<?php

namespace App\Providers;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('create-currency', fn($user) => $user->role === UserRole::Admin);

        Gate::define('view-wallet', function ($user, $wallet) {
            return $wallet->user_id === $user->id;
        });

        Gate::define('view-wallets', function ($user, $userParam) {
            return $user->id === $userParam->id || $user->role === UserRole::Employee || $user->role === UserRole::Admin;
        });

        Gate::define('canSendFromWallet', function ($user, $wallet) {
            return $wallet->user_id === $user->id;
        });

        Gate::define('deposit', fn($user) => $user->role === UserRole::Employee || $user->role === UserRole::Admin);
        Gate::define('withdraw', fn($user) => $user->role === UserRole::Employee || $user->role === UserRole::Admin);
        Gate::define('isAdmin', fn($user) => $user->role === UserRole::Admin);
    }
}
