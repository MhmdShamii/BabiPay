<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone');
            $table->string('role')->default('user');
            $table->string('status')->default('active');

            $table->timestamps();
        });

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@babipay.com'],
            [
                'id' => DB::table('users')->where('email', 'admin@babipay.com')->value('id')
                    ?? (string) Str::uuid(),
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => UserRole::Admin,
                'phone' => '+961 00 000 000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
