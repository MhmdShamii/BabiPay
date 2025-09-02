<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\RequestDeposit;
use App\Http\Requests\RequestP2P;
use App\Http\Requests\RequestWithdraw;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TransactionsController extends Controller
{
    public function deposit(RequestDeposit $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $wallet = Wallet::findOrFail($validation['wallet_id']);

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

        return response()->json([
            'message' => 'Deposit successful',
            'wallet'  => $wallet,
            'transaction' => $transaction
        ]);
    }

    public function withdraw(RequestWithdraw $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $wallet = Wallet::findOrFail($validation['wallet_id']);

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

        return response()->json([
            'message' => 'Withdrawal successful',
            'wallet'  => $wallet,
            'transaction' => $transaction
        ]);
    }

    public function p2p(RequestP2P $request)
    {
        $validation = $request->validated();
        $user = Auth::user();

        $senderWallet = Wallet::findOrFail($validation['sender_wallet_id']);


        if (! Gate::allows('canSendFromWallet', $senderWallet)) {
            return response()->json(['message' => 'Unauthorizedss'], 403);
        }

        if ($senderWallet->balance < $validation['amount']) {
            return response()->json(['message' => 'Insufficient balance in sender wallet'], 400);
        }

        $reciveruser = User::where('email', $validation['reciverIdentifier'])
            ->orWhere('username', $validation['reciverIdentifier'])
            ->first();

        if (! $reciveruser) {
            return response()->json(['message' => 'Reciver not Found'], 400);
        }

        $receiverWallets = Wallet::where("user_id", $reciveruser->id)->get();

        if ($receiverWallets->isEmpty()) {
            return response()->json(['message' => 'Receiver wallets not found'], 400);
        }

        $walletToRecive = $receiverWallets->where("currency_id", $senderWallet->currency_id)->first();

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
            'description' => $validation['description'],
            'status' => TransactionStatus::Complete,
            'transaction_date_time' => now(),
        ]);

        return response()->json([
            'message' => 'P2P transfer successful',
            'sender_wallet'  => $senderWallet,
            'receiver_wallet' => $walletToRecive,
            'transaction' => $transaction
        ]);
    }
}
