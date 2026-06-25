<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Exception;

class LecturerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lecturer::query()->with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $lecturers = $query->get()->map(function ($lecturer) {
            return [
                'id'            => $lecturer->id,
                'user_id'       => $lecturer->user_id,
                'name'          => $lecturer->user->name,
                'email'         => $lecturer->user->email,
                'employee_id'   => $lecturer->employee_id,
                'department'    => $lecturer->department,
                'qualification' => $lecturer->qualification,
                'status'        => $lecturer->status,
                'created_at'    => $lecturer->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Lecturers retrieved successfully',
            'data'    => [
                'lecturers' => $lecturers
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id'       => 'required|exists:users,id|unique:lecturers,user_id',
            'employee_id'   => 'nullable|string|unique:lecturers,employee_id|max:255',
            'department'    => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
        ], [
            'user_id.unique' => 'A lecturer profile already exists for this user.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $lecturer = Lecturer::create([
                'user_id'       => $request->user_id,
                'employee_id'   => $request->employee_id,
                'department'    => $request->department,
                'qualification' => $request->qualification,
                'status'        => 'inactive',
            ]);

            $lecturer->load('user');

            return response()->json([
                'status'  => 'success',
                'message' => 'Lecturer profile created successfully',
                'data'    => [
                    'lecturer' => [
                        'id'            => $lecturer->id,
                        'user_id'       => $lecturer->user_id,
                        'name'          => $lecturer->user->name,
                        'email'         => $lecturer->user->email,
                        'employee_id'   => $lecturer->employee_id,
                        'department'    => $lecturer->department,
                        'qualification' => $lecturer->qualification,
                        'status'        => $lecturer->status,
                        'created_at'    => $lecturer->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not build lecturer profile.'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $lecturer = Lecturer::with('user')->find($id);

        if (!$lecturer) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Lecturer profile not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Lecturer profile retrieved successfully',
            'data'    => [
                'lecturer' => [
                    'id'            => $lecturer->id,
                    'user_id'       => $lecturer->user_id,
                    'name'          => $lecturer->user->name,
                    'email'         => $lecturer->user->email,
                    'employee_id'   => $lecturer->employee_id,
                    'department'    => $lecturer->department,
                    'qualification' => $lecturer->qualification,
                    'status'        => $lecturer->status,
                    'created_at'    => $lecturer->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $lecturer = Lecturer::find($id);

        if (!$lecturer) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Lecturer profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id'       => 'exists:users,id|unique:lecturers,user_id,' . $id,
            'employee_id'   => 'nullable|string|max:255|unique:lecturers,employee_id,' . $id,
            'department'    => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $lecturer->update($request->only(['user_id', 'employee_id', 'department', 'qualification']));
        $lecturer->load('user');

        return response()->json([
            'status'  => 'success',
            'message' => 'Lecturer profile updated successfully',
            'data'    => [
                'lecturer' => [
                    'id'            => $lecturer->id,
                    'user_id'       => $lecturer->user_id,
                    'name'          => $lecturer->user->name,
                    'email'         => $lecturer->user->email,
                    'employee_id'   => $lecturer->employee_id,
                    'department'    => $lecturer->department,
                    'qualification' => $lecturer->qualification,
                    'status'        => $lecturer->status,
                    'created_at'    => $lecturer->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }
}