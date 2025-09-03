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
        return response()->json([
            'wallet' => [
                'id'       => $wallet->id,
                'balance'  => $wallet->balance,
                'currency' => $wallet->currency->name,
                'status'   => $wallet->status,
            ]
        ]);
    }

    public function showUserWallets(User $user)
    {
        $wallets = Wallet::where('user_id', $user->id)->get();

        return response()->json([
            'ownerId' => $user->id,
            'ownerUserName' => $user->username,
            'wallets' => $wallets->map(function ($wallet) {
                return [
                    'id'       => $wallet->id,
                    'balance'  => $wallet->balance,
                    'currency' => $wallet->currency->name,
                    'status'   => $wallet->status,
                ];
            })

        ]);
    }

    public function showAll()
    {
        $user = Auth::user();
        $wallets = Wallet::where('user_id', $user->id)->get();

        return response()->json(
            $wallets->map(function ($wallet) {
                return [
                    'id'       => $wallet->id,
                    'balance'  => $wallet->balance,
                    'currency' => $wallet->currency->name,
                    'status'   => $wallet->status,
                ];
            })
        );
    }
}
