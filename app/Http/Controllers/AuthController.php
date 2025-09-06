<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        //validate request feild
        $data = $request->validated();

        //assign default role
        $data['role'] = UserRole::User;

        //create user
        $user = User::create($data);

        //generate access token
        $token = $user->createToken('api', ['*']);
        $token->accessToken->forceFill([
            'expires_at' => now()->addDays(2),
        ])->save();

        //create the users defauld starting wallet
        $defaultCurrencyId = Currency::where('code', 'USD')->value('id');

        $newWallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'currency_id' => $defaultCurrencyId,
            'status' => WalletStatus::Active,
        ]);

        //return user
        return response()->json([
            'message' => 'Registered successfully.',
            'user'    => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
                'role'     => $user->role,
                'phone'    => $user->phone,
                'created_at' => $user->created_at,
                'wallet' => [
                    'id' => $newWallet->id,
                    'balance' => $newWallet->balance,
                    'currency' => $newWallet->currency->name,
                    'status' => $newWallet->status,
                ]
            ],
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(LoginUserRequest $request)
    {

        //validate
        $data = $request->validated();

        //grabe the validated data
        $identifier = $data['identifier'];
        $password   = $data['password'];

        //search fir user by email or username
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        //check if the password maches the password of the found users
        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->status !== UserStatus::Active) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        //delete old user access token
        $user->tokens()->delete();

        //generatge new token
        $newtoken = $user->createToken('api', ['*']);
        $newtoken->accessToken->forceFill([
            'expires_at' => now()->addDays(2),
        ])->save();

        //return the loged in user
        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'email'     => $user->email,
                'role'      => $user->role,
                'phone'     => $user->phone,
            ],
            'token' => $newtoken->plainTextToken,
            'expires_at' => $newtoken->accessToken->expires_at,
            'token_type' => 'Bearer',
        ], 200);
    }


    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $wallets = $user->wallets()
            ->with('currency:id,name,code')
            ->latest()
            ->get()
            ->map(function ($wallet) {
                return [
                    'id'       => $wallet->id,
                    'balance'  => $wallet->balance,
                    'currency' => $wallet->currency->name,
                    'status'   => $wallet->status,
                ];
            })
            ->all();

        return response()->json([
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'email'     => $user->email,
                'role'      => $user->role,
                'phone'     => $user->phone,
                'wallets' => $wallets,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()?->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}
