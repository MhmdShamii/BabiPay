<?php

namespace App\Models;

use App\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
