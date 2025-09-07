<?php

namespace App\Http\Controllers;

use App\Enums\WalletStatus;
use App\Http\Requests\RequestWallet;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class WalletController extends Controller
{
    public function create(RequestWallet $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $wallet = Wallet::create([
                'user_id'     => $user->id,
                'currency_id' => $data['currency_id'],
                'status'      => WalletStatus::Active,
                'balance'     => 0,
            ])->load('currency:id,name,code');

            return response()->json([
                'message' => 'Wallet created successfully.',
                'wallet' => [
                    'id'       => $wallet->id,
                    'balance'  => $wallet->balance,
                    'currency' => $wallet->currency->name,
                    'status'   => $wallet->status,
                ],
            ], Response::HTTP_CREATED);
        } catch (QueryException $e) {
            Log::error('Failed to create wallet: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'currency_id' => $data['currency_id'] ?? 'unknown'
            ]);

            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Wallet creation failed.',
                    'error' => 'A wallet with this currency already exists for this user.'
                ], Response::HTTP_CONFLICT);
            }

            return response()->json([
                'message' => 'Wallet creation failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected error during wallet creation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'currency_id' => $data['currency_id'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Wallet creation failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Wallet $wallet): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find wallet with authorization check
            $wallet = Wallet::with('currency:id,name,code,decimal_places')
                ->where('user_id', $user->id)
                ->find($wallet->id);

            if (!$wallet) {
                return response()->json([
                    'message' => 'Wallet not found or access denied.'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$wallet->currency) {
                Log::error('Currency data missing for wallet', [
                    'wallet_id' => $wallet->id,
                    'currency_id' => $wallet->currency_id
                ]);

                return response()->json([
                    'message' => 'Currency information not available.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Format balance properly
            $balance = $wallet->balance / pow(10, $wallet->currency->decimal_places);
            $formattedBalance = number_format($balance, $wallet->currency->decimal_places);

            return response()->json([
                'message' => 'Wallet retrieved successfully.',
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
        } catch (\Exception $e) {
            Log::error('Failed to retrieve wallet: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'wallet_id' => $wallet->id ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to retrieve wallet. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function showUserWallets(User $user): JsonResponse
    {
        try {
            $wallets = Wallet::with('currency:id,name,code,decimal_places')
                ->where('user_id', $user->id)
                ->get();

            if ($wallets->isEmpty()) {
                return response()->json([
                    'message' => 'No wallets found for this user.',
                    'ownerId' => $user->id,
                    'ownerUserName' => $user->username,
                    'wallets' => []
                ]);
            }

            $formattedWallets = $wallets->map(function ($wallet) {
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
            });

            return response()->json([
                'message' => 'User wallets retrieved successfully.',
                'ownerId' => $user->id,
                'ownerUserName' => $user->username,
                'wallets' => $formattedWallets
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user wallets: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'requested_user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Failed to retrieve user wallets. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function showAll(): JsonResponse
    {
        try {
            $user = Auth::user();

            $wallets = Wallet::with('currency:id,name,code,decimal_places')
                ->where('user_id', $user->id)
                ->get();

            $formattedWallets = $wallets->map(function ($wallet) {
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
            });

            return response()->json([
                'message' => 'Wallets retrieved successfully.',
                'wallets' => $formattedWallets
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve wallets: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve wallets. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function freeze(Wallet $wallet): JsonResponse
    {
        try {
            if ($wallet->status === WalletStatus::Frozen) {
                return response()->json([
                    'message' => 'Wallet is already frozen.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $wallet->status = WalletStatus::Frozen;
            $wallet->save();

            $receiverUser = User::find($wallet->user_id);

            return response()->json([
                'message' => 'Wallet has been frozen successfully.',
                'wallet' => [
                    'id'       => $wallet->id,
                    'ownerName' => $receiverUser->username,
                    'balance'  => $wallet->balance / (pow(10, $wallet->currency->decimal_places)),
                    'currency' => $wallet->currency->name,
                    'status'   => $wallet->status,
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wallet not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to freeze wallet: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'wallet_id' => $wallet->id ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to freeze wallet. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function activate(Wallet $wallet): JsonResponse
    {
        try {
            if ($wallet->status === WalletStatus::Active) {
                return response()->json([
                    'message' => 'Wallet is already active.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $wallet->status = WalletStatus::Active;
            $wallet->save();

            $receiverUser = User::find($wallet->user_id);

            return response()->json([
                'message' => 'Wallet has been activated successfully.',
                'wallet' => [
                    'id'       => $wallet->id,
                    'ownerName' => $receiverUser->username,
                    'balance'  => $wallet->balance / (pow(10, $wallet->currency->decimal_places)),
                    'currency' => $wallet->currency->name,
                    'status'   => $wallet->status,
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Wallet not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to activate wallet: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'wallet_id' => $wallet->id ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to activate wallet. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
