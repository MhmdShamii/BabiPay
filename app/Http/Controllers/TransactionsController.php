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
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class TransactionsController extends Controller
{
    public function deposit(RequestDeposit $request)
    {
        try {
            $validation = $request->validated();
            $user = Auth::user();

            $result = DB::transaction(function () use ($validation, $user) {
                $wallet = Wallet::where('id', $validation['wallet_id'])
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->load('currency');

                $amountInCents = (int) ($validation['amount'] * (pow(10, $wallet->currency->decimal_places)));

                $walletOwnerModel = User::where('id', $wallet->user_id)->firstOrFail();

                if ($walletOwnerModel->status !== UserStatus::Active) {
                    abort(403, 'Receiver user is ' . $walletOwnerModel->status->value);
                }

                if ($wallet->status !== WalletStatus::Active) {
                    abort(403, 'Wallet is not active');
                }

                $wallet->balance += $amountInCents;
                $wallet->save();

                $transaction = Transaction::create([
                    'user_id'   => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount'    => $amountInCents,
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
                        'balance'  => ($wallet->balance) / (pow(10, $wallet->currency->decimal_places)),
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
        } catch (ModelNotFoundException $e) {
            Log::error('Wallet or user not found during deposit: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'wallet_id' => $validation['wallet_id'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Wallet not found or inaccessible.',
                'error' => 'The specified wallet does not exist or you do not have access to it.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'Authorization failed.',
                'error' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            // Re-throw HTTP exceptions (like abort()) to let Laravel handle them
            throw $e;
        } catch (\Exception $e) {
            Log::error('Deposit transaction failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'wallet_id' => $validation['wallet_id'] ?? 'unknown',
                'amount' => $validation['amount'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Deposit failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function withdraw(RequestWithdraw $request)
    {
        try {
            $validation = $request->validated();
            $user = Auth::user();

            $result = DB::transaction(function () use ($validation, $user) {
                $wallet = Wallet::where('id', $validation['wallet_id'])
                    ->lockForUpdate()
                    ->firstOrFail()->load('currency');

                $walletOwner = User::where('id', $wallet->user_id)->firstOrFail();

                if ($validation['amount'] > ($wallet->balance) / (pow(10, $wallet->currency->decimal_places))) {
                    abort(409, 'Insufficient balance in wallet');
                }

                if ($walletOwner->status !== UserStatus::Active) {
                    abort(403, 'Account wallet is ' . $walletOwner->status->value);
                }

                if ($wallet->status !== WalletStatus::Active) {
                    abort(403, 'Wallet is ' . $wallet->status->value);
                }

                $amountInCents = (int) ($validation['amount'] * (pow(10, $wallet->currency->decimal_places)));

                $wallet->balance -= $amountInCents;
                $wallet->save();

                $wallet = $wallet->fresh()->load('currency');

                $transaction = Transaction::create([
                    'user_id'   => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount'    => $amountInCents,
                    'transaction_type' => TransactionType::Withdraw,
                    'description' => 'Withdraw from wallet',
                    'status' => TransactionStatus::Complete,
                    'transaction_date_time' => now(),
                ]);

                return [
                    'wallet' => [
                        'id'       => $wallet->id,
                        'owner'    => $walletOwner->only(['id', 'username', 'email']),
                        'balance'  => ($wallet->balance) / (pow(10, $wallet->currency->decimal_places)),
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
        } catch (ModelNotFoundException $e) {
            Log::error('Wallet or user not found during withdrawal: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'wallet_id' => $validation['wallet_id'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Wallet not found or inaccessible.',
                'error' => 'The specified wallet does not exist or you do not have access to it.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            // Re-throw HTTP exceptions (like abort()) to let Laravel handle them
            throw $e;
        } catch (\Exception $e) {
            Log::error('Withdrawal transaction failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'wallet_id' => $validation['wallet_id'] ?? 'unknown',
                'amount' => $validation['amount'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Withdrawal failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function p2p(RequestP2P $request)
    {
        try {
            $validation = $request->validated();
            $user = Auth::user();

            $result = DB::transaction(function () use ($validation, $user) {
                // Lock sender wallet
                $senderWallet = Wallet::where('id', $validation['sender_wallet_id'])
                    ->lockForUpdate()
                    ->firstOrFail()->load('currency');

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

                if ($senderWallet->id === $receiverWallet->id) {
                    abort(409, 'Cannot transfer to the same wallet');
                }

                $amountInCents = (int) ($validation['amount'] * (pow(10, $senderWallet->currency->decimal_places)));

                // Move funds
                $senderWallet->balance  -= $amountInCents;
                $receiverWallet->balance += $amountInCents;
                $senderWallet->save();
                $receiverWallet->save();

                // Create transaction
                $transaction = Transaction::create([
                    'user_id'             => $user->id,
                    'wallet_id'           => $senderWallet->id,
                    'related_wallet_id'   => $receiverWallet->id,
                    'amount'              => $amountInCents,
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
                        'balance'  => $senderWallet->balance / (pow(10, $senderWallet->currency->decimal_places)),
                        'currency' => $senderWallet->currency->name,
                    ],
                    'receiverWallet' => [
                        'id'       => $receiverWallet->id,
                        'owner'    => $receiverUser->only(['id', 'username', 'email']),
                        'balance'  => $receiverWallet->balance / (pow(10, $receiverWallet->currency->decimal_places)),
                        'currency' => $receiverWallet->currency->name,
                    ],
                    'transaction' => [
                        'id'        => $transaction->id,
                        'amount'    => $transaction->amount / (pow(10, $senderWallet->currency->decimal_places)),
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
        } catch (ModelNotFoundException $e) {
            Log::error('Wallet not found during P2P transfer: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'sender_wallet_id' => $validation['sender_wallet_id'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Sender wallet not found or inaccessible.',
                'error' => 'The specified sender wallet does not exist or you do not have access to it.'
            ], Response::HTTP_NOT_FOUND);
        } catch (HttpResponseException $e) {
            // Re-throw HTTP exceptions (like abort()) to let Laravel handle them
            throw $e;
        } catch (\Exception $e) {
            Log::error('P2P transfer failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'sender_wallet_id' => $validation['sender_wallet_id'] ?? 'unknown',
                'receiver_identifier' => $validation['receiverIdentifier'] ?? 'unknown',
                'amount' => $validation['amount'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'P2P transfer failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
