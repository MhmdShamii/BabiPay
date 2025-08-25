<?php

namespace App\Models;

use App\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wallet extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'balance',
        'status',
    ];

    protected $casts = [
        'status' => WalletStatus::class,
    ];

    public function owner(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
