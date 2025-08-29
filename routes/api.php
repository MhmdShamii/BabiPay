<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware("auth:sanctum")->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/currency', [CurrencyController::class, 'create']);
    Route::get('/currency', [CurrencyController::class, 'getAll']);

    Route::post('/wallet', [WalletController::class, 'create']);
    Route::get('/wallet', [WalletController::class, 'show']);
});
