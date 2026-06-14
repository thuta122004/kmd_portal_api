<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;
use Exception;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('role');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->get()->map(function ($user) {
            return [
                'id'         => $user->id,
                'role_id'    => $user->role_id,
                'role_name'  => $user->role->name,
                'name'       => $user->name,
                'email'      => $user->email,
                'status'     => $user->status,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Users retrieved successfully',
            'data'    => [
                'users' => $users
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users|kmd_email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id'  => 'required|exists:roles,id',
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
                'role_id'  => $request->role_id,
                'status'   => 'active',
            ]);

            $user->load('role');

            return response()->json([
                'status'  => 'success',
                'message' => 'User created successfully',
                'data'    => [
                    'user' => [
                        'id'         => $user->id,
                        'role_id'    => $user->role_id,
                        'role_name'  => $user->role->name,
                        'name'       => $user->name,
                        'email'      => $user->email,
                        'status'     => $user->status,
                        'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not create user.'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'User retrieved successfully',
            'data'    => [
                'user' => [
                    'id'         => $user->id,
                    'role_id'    => $user->role_id,
                    'role_name'  => $user->role->name,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'status'     => $user->status,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'string|max:255',
            'email'   => 'string|email|max:255|kmd_email|unique:users,email,' . $id,
            'role_id' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'email', 'role_id']));
        $user->load('role');

        return response()->json([
            'status'  => 'success',
            'message' => 'User updated successfully',
            'data'    => [
                'user' => [
                    'id'         => $user->id,
                    'role_id'    => $user->role_id,
                    'role_name'  => $user->role->name,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'status'     => $user->status,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function updatePassword(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Password updated successfully'
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $user->status = ($user->status === 'active') ? 'inactive' : 'active';
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => "User status updated to {$user->status}",
            'data'    => [
                'user' => [
                    'id'         => $user->id,
                    'status'     => $user->status,
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }
}