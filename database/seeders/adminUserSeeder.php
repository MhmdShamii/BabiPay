<?php

namespace Database\Seeders;

use App\Enums\UserRole; // adjust if you use a string enum or consts
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@babipay.com');
        $password = env('ADMIN_PASSWORD', 'change-me-now');

        DB::transaction(function () use ($email, $password) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'id'       => (string) Str::uuid(),
                    'username' => 'admin',
                    'password' => Hash::make($password),
                    'role'     => UserRole::Admin,
                    'phone'    => '+961 00 000 000',
                ]
            );
        });
    }
}
