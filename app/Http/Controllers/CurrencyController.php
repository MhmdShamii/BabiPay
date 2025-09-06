<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestCurrency;
use App\Models\Currency;

class CurrencyController extends Controller
{
    public function create(RequestCurrency $request)
    {
        $validation = $request->validated();

        $currency = Currency::create([
            'name' => $validation['name'],
            'code' => $validation['code'],
            'decimal_places' => $validation['decimal_places'],

        ]);

        return response()->json([
            'id' => $currency['id'],
            'name' => $currency['name'],
            'code' => $currency['code'],
            'decimal_places' => $currency['decimal_places'],
            'created_at' => $currency['created_at'],
            'updated_at' => $currency['updated_at']
        ]);
    }
    public function getAll()
    {
        return response()->json(Currency::get());
    }
}
