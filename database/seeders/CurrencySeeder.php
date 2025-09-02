<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::firstOrCreate(
            ['code' => 'USD'],
            [
                'id'       => (string) Str::uuid(),
                'code'     => 'USD',
                'name'     => 'United States Dollar',
            ]
        );
    }
}
