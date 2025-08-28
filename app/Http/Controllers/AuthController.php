<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
            'expieres_at' => $token->accessToken->expires_at,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(LoginUserRequest $request)
    {

        //validate
        $data = $request->validated();

        //grabe the validated data
        $identifier = $data['identifier'];
        $password   = $data['password'];

        //search fir user by email or username
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        //check if the password maches the password of the found users
        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        //delete old user access token
        $user->tokens()->delete();

        //generatge new token
        $newtoken = $user->createToken('api', ['*']);
        $newtoken->accessToken->forceFill([
            'expires_at' => now()->addDays(2),
        ])->save();

        //return the loged in user
        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'email'     => $user->email,
                'role'      => $user->role,
                'phone'     => $user->phone,
            ],
            'token' => $newtoken->plainTextToken,
            'expieres_at' => $newtoken->accessToken->expires_at,
            'token_type' => 'Bearer',
        ]);
    }
}
