<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserTransactionRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'related_wallet_id',
        'amount',
        'user_transaction_role',
        'transaction_type',
        'description',
        'status',
        'transaction_date_time',
    ];

    protected $casts = [
        'status' => TransactionStatus::class,
        'transaction_type' => TransactionType::class,
        'user_transaction_role' => UserTransactionRole::class,
    ];

    public function relatedWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'related_wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'related_wallet_id');
    }
}
