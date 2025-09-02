<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\RequestDeposit;
use App\Http\Requests\RequestP2P;
use App\Http\Requests\RequestWithdraw;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;

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
        $receiverWallet = Wallet::findOrFail($validation['receiver_wallet_id']);

        if ($senderWallet->currency_id !== $receiverWallet->currency_id) {
            return response()->json(['message' => 'Currency mismatch between wallets'], 400);
        }

        if ($senderWallet->balance < $validation['amount']) {
            return response()->json(['message' => 'Insufficient balance in sender wallet'], 400);
        }

        $senderWallet->balance -= $validation['amount'];
        $receiverWallet->balance += $validation['amount'];
        $senderWallet->save();
        $receiverWallet->save();

        $transaction = Transaction::create([
            'user_id'   => $user->id,
            'wallet_id' => $senderWallet->id,
            'related_wallet_id' => $receiverWallet->id,
            'amount'    => $validation['amount'],
            'transaction_type' => TransactionType::PeerToPeer,
            'description' => 'P2P transfer',
            'status' => TransactionStatus::Complete,
            'transaction_date_time' => now(),
        ]);

        return response()->json([
            'message' => 'P2P transfer successful',
            'sender_wallet'  => $senderWallet,
            'receiver_wallet' => $receiverWallet,
            'transaction' => $transaction
        ]);
    }
}
