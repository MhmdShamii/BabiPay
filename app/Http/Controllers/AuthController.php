<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        $data = $request->validated();

        $data['role'] = UserRole::User;

        $user = User::create($data);

        $token = $user->createToken('api', ['*']);
        $token->accessToken->forceFill([
            'expires_at' => now()->addDays(2),
        ])->save();

        return response()->json([
            'message' => 'Registered successfully.',
            'user'    => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
                'role'     => $user->role,
                'phone'    => $user->phone,
                'created_at' => $user->created_at,
            ],
            'token'   => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
}
