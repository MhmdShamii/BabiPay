<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignUuid('wallet_id')->nullable()->constrained()->nullOnDelete();

            //have value ony if transaction type is P2P
            $table->foreignUuid('related_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();

            $table->string('user_transaction_role')->default('sender');


            $table->unsignedBigInteger('amount');
            $table->string('transaction_type')->default('P2P');

            $table->string('description');
            $table->timestampTz("transaction_date_time");

            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
