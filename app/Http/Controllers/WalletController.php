<?php

namespace App\Http\Controllers;

use App\Enums\WalletStatus;
use App\Http\Requests\RequestWallet;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function create(RequestWallet $request)
    {
        $data = $request->validated();

        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }


        $wallet = Wallet::create([
            'user_id'     => $user->id,
            'currency_id' => $data['currency_id'],
            'status'      => WalletStatus::Active,
            'balance'     => 0,
        ])->load('currency:id,name,code');

        return response()->json([
            'wallet' => [
                'id'       => $wallet->id,
                'balance'  => $wallet->balance,
                'currency' => $wallet->currency->name,
                'status'   => $wallet->status,
            ],
        ], 201);
    }

    public function show(Wallet $wallet)
    {
        $user = Auth::user();

        // Find wallet with authorization check
        $wallet = Wallet::with('currency:id,name,code,decimal_places')
            ->where('user_id', $user->id)
            ->find($wallet->id);

        if (!$wallet) {
            return response()->json([
                'error' => 'Wallet not found or access denied'
            ], 404);
        }

        if (!$wallet->currency) {
            return response()->json([
                'error' => 'Currency information not available'
            ], 500);
        }

        // Format balance properly
        $balance = $wallet->balance / pow(10, $wallet->currency->decimal_places);
        $formattedBalance = number_format($balance, $wallet->currency->decimal_places);

        return response()->json([
            'wallet' => [
                'id' => $wallet->id,
                'balance' => (float) $formattedBalance,
                'formatted_balance' => $formattedBalance,
                'currency' => $wallet->currency->name,
                'currency_code' => $wallet->currency->code,
                'status' => $wallet->status,
                'decimal_places' => $wallet->currency->decimal_places
            ]
        ]);
    }

    public function showUserWallets(User $user)
    {
        $wallets = Wallet::with('currency:id,name,code,decimal_places')
            ->where('user_id', $user->id)
            ->get();

        if ($wallets->isEmpty()) {
            return response()->json([
                'message' => 'No wallets found for this user',
                'ownerId' => $user->id,
                'ownerUserName' => $user->username,
                'wallets' => []
            ], 200);
        }

        return response()->json([
            'ownerId' => $user->id,
            'ownerUserName' => $user->username,
            'wallets' => $wallets->map(function ($wallet) {
                if (!$wallet->currency) {
                    return [
                        'id' => $wallet->id,
                        'balance' => 0,
                        'formatted_balance' => '0.00',
                        'currency' => 'Unknown Currency',
                        'currency_code' => 'N/A',
                        'status' => $wallet->status,
                        'error' => 'Currency data missing'
                    ];
                }

                $balance = $wallet->balance / pow(10, $wallet->currency->decimal_places);
                $formattedBalance = number_format($balance, $wallet->currency->decimal_places);

                return [
                    'id' => $wallet->id,
                    'balance' => (float) $formattedBalance,
                    'formatted_balance' => $formattedBalance,
                    'currency' => $wallet->currency->name,
                    'currency_code' => $wallet->currency->code,
                    'status' => $wallet->status,
                    'decimal_places' => $wallet->currency->decimal_places
                ];
            })
        ]);
    }

    public function showAll()
    {
        $user = Auth::user();

        $wallets = Wallet::with('currency:id,name,code,decimal_places')
            ->where('user_id', $user->id)
            ->get();

        return response()->json(
            $wallets->map(function ($wallet) {
                $balance = $wallet->balance / pow(10, $wallet->currency->decimal_places);

                return [
                    'id' => $wallet->id,
                    'balance' => (float) number_format($balance, $wallet->currency->decimal_places, '.', ''),
                    'formatted_balance' => number_format($balance, $wallet->currency->decimal_places),
                    'currency' => $wallet->currency->name,
                    'currency_code' => $wallet->currency->code,
                    'status' => $wallet->status,
                    'decimal_places' => $wallet->currency->decimal_places
                ];
            })
        );
    }

    public function freeze(Wallet $wallet)
    {
        if ($wallet->status === WalletStatus::Frozen) {
            return response()->json(['message' => 'Wallet is already frozen.'], 400);
        }

        $wallet->status = WalletStatus::Frozen;
        $wallet->save();


        $reciveruser = User::where('id', $wallet->user_id)
            ->first();

        return response()->json(['message' => 'Wallet has been frozen successfully.', 'wallet' => [
            'id'       => $wallet->id,
            'ownerName' => $reciveruser->username,
            'balance'  => $wallet->balance / (pow(10, $wallet->currency->decimal_places)),
            'currency' => $wallet->currency->name,
            'status'   => $wallet->status,
        ]], 200);
    }

    public function activate(Wallet $wallet)
    {
        if ($wallet->status === WalletStatus::Active) {
            return response()->json(['message' => 'Wallet is already active.'], 400);
        }

        $wallet->status = WalletStatus::Active;
        $wallet->save();

        $reciveruser = User::where('id', $wallet->user_id)
            ->first();

        return response()->json(['message' => 'Wallet has been activated successfully.', 'wallet' => [
            'id'       => $wallet->id,
            'ownerName' => $reciveruser->username,
            'balance'  => $wallet->balance / (pow(10, $wallet->currency->decimal_places)),
            'currency' => $wallet->currency->name,
            'status'   => $wallet->status,
        ]], 200);
    }
}
