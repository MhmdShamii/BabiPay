<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RequestP2P extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sender_wallet_id' => 'required|exists:wallets,id',
            'receiverIdentifier' => 'required|exists:users,username',
            'description' => 'nullable|string|max:255',
            'amount'    => 'required|numeric|min:0.01',
        ];
    }
}
