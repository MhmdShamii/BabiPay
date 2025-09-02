<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/currency', [CurrencyController::class, 'create'])
        ->middleware('can:create-currency');
    Route::get('/currency', [CurrencyController::class, 'getAll']);

    Route::post('/wallets', [WalletController::class, 'create']);
    Route::get('/wallets', [WalletController::class, 'showAll']);
    Route::get('/wallets/{wallet}', [WalletController::class, 'show'])
        ->middleware('can:view-wallet,wallet');

    Route::get('/users/{user}/wallets', [WalletController::class, 'showUserWallets'])
        ->middleware('can:view-wallets,user');

    Route::post('/transactions/deposit', [TransactionsController::class, 'deposit'])
        ->middleware('can:deposit');
    Route::post('/transactions/withdraw', [TransactionsController::class, 'withdraw'])
        ->middleware('can:withdraw');
    Route::post('/transactions/p2p', [TransactionsController::class, 'p2p']);
});
