<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

use App\Models\Concerns\HasUuid;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasUuid;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role'
    ];

    protected $casts = [
        'role' => UserRole::class,
        'status' => UserStatus::class,
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
