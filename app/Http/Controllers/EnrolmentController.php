<?php

namespace App\Http\Controllers;

use App\Models\Enrolment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\Notification;
use Exception;

class EnrolmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Enrolment::query()->with(['student.user', 'section']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $enrolments = $query->get()->map(function ($item) {
            return [
                'id'                 => $item->id,
                'student_id'         => $item->student_id,
                'student_name'       => $item->student->user->name,
                'student_reg_number' => $item->student->student_reg_number,
                'section_id'         => $item->section_id,
                'section_name'       => $item->section->name,
                'section_code'       => $item->section->code,
                'note'               => $item->note,
                'status'             => $item->status,
                'created_at'         => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Enrolments retrieved successfully',
            'data'    => [
                'enrolments' => $enrolments
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'section_id' => 'required|exists:sections,id',
            'note'       => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            $exists = Enrolment::where('student_id', $request->student_id)
                ->where('section_id', $request->section_id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('section_id', 'This student is already enrolled in this section.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $enrolment = Enrolment::create([
                'student_id' => $request->student_id,
                'section_id' => $request->section_id,
                'note'       => $request->note,
                'status'     => 'active',
            ]);

            $this->syncStudentProfileStatus($enrolment->student_id);

            $enrolment->load(['student.user', 'section']);

            Notification::create([
                'user_id' => $enrolment->student->user->id,
                'title'   => 'New Section Enrolment',
                'content' => "You have been successfully enrolled into section {$enrolment->section->name}.",
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Student enrolled into section successfully',
                'data'    => [
                    'enrolment' => [
                        'id'                 => $enrolment->id,
                        'student_id'         => $enrolment->student_id,
                        'student_name'       => $enrolment->student->user->name,
                        'student_reg_number' => $enrolment->student->student_reg_number,
                        'section_id'         => $enrolment->section_id,
                        'section_name'       => $enrolment->section->name,
                        'note'               => $enrolment->note,
                        'status'             => $enrolment->status,
                        'created_at'         => $enrolment->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not process enrolment entry.'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $enrolment = Enrolment::with(['student.user', 'section'])->find($id);

        if (!$enrolment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Enrolment record not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Enrolment details retrieved',
            'data'    => [
                'enrolment' => [
                    'id'                 => $enrolment->id,
                    'student_id'         => $enrolment->student_id,
                    'student_name'       => $enrolment->student->user->name,
                    'student_reg_number' => $enrolment->student->student_reg_number,
                    'section_id'         => $enrolment->section_id,
                    'section_name'       => $enrolment->section->name,
                    'note'               => $enrolment->note,
                    'status'             => $enrolment->status,
                    'created_at'         => $enrolment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $enrolment = Enrolment::find($id);

        if (!$enrolment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Enrolment record not found'
            ], 404);
        }

        $oldStudentId = $enrolment->student_id;

        $validator = Validator::make($request->all(), [
            'student_id' => 'nullable|exists:students,id',
            'section_id' => 'nullable|exists:sections,id',
            'note'       => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $enrolment) {
            if ($request->has('student_id') || $request->has('section_id')) {
                $studentId = $request->input('student_id', $enrolment->student_id);
                $sectionId = $request->input('section_id', $enrolment->section_id);

                $duplicateExists = Enrolment::where('student_id', $studentId)
                    ->where('section_id', $sectionId)
                    ->where('id', '!=', $enrolment->id)
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add('section_id', 'This student is already enrolled in this section.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $enrolment->update($request->only(['student_id', 'section_id', 'note']));
        
        if ($request->has('student_id') && $oldStudentId != $request->student_id) {
            $this->syncStudentProfileStatus($oldStudentId);
        }
        $this->syncStudentProfileStatus($enrolment->student_id);

        $enrolment->load(['student.user', 'section']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Enrolment record updated successfully',
            'data'    => [
                'enrolment' => [
                    'id'                 => $enrolment->id,
                    'student_id'         => $enrolment->student_id,
                    'student_name'       => $enrolment->student->user->name,
                    'student_reg_number' => $enrolment->student->student_reg_number,
                    'section_id'         => $enrolment->section_id,
                    'section_name'       => $enrolment->section->name,
                    'note'               => $enrolment->note,
                    'status'             => $enrolment->status,
                    'created_at'         => $enrolment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $enrolment = Enrolment::find($id);

        if (!$enrolment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Enrolment record not found'
            ], 404);
        }
        
        if ($enrolment->status === 'active') {
            $enrolment->status = 'inactive';
        } elseif ($enrolment->status === 'inactive') {
            $enrolment->status = 'suspended';
        } else {
            $enrolment->status = 'active';
        }
        
        $enrolment->save();

        $this->syncStudentProfileStatus($enrolment->student_id);

        $enrolment->load(['student.user', 'section']);

        Notification::create([
            'user_id' => $enrolment->student->user->id,
            'title'   => 'Enrolment Status Updated',
            'content' => "Your enrolment status for section {$enrolment->section->name} has been changed to {$enrolment->status}.",
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Enrolment status updated to {$enrolment->status}",
            'data'    => [
                'enrolment' => [
                    'id'         => $enrolment->id,
                    'status'     => $enrolment->status,
                    'updated_at' => $enrolment->updated_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    private function syncStudentProfileStatus($studentId): void
    {
        $student = Student::with('user')->find($studentId);
        if (!$student) return;

        $hasActiveEnrolments = Enrolment::where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();

        $newStatus = $hasActiveEnrolments ? 'active' : 'inactive';

        $student->status = $newStatus;
        $student->save();

        if ($student->user) {
            $student->user->status = $newStatus;
            $student->user->save();
        }
    }
}