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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        DB::beginTransaction();

        try {
            // Validate request fields
            $data = $request->validated();

            // Assign default role
            $data['role'] = UserRole::User;

            // Create user
            $user = User::create($data);

            // Generate access token
            $token = $user->createToken('api', ['*']);
            $token->accessToken->forceFill([
                'expires_at' => now()->addDays(2),
            ])->save();

            // Create the user's default starting wallet
            $defaultCurrencyId = Currency::where('code', 'USD')->value('id');

            if (!$defaultCurrencyId) {
                throw new \Exception('Default currency not found', 500);
            }

            $newWallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency_id' => $defaultCurrencyId,
                'status' => WalletStatus::Active,
            ]);

            DB::commit();

            // Return user
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
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request,
            ]);

            return response()->json([
                'message' => 'Registration failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function login(LoginUserRequest $request)
    {
        try {
            // Validate
            $data = $request->validated();

            // Grab the validated data
            $identifier = $data['identifier'];
            $password   = $data['password'];

            // Search for user by email or username
            $user = User::where('email', $identifier)
                ->orWhere('username', $identifier)
                ->first();

            // Check if the password matches the password of the found user
            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid credentials. Please check your login details.'
                ], 401);
            }

            if ($user->status !== UserStatus::Active) {
                return response()->json([
                    'message' => 'Your account is not active. Please contact support.'
                ], 401);
            }

            // Delete old user access tokens
            $user->tokens()->delete();

            // Generate new token
            $newtoken = $user->createToken('api', ['*']);
            $newtoken->accessToken->forceFill([
                'expires_at' => now()->addDays(2),
            ])->save();

            // Return the logged in user
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
        } catch (\Exception $e) {
            Log::error('Login failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'identifier' => $request['identifier']
            ]);

            return response()->json([
                'message' => 'Login failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
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
                    'wallets'   => $wallets,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user profile: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to retrieve user profile.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()?->delete();

            return response()->json([
                'message' => 'Logged out successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Logout failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user() ? $request->user()->id : 'unknown'
            ]);

            return response()->json([
                'message' => 'Logout failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
