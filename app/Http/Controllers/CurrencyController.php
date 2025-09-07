<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestCurrency;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CurrencyController extends Controller
{
    public function create(RequestCurrency $request): JsonResponse
    {
        try {
            // Validate request data
            $validation = $request->validated();

            // Create currency
            $currency = Currency::create([
                'name' => $validation['name'],
                'code' => $validation['code'],
                'decimal_places' => $validation['decimal_places'],
            ]);

            // Return success response
            return response()->json([
                'message' => 'Currency created successfully.',
                'data' => [
                    'id' => $currency->id,
                    'name' => $currency->name,
                    'code' => $currency->code,
                    'decimal_places' => $currency->decimal_places,
                    'created_at' => $currency->created_at,
                    'updated_at' => $currency->updated_at
                ]
            ], Response::HTTP_CREATED);
        } catch (QueryException $e) {
            // Handle database errors (e.g., duplicate entry, constraint violation)
            Log::error('Database error while creating currency: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request,
            ]);

            // Check if it's a duplicate entry error
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Currency could not be created.',
                    'error' => 'A currency with this code already exists.'
                ], Response::HTTP_CONFLICT);
            }

            return response()->json([
                'message' => 'Currency could not be created due to a database error.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            // Handle any other unexpected errors
            Log::error('Unexpected error while creating currency: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request
            ]);

            return response()->json([
                'message' => 'Currency could not be created.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAll(): JsonResponse
    {
        try {
            // Retrieve all currencies
            $currencies = Currency::all();

            // Check if any currencies exist
            if ($currencies->isEmpty()) {
                return response()->json([
                    'message' => 'No currencies found.',
                    'data' => []
                ], Response::HTTP_OK);
            }

            // Return success response with currencies
            return response()->json([
                'message' => 'Currencies retrieved successfully.',
                'data' => $currencies
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            Log::error('Unexpected error while retrieving currencies: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve currencies.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
