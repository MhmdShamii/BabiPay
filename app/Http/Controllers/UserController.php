<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();

        return response()->json($users);
    }

    public function promote(User $user)
    {
        $promotedUser = User::where('id', $user->id)->first();
        if (!$promotedUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($promotedUser->role === UserRole::Employee) {
            return response()->json(['message' => 'User is already an employee.'], 400);
        }

        $promotedUser->role = UserRole::Employee;
        $promotedUser->save();

        return response()->json(['message' => 'User promoted to employee successfully.']);
    }

    public function deactivate(User $user)
    {
        $deactivatedUser = User::where('id', $user->id)->first();
        if (!$deactivatedUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($deactivatedUser->status === UserStatus::Deactivated) {
            return response()->json(['message' => 'User is already deactivated.'], 400);
        }

        $deactivatedUser->status = UserStatus::Deactivated;
        $deactivatedUser->save();

        $deactivatedUser->tokens()?->delete();

        return response()->json(['message' => 'User deactivated successfully.', 'user' => $deactivatedUser]);
    }

    public function activate(User $user)
    {
        $activatedUser = User::where('id', $user->id)->first();
        if (!$activatedUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($activatedUser->status === UserStatus::Active) {
            return response()->json(['message' => 'User is already activated.'], 400);
        }

        $activatedUser->status = UserStatus::Active;
        $activatedUser->save();

        return response()->json(['message' => 'User activated successfully.', 'user' => $activatedUser]);
    }
}
