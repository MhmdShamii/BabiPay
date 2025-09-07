<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $users = User::all();

            return response()->json([
                'message' => 'Users retrieved successfully.',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve users: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve users. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function promote(User $user): JsonResponse
    {
        try {
            // Using route model binding, so user is already resolved
            if ($user->role === UserRole::Employee) {
                return response()->json([
                    'message' => 'User is already an employee.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $user->role = UserRole::Employee;
            $user->save();

            return response()->json([
                'message' => 'User promoted to employee successfully.',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to promote user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to promote user. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deactivate(User $user): JsonResponse
    {
        try {
            // Using route model binding, so user is already resolved
            if ($user->status === UserStatus::Deactivated) {
                return response()->json([
                    'message' => 'User is already deactivated.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $user->status = UserStatus::Deactivated;
            $user->save();

            // Delete user tokens
            $user->tokens()->delete();

            return response()->json([
                'message' => 'User deactivated successfully.',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to deactivate user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to deactivate user. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function activate(User $user): JsonResponse
    {
        try {
            // Using route model binding, so user is already resolved
            if ($user->status === UserStatus::Active) {
                return response()->json([
                    'message' => 'User is already activated.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $user->status = UserStatus::Active;
            $user->save();

            return response()->json([
                'message' => 'User activated successfully.',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to activate user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Failed to activate user. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
