<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserTransactionRole;
use App\Models\Concerns\HasUUID;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUUID;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'related_wallet_id',
        'amount',
        'user_transaction_role',
        'description'
    ];

    protected $casts = [
        'status' => TransactionStatus::class,
        'transaction_type' => TransactionType::class,
        'user_transaction_roll' => UserTransactionRole::class,
    ];
}
