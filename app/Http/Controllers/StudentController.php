<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Exception;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Student::query()->with(['user', 'guardians.user', 'enrolments.section']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->get()->map(function ($student) {
            return [
                'id'                 => $student->id,
                'user_id'            => $student->user_id,
                'name'               => $student->user?->name,
                'email'              => $student->user?->email,
                'student_reg_number' => $student->student_reg_number,
                'date_of_birth'      => $student->date_of_birth,
                'gender'             => $student->gender,
                'phone'              => $student->phone,
                'address'            => $student->address,
                'status'             => $student->status,
                'guardians'          => $student->guardians->map(function ($guardian) {
                    return [
                        'id'                 => $guardian->id,
                        'name'               => $guardian->user?->name,
                        'phone'              => $guardian->phone,
                        'relationship_type'  => $guardian->pivot->relationship_type,
                        'is_primary_contact' => (bool) ($guardian->pivot->is_primary_contact ?? false),
                    ];
                }),
                'enrolment_history'  => $student->enrolments->map(function ($e) {
                    return [
                        'section_name' => $e->section?->name,
                        'section_code' => $e->section?->code,
                        'status'       => $e->status,
                        'enrolled_at'  => $e->created_at?->format('Y-m-d'),
                    ];
                }),
                'created_at'         => $student->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Students retrieved successfully',
            'data'    => [
                'students' => $students
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id'            => 'required|exists:users,id|unique:students,user_id',
            'student_reg_number' => 'required|string|unique:students,student_reg_number|max:255',
            'date_of_birth'      => 'required|date',
            'gender'             => 'required|in:Male,Female',
            'phone'              => 'nullable|string|max:50',
            'address'            => 'nullable|string',
        ], [
            'user_id.unique'            => 'A student profile already exists for this user.',
            'student_reg_number.unique' => 'This registration number has already been taken.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $student = Student::create([
                'user_id'            => $request->user_id,
                'student_reg_number' => $request->student_reg_number,
                'date_of_birth'      => $request->date_of_birth,
                'gender'             => $request->gender,
                'phone'              => $request->phone,
                'address'            => $request->address,
                'status'             => 'inactive',
            ]);

            $student->load(['user', 'guardians.user']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Student profile created successfully',
                'data'    => [
                    'student' => [
                        'id'                 => $student->id,
                        'user_id'            => $student->user_id,
                        'name'               => $student->user?->name,
                        'email'              => $student->user?->email,
                        'student_reg_number' => $student->student_reg_number,
                        'date_of_birth'      => $student->date_of_birth,
                        'gender'             => $student->gender,
                        'phone'              => $student->phone,
                        'address'            => $student->address,
                        'status'             => $student->status,
                        'guardians'          => [],
                        'created_at'         => $student->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not build student profile.',
                'error_details' => $e->getMessage(),
                'error_file'    => $e->getFile(),
                'error_line'    => $e->getLine()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $student = Student::with(['user', 'guardians.user', 'enrolments.section'])->find($id);

        if (!$student) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Student profile not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Student profile retrieved successfully',
            'data'    => [
                'student' => [
                    'id'                 => $student->id,
                    'user_id'            => $student->user_id,
                    'name'               => $student->user?->name,
                    'email'              => $student->user?->email,
                    'student_reg_number' => $student->student_reg_number,
                    'date_of_birth'      => $student->date_of_birth,
                    'gender'             => $student->gender,
                    'phone'              => $student->phone,
                    'address'            => $student->address,
                    'status'             => $student->status,
                    'guardians'          => $student->guardians->map(function ($guardian) {
                        return [
                            'id'                 => $guardian->id,
                            'name'               => $guardian->user?->name,
                            'phone'              => $guardian->phone,
                            'relationship_type'  => $guardian->pivot->relationship_type,
                            'is_primary_contact' => (bool) ($guardian->pivot->is_primary_contact ?? false),
                        ];
                    }),
                    'enrolment_history'  => $student->enrolments->map(function ($e) {
                        return [
                            'section'     => $e->section?->name,
                            'status'      => $e->status,
                            'enrolled_at' => $e->created_at?->format('Y-m-d'),
                        ];
                    }),
                    'created_at'         => $student->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Student profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id'            => 'exists:users,id|unique:students,user_id,' . $id,
            'student_reg_number' => 'string|max:255|unique:students,student_reg_number,' . $id,
            'date_of_birth'      => 'date',
            'gender'             => 'in:Male,Female',
            'phone'              => 'nullable|string|max:50',
            'address'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $student->update($request->only([
            'user_id', 
            'student_reg_number', 
            'date_of_birth', 
            'gender', 
            'phone', 
            'address'
        ]));
        
        $student->load(['user', 'guardians.user']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Student profile updated successfully',
            'data'    => [
                'student' => [
                    'id'                 => $student->id,
                    'user_id'            => $student->user_id,
                    'name'               => $student->user?->name,
                    'email'              => $student->user?->email,
                    'student_reg_number' => $student->student_reg_number,
                    'date_of_birth'      => $student->date_of_birth,
                    'gender'             => $student->gender,
                    'phone'              => $student->phone,
                    'address'            => $student->address,
                    'status'             => $student->status,
                    'guardians'          => $student->guardians->map(function ($guardian) {
                        return [
                            'id'                 => $guardian->id,
                            'name'               => $guardian->user?->name,
                            'phone'              => $guardian->phone,
                            'relationship_type'  => $guardian->pivot->relationship_type,
                            'is_primary_contact' => (bool) ($guardian->pivot->is_primary_contact ?? false),
                        ];
                    }),
                    'created_at'         => $student->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }
}