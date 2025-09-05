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

        $wallet = Wallet::findOrFail($validation['wallet_id']);


        $walletOwner = User::where('id', $wallet->user_id)->first();

        if ($walletOwner->status !== UserStatus::Active) {
            return response()->json(['message' => 'Receiver user is ' . $walletOwner->status->value], 403);
        }

        if ($wallet->status !== WalletStatus::Active) {
            return response()->json(['message' => 'Wallet is ' . $wallet->status->value], 400);
        }

        $wallet->balance -= $validation['amount'];
        $wallet->save();

        $transaction = Transaction::create([
            'user_id'   => $user->id,
            'wallet_id' => $wallet->id,
            'amount'    => $validation['amount'],
            'transaction_type' => TransactionType::Withdraw,
            'description' => 'Withdraw from wallet',
            'status' => TransactionStatus::Complete,
            'transaction_date_time' => now(),
        ]);

        $walletOwner = User::find($wallet->user_id)->only(['id', 'username', 'email']);

        return response()->json([
            'message' => 'Withdrawal successful',
            'withdrawAmount'  => $validation['amount'],
            'wallet'  => [
                'id'       => $wallet->id,
                'owner'     => $walletOwner,
                'balance'  => $wallet->balance,
                'currency' => $wallet->currency->name,
            ],
            'transaction' => [
                'id'       => $transaction->id,
                'amount'   => $transaction->amount,
                'type'     => $transaction->transaction_type,
                'status'   => $transaction->status,
                'date'     => $transaction->transaction_date_time,
            ]
        ]);
    }

    public function p2p(RequestP2P $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $senderWallet = Wallet::findOrFail($validation['sender_wallet_id']);

        if ($senderWallet->status !== WalletStatus::Active) {
            return response()->json(['message' => 'sender wallet is ' . $senderWallet->status->value], 400);
        }

        if (! Gate::allows('canSendFromWallet', $senderWallet)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($senderWallet->balance < $validation['amount']) {
            return response()->json(['message' => 'Insufficient balance in sender wallet'], 400);
        }

        $receiverUser = User::where('email', $validation['receiverIdentifier'])
            ->orWhere('username', $validation['receiverIdentifier'])
            ->first();

        if (! $receiverUser) {
            return response()->json(['message' => 'Receiver not Found'], 400);
        }

        if ($receiverUser->status !== UserStatus::Active) {
            return response()->json(['message' => 'Receiver user is ' . $receiverUser->status->value], 403);
        }

        $receiverWallets = Wallet::where("user_id", $receiverUser->id)->get();


        if ($receiverWallets->isEmpty()) {
            return response()->json(['message' => 'Receiver wallets not found'], 400);
        }

        $walletToRecive = $receiverWallets->where("currency_id", $senderWallet->currency_id)->first();

        if ($walletToRecive->status !== WalletStatus::Active) {
            return response()->json(['message' => 'reciver wallet is ' . $walletToRecive->status->value], 400);
        }

        if (! $walletToRecive) {
            return response()->json(['message' => 'Reciver Dont have a wallet for this currency'], 400);
        }

        $senderWallet->balance -= $validation['amount'];
        $walletToRecive->balance += $validation['amount'];
        $senderWallet->save();
        $walletToRecive->save();

        $transaction = Transaction::create([
            'user_id'   => $user->id,
            'wallet_id' => $senderWallet->id,
            'related_wallet_id' => $walletToRecive->id,
            'amount'    => $validation['amount'],
            'transaction_type' => TransactionType::PeerToPeer,
            'description' => $validation['description'] ?? 'P2P transfer to ' . $receiverUser->username,
            'status' => TransactionStatus::Complete,
            'transaction_date_time' => now(),
        ]);



        return response()->json([
            'message' => 'P2P transfer successful',
            'senderWallet'  => [
                'id'       => $senderWallet->id,
                'userName'  => $user->username,
                'balance'  => $senderWallet->balance,
                'currency' => $senderWallet->currency->name,
            ],
            'receiverWallet' => [
                'id'       => $walletToRecive->id,
                'receiverName'  => $receiverUser->username,
                'balance'  => $walletToRecive->balance,
                'currency' => $walletToRecive->currency->name,
            ],
            'transaction' => [
                'id'       => $transaction->id,
                'from'     => $user->username,
                'to'       => $receiverUser->username,
                'currency' => $senderWallet->currency->name,
                'amount'   => $transaction->amount,
                'description' => $transaction->description,
                'type'     => $transaction->transaction_type,
                'status'   => $transaction->status,
                'date'     => $transaction->transaction_date_time,
            ]
        ]);
    }
}
