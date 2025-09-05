<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Http\Requests\RequestDeposit;
use App\Http\Requests\RequestP2P;
use App\Http\Requests\RequestWithdraw;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransactionsController extends Controller
{
    public function deposit(RequestDeposit $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $result = DB::transaction(function () use ($validation, $user) {

            $wallet = Wallet::where('id', $validation['wallet_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $walletOwnerModel = User::where('id', $wallet->user_id)->firstOrFail();

            if ($walletOwnerModel->status !== UserStatus::Active) {
                abort(403, 'Receiver user is ' . $walletOwnerModel->status->value);
            }

            if ($wallet->status !== WalletStatus::Active) {
                abort(403, 'Wallet is not active');
            }

            $wallet->balance += $validation['amount'];
            $wallet->save();

            $transaction = Transaction::create([
                'user_id'   => $user->id,
                'wallet_id' => $wallet->id,
                'amount'    => $validation['amount'],
                'transaction_type' => TransactionType::Deposit,
                'description' => 'Deposit to wallet',
                'status' => TransactionStatus::Complete,
                'transaction_date_time' => now(),
            ]);

            $wallet = $wallet->fresh()->load('currency');

            return [
                'wallet' => [
                    'id'       => $wallet->id,
                    'owner'    => $walletOwnerModel->only(['id', 'username', 'email']),
                    'balance'  => $wallet->balance,
                    'currency' => $wallet->currency->name,
                ],
                'transaction' => [
                    'id'          => $transaction->id,
                    'performedBy' => $user->username,
                    'amount'      => $transaction->amount,
                    'type'        => $transaction->transaction_type,
                    'date'        => $transaction->transaction_date_time,
                ],
            ];
        });

        return response()->json([
            'message'      => 'Deposit successful',
            'amountAdded'  => $validation['amount'],
            'wallet'       => $result['wallet'],
            'transaction'  => $result['transaction'],
        ]);
    }

    public function withdraw(RequestWithdraw $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $result = DB::transaction(function () use ($validation, $user) {

            $wallet = Wallet::where('id', $validation['wallet_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $walletOwner = User::where('id', $wallet->user_id)->firstOrFail();

            if ($validation['amount'] > $wallet->balance) {
                abort(409, 'Insufficient balance in wallet');
            }

            if ($walletOwner->status !== UserStatus::Active) {
                abort(403, 'Account wallet is ' . $walletOwner->status->value);
            }

            if ($wallet->status !== WalletStatus::Active) {
                abort(403, 'Wallet is ' . $wallet->status->value);
            }

            $wallet->balance -= $validation['amount'];
            $wallet->save();

            $wallet = $wallet->fresh()->load('currency');

            $transaction = Transaction::create([
                'user_id'   => $user->id,
                'wallet_id' => $wallet->id,
                'amount'    => $validation['amount'],
                'transaction_type' => TransactionType::Withdraw,
                'description' => 'Withdraw from wallet',
                'status' => TransactionStatus::Complete,
                'transaction_date_time' => now(),
            ]);

            return [
                'wallet' => [
                    'id'       => $wallet->id,
                    'owner'    => $walletOwner->only(['id', 'username', 'email']),
                    'balance'  => $wallet->balance,
                    'currency' => $wallet->currency->name,
                ],
                'transaction' => [
                    'id'            => $transaction->id,
                    'withdrawedFrom' => $walletOwner->username,
                    'amount'        => $transaction->amount,
                    'type'          => $transaction->transaction_type,
                    'date'          => $transaction->transaction_date_time,
                ],
            ];
        });

        return response()->json([
            'message'        => 'Withdrawal successful',
            'withdrawAmount' => $validation['amount'],
            'wallet'         => $result['wallet'],
            'transaction'    => $result['transaction'],
        ]);
    }

    public function p2p(RequestP2P $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $result = DB::transaction(function () use ($validation, $user) {

            // Lock sender wallet
            $senderWallet = Wallet::where('id', $validation['sender_wallet_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($senderWallet->status !== WalletStatus::Active) {
                abort(403, 'Sender wallet is ' . $senderWallet->status->value);
            }

            if (! Gate::allows('canSendFromWallet', $senderWallet)) {
                abort(403, 'Unauthorized');
            }

            if ($senderWallet->balance < $validation['amount']) {
                abort(409, 'Insufficient balance in sender wallet');
            }

            // Find receiver user (by email or username)
            $receiverUser = User::where('email', $validation['receiverIdentifier'])
                ->orWhere('username', $validation['receiverIdentifier'])
                ->first();

            if (! $receiverUser) {
                abort(404, 'Receiver not found');
            }

            if ($receiverUser->status !== UserStatus::Active) {
                abort(403, 'Receiver user is ' . $receiverUser->status->value);
            }

            // Find & lock receiver wallet with same currency
            $receiverWallet = Wallet::where('user_id', $receiverUser->id)
                ->where('currency_id', $senderWallet->currency_id)
                ->lockForUpdate()
                ->first();

            if (! $receiverWallet) {
                abort(404, 'Receiver does not have a wallet for this currency');
            }

            if ($receiverWallet->status !== WalletStatus::Active) {
                abort(403, 'Receiver wallet is ' . $receiverWallet->status->value);
            }

            // Move funds
            $senderWallet->balance  -= $validation['amount'];
            $receiverWallet->balance += $validation['amount'];
            $senderWallet->save();
            $receiverWallet->save();

            // Create transaction
            $transaction = Transaction::create([
                'user_id'             => $user->id,
                'wallet_id'           => $senderWallet->id,
                'related_wallet_id'   => $receiverWallet->id,
                'amount'              => $validation['amount'],
                'transaction_type'    => TransactionType::PeerToPeer,
                'description'         => $validation['description'] ?? ('P2P transfer to ' . $receiverUser->username),
                'status'              => TransactionStatus::Complete,
                'transaction_date_time' => now(),
            ]);

            // Refresh for response
            $senderWallet   = $senderWallet->fresh()->load('currency');
            $receiverWallet = $receiverWallet->fresh()->load('currency');

            return [
                'senderWallet' => [
                    'id'       => $senderWallet->id,
                    'balance'  => $senderWallet->balance,
                    'currency' => $senderWallet->currency->name,
                ],
                'receiverWallet' => [
                    'id'       => $receiverWallet->id,
                    'owner'    => $receiverUser->only(['id', 'username', 'email']),
                    'balance'  => $receiverWallet->balance,
                    'currency' => $receiverWallet->currency->name,
                ],
                'transaction' => [
                    'id'        => $transaction->id,
                    'amount'    => $transaction->amount,
                    'type'      => $transaction->transaction_type,
                    'date'      => $transaction->transaction_date_time,
                    'note'      => $transaction->description,
                ],
            ];
        });

        return response()->json([
            'message'        => 'P2P transfer successful',
            'senderWallet'   => $result['senderWallet'],
            'receiverWallet' => $result['receiverWallet'],
            'transaction'    => $result['transaction'],
        ]);
    }
}
