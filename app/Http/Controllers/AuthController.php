<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;
use Exception;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users|kmd_email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422); 
        }

        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('register_token')->plainTextToken;

            return response()->json([
                'status'  => 'success',
                'message' => 'User registered successfully',
                'data'    => [
                    'user'  => $user,
                    'token' => $token
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not register user'
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|kmd_email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User account not found'
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $token = $user->createToken('login_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged in successfully',
            'data'    => [
                'user'  => $user,
                'token' => $token
            ]
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || !$user->currentAccessToken()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Active session or token not found'
                ], 404);
            }

            $user->currentAccessToken()->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Logged out successfully. Token revoked.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'An unexpected error occurred during logout'
            ], 500);
        }
    }
}
