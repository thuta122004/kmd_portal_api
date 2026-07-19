<?php

namespace App\Http\Controllers;

use App\Models\SectionAssignment;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;

class SectionAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SectionAssignment::query()->with(['section', 'subject', 'lecturer.user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->get()->map(function ($item) {
            return [
                'id'            => $item->id,
                'section_name'  => $item->section->name,
                'section_id'    => $item->section->id,
                'section_code'  => $item->section->code,
                'subject_name'  => $item->subject->name,
                'subject_id'    => $item->subject->id,
                'subject_code'  => $item->subject->code,
                'lecturer_name' => $item->lecturer->user->name,
                'lecturer_email' => $item->lecturer->user->email,
                'lecturer_id'   => $item->lecturer->id,
                'is_primary'    => (bool) $item->is_primary,
                'status'        => $item->status,
                'created_at'    => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Section Assignments retrieved successfully',
            'data'    => [
                'assignments' => $assignments
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'section_id'  => [
                'required',
                Rule::exists('sections', 'id')->where('status', 'active')
            ],
            'subject_id'  => [
                'required',
                Rule::exists('subjects', 'id')->where('status', 'active')
            ],
            'lecturer_id' => [
                'required',
                Rule::exists('lecturers', 'id'),
                Rule::unique('section_assignments')->where(function ($query) use ($request) {
                    return $query->where('section_id', $request->section_id)
                                ->where('subject_id', $request->subject_id)
                                ->where('lecturer_id', $request->lecturer_id);
                }),
            ],
            'is_primary'  => 'boolean'
        ], [
            'section_id.exists'  => 'The selected section is inactive or invalid.',
            'subject_id.exists'  => 'The selected subject is inactive or invalid.',
            'lecturer_id.unique' => 'This lecturer is already assigned to this subject in this section.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        if ($request->input('is_primary', true)) {
            $primaryExists = SectionAssignment::where('section_id', $request->section_id)
                ->where('subject_id', $request->subject_id)
                ->where('is_primary', true)
                ->where('status', 'active')
                ->exists();

            if ($primaryExists) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => [
                        'is_primary' => ['A primary lecturer has already been assigned to this subject in this section.']
                    ]
                ], 422);
            }
        }

        $assignment = SectionAssignment::create([
            'section_id'  => $request->section_id,
            'subject_id'  => $request->subject_id,
            'lecturer_id' => $request->lecturer_id,
            'is_primary'  => $request->input('is_primary', true),
            'status'      => 'active',
        ]);

        $this->syncLecturerProfileStatus($assignment->lecturer_id);

        $assignment->load(['section', 'subject', 'lecturer.user']);

        Notification::create([
            'user_id' => $assignment->lecturer->user->id,
            'title'   => 'New Section Assignment',
            'content' => "You have been assigned to teach {$assignment->subject->name} for section {$assignment->section->name}.",
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Lecturer assigned to subject and section successfully',
            'data'    => [
                'assignment' => [
                    'id'            => $assignment->id,
                    'section_name'  => $assignment->section->name,
                    'subject_name'  => $assignment->subject->name,
                    'lecturer_name' => $assignment->lecturer->user->name,
                    'is_primary'    => (bool) $assignment->is_primary,
                    'status'        => $assignment->status,
                    'created_at'    => $assignment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $assignment = SectionAssignment::with(['section', 'subject', 'lecturer.user'])->find($id);

        if (!$assignment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found for this Section'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Section Assignment details retrieved',
            'data'    => [
                'assignment' => [
                    'id'            => $assignment->id,
                    'section_name'  => $assignment->section->name,
                    'subject_name'  => $assignment->subject->name,
                    'lecturer_name' => $assignment->lecturer->user->name,
                    'is_primary'    => (bool) $assignment->is_primary,
                    'status'        => $assignment->status,
                    'created_at'    => $assignment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $assignment = SectionAssignment::find($id);
        $oldLecturer = $assignment->lecturer;

        if (!$assignment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found for this Section'
            ], 404);
        }

        $oldLecturerId = $assignment->lecturer_id;

        $validator = Validator::make($request->all(), [
            'section_id'  => [
                'nullable',
                Rule::exists('sections', 'id')->where(function ($query) use ($assignment) {
                    $query->where('status', 'active')->orWhere('id', $assignment->section_id);
                })
            ],
            'subject_id'  => [
                'nullable',
                Rule::exists('subjects', 'id')->where(function ($query) use ($assignment) {
                    $query->where('status', 'active')->orWhere('id', $assignment->subject_id);
                })
            ],
            'lecturer_id' => [
                'nullable',
                Rule::exists('lecturers', 'id'),
                Rule::unique('section_assignments')->where(function ($query) use ($request, $assignment) {
                    $sectionId = $request->input('section_id', $assignment->section_id);
                    $subjectId = $request->input('subject_id', $assignment->subject_id);
                    $lecturerId = $request->input('lecturer_id', $assignment->lecturer_id);

                    return $query->where('section_id', $sectionId)
                                ->where('subject_id', $subjectId)
                                ->where('lecturer_id', $lecturerId)
                                ->where('id', '!=', $assignment->id);
                }),
            ],
            'is_primary'  => 'boolean'
        ], [
            'section_id.exists'  => 'The selected section is inactive or invalid.',
            'subject_id.exists'  => 'The selected subject is inactive or invalid.',
            'lecturer_id.unique' => 'This lecturer is already assigned to this subject in this section.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $sectionId = $request->input('section_id', $assignment->section_id);
        $subjectId = $request->input('subject_id', $assignment->subject_id);
        $isPrimary = $request->input('is_primary', $assignment->is_primary);

        if ($isPrimary) {
            $primaryExists = SectionAssignment::where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->where('is_primary', true)
                ->where('status', 'active')
                ->where('id', '!=', $id)
                ->exists();

            if ($primaryExists) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => [
                        'is_primary' => ['A primary lecturer has already been assigned to this subject in this section.']
                    ]
                ], 422);
            }
        }

        $assignment->update($request->only([
            'section_id', 
            'subject_id', 
            'lecturer_id', 
            'is_primary'
        ]));

        if ($request->has('lecturer_id') && $oldLecturerId != $request->lecturer_id) {
            $this->syncLecturerProfileStatus($oldLecturerId);
        }
        $this->syncLecturerProfileStatus($assignment->lecturer_id);

        $assignment->load(['section', 'subject', 'lecturer.user']);

        if ($request->has('lecturer_id') && $oldLecturer->id != $assignment->lecturer_id) {
            Notification::create([
                'user_id' => $oldLecturer->user->id,
                'title'   => 'Section Assignment Removed',
                'content' => "You are no longer assigned to teach {$assignment->subject->name} for section {$assignment->section->name}.",
            ]);

            Notification::create([
                'user_id' => $assignment->lecturer->user->id,
                'title'   => 'New Section Assignment',
                'content' => "You have been assigned to teach {$assignment->subject->name} for section {$assignment->section->name}.",
            ]);
        } else {
            Notification::create([
                'user_id' => $assignment->lecturer->user->id,
                'title'   => 'Section Assignment Updated',
                'content' => "Your teaching assignment details for {$assignment->subject->name} in section {$assignment->section->name} have been updated.",
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Section Assignment updated successfully',
            'data'    => [
                'assignment' => [
                    'id'            => $assignment->id,
                    'section_name'  => $assignment->section->name,
                    'subject_name'  => $assignment->subject->name,
                    'lecturer_name' => $assignment->lecturer->user->name,
                    'is_primary'    => (bool) $assignment->is_primary,
                    'status'        => $assignment->status,
                    'created_at'    => $assignment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $assignment = SectionAssignment::find($id);

        if (!$assignment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found for this Section'
            ], 404);
        }

        $targetStatus = ($assignment->status === 'active') ? 'inactive' : 'active';

        if ($targetStatus === 'active' && $assignment->is_primary) {
            $primaryExists = SectionAssignment::where('section_id', $assignment->section_id)
                ->where('subject_id', $assignment->subject_id)
                ->where('is_primary', true)
                ->where('status', 'active')
                ->where('id', '!=', $id)
                ->exists();

            if ($primaryExists) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Cannot activate: A primary lecturer has already been assigned to this subject in this section.'
                ], 422);
            }
        }

        $assignment->status = $targetStatus;
        $assignment->save();

        $this->syncLecturerProfileStatus($assignment->lecturer_id);

        $assignment->load(['section', 'subject', 'lecturer.user']);

        Notification::create([
            'user_id' => $assignment->lecturer->user->id,
            'title'   => 'Assignment Status Changed',
            'content' => "Your assignment for {$assignment->subject->name} in section {$assignment->section->name} is now {$assignment->status}.",
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Section Assignment status updated to {$assignment->status}",
            'data'    => [
                'assignment' => [
                    'id'         => $assignment->id,
                    'status'     => $assignment->status,
                    'updated_at' => $assignment->updated_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    private function syncLecturerProfileStatus($lecturerId): void
    {
        $lecturer = Lecturer::with('user')->find($lecturerId);
        if (!$lecturer) return;

        $hasActiveAssignments = SectionAssignment::where('lecturer_id', $lecturerId)
            ->where('status', 'active')
            ->exists();

        $newStatus = $hasActiveAssignments ? 'active' : 'inactive';

        DB::transaction(function () use ($lecturer, $newStatus) {
            $lecturer->status = $newStatus;
            $lecturer->save();

            if ($lecturer->user) {
                $user = $lecturer->user->fresh(); 
                $user->status = $newStatus;
                $user->save();
            }
        });
    }
}