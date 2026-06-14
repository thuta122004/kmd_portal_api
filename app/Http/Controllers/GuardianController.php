<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Exception;

class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Guardian::query()->with(['user', 'students.user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $guardians = $query->get()->map(function ($guardian) {
            return [
                'id'         => $guardian->id,
                'user_id'    => $guardian->user_id,
                'name'       => $guardian->user?->name,
                'email'      => $guardian->user?->email,
                'phone'      => $guardian->phone,
                'occupation' => $guardian->occupation,
                'address'    => $guardian->address,
                'status'     => $guardian->status,
                'students'   => $guardian->students->map(function ($student) {
                    return [
                        'id'                 => $student->id,
                        'name'               => $student->user?->name,
                        'student_reg_number' => $student->student_reg_number,
                        'relationship_type'  => $student->pivot->relationship_type,
                        'is_primary_contact' => (bool) ($student->pivot->is_primary_contact ?? false),
                    ];
                }),
                'created_at' => $guardian->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Guardians retrieved successfully',
            'data'    => [
                'guardians' => $guardians
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id|unique:guardians,user_id',
            'phone'      => 'required|string|max:50',
            'occupation' => 'nullable|string|max:255',
            'address'    => 'nullable|string',
        ], [
            'user_id.unique' => 'A guardian profile already exists for this user.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $guardian = Guardian::create([
                'user_id'    => $request->user_id,
                'phone'      => $request->phone,
                'occupation' => $request->occupation,
                'address'    => $request->address,
                'status'     => 'active',
            ]);

            $guardian->load(['user', 'students.user']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Guardian profile created successfully',
                'data'    => [
                    'guardian' => [
                        'id'         => $guardian->id,
                        'user_id'    => $guardian->user_id,
                        'name'       => $guardian->user?->name,
                        'email'      => $guardian->user?->email,
                        'phone'      => $guardian->phone,
                        'occupation' => $guardian->occupation,
                        'address'    => $guardian->address,
                        'status'     => $guardian->status,
                        'students'   => [],
                        'created_at' => $guardian->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not build guardian profile.'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $guardian = Guardian::with(['user', 'students.user'])->find($id);

        if (!$guardian) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Guardian profile not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Guardian profile retrieved successfully',
            'data'    => [
                'guardian' => [
                    'id'         => $guardian->id,
                    'user_id'    => $guardian->user_id,
                    'name'       => $guardian->user?->name,
                    'email'      => $guardian->user?->email,
                    'phone'      => $guardian->phone,
                    'occupation' => $guardian->occupation,
                    'address'    => $guardian->address,
                    'status'     => $guardian->status,
                    'students'   => $guardian->students->map(function ($student) {
                        return [
                            'id'                 => $student->id,
                            'name'               => $student->user?->name,
                            'student_reg_number' => $student->student_reg_number,
                            'relationship_type'  => $student->pivot->relationship_type,
                            'is_primary_contact' => (bool) ($student->pivot->is_primary_contact ?? false),
                        ];
                    }),
                    'created_at' => $guardian->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $guardian = Guardian::find($id);

        if (!$guardian) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Guardian profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id'    => 'exists:users,id|unique:guardians,user_id,' . $id,
            'phone'      => 'string|max:50',
            'occupation' => 'nullable|string|max:255',
            'address'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $guardian->update($request->only(['user_id', 'phone', 'occupation', 'address']));
        $guardian->load(['user', 'students.user']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Guardian profile updated successfully',
            'data'    => [
                'guardian' => [
                    'id'         => $guardian->id,
                    'user_id'    => $guardian->user_id,
                    'name'       => $guardian->user?->name,
                    'email'      => $guardian->user?->email,
                    'phone'      => $guardian->phone,
                    'occupation' => $guardian->occupation,
                    'address'    => $guardian->address,
                    'status'     => $guardian->status,
                    'students'   => $guardian->students->map(function ($student) {
                        return [
                            'id'                 => $student->id,
                            'name'               => $student->user?->name,
                            'student_reg_number' => $student->student_reg_number,
                            'relationship_type'  => $student->pivot->relationship_type,
                            'is_primary_contact' => (bool) ($student->pivot->is_primary_contact ?? false),
                        ];
                    }),
                    'created_at' => $guardian->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $guardian = Guardian::find($id);

        if (!$guardian) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Guardian profile not found'
            ], 404);
        }

        $guardian->status = ($guardian->status === 'active') ? 'inactive' : 'active';
        $guardian->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Guardian status updated to {$guardian->status}",
            'data'    => [
                'guardian' => [
                    'id'         => $guardian->id,
                    'status'     => $guardian->status,
                    'updated_at' => $guardian->updated_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function attachStudent(Request $request, $guardianId): JsonResponse
    {
        $guardian = Guardian::findOrFail($guardianId);
        
        $validator = Validator::make($request->all(), [
            'student_id'         => 'required|exists:students,id',
            'relationship_type'  => 'required|string|max:255',
            'is_primary_contact' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $guardian->students()->syncWithoutDetaching([
            $request->student_id => [
                'relationship_type'  => $request->relationship_type,
                'is_primary_contact' => $request->is_primary_contact,
            ]
        ]);

        return response()->json(['status' => 'success', 'message' => 'Student linked successfully.']);
    }
}