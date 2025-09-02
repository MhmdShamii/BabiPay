<?php


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class RequestWallet extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        $userId = Auth::id();

        return [
            'currency_id' => [
                'required',
                'uuid',
                'exists:currencies,id',
                Rule::unique('wallets', 'currency_id')
                    ->where(fn($q) => $q->where('user_id', $userId)),
            ],
        ];
    }
}
