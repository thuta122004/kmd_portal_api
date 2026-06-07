<?php

namespace App\Http\Controllers;

use App\Models\SectionAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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
                'subject_name'  => $item->subject->name,
                'lecturer_name' => $item->lecturer->user->name,
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
            'section_id'  => 'required|exists:sections,id',
            'subject_id'  => 'required|exists:subjects,id',
            'lecturer_id' => [
                'required',
                'exists:lecturers,id',
                Rule::unique('section_assignments')->where(function ($query) use ($request) {
                    return $query->where('section_id', $request->section_id)
                                ->where('subject_id', $request->subject_id)
                                ->where('lecturer_id', $request->lecturer_id);
                }),
            ],
            'is_primary'  => 'boolean'
        ], [
            'lecturer_id.exists' => 'The selected lecturer is invalid.',
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

        $assignment->load(['section', 'subject', 'lecturer.user']);

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

        if (!$assignment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found for this Section'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'section_id'  => 'exists:sections,id',
            'subject_id'  => 'exists:subjects,id',
            'lecturer_id' => [
                'nullable',
                'exists:lecturers,id',
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
            'lecturer_id.exists' => 'The selected lecturer is invalid.',
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

        $assignment->load(['section', 'subject', 'lecturer.user']);

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

        $assignment->status = ($assignment->status === 'active') ? 'inactive' : 'active';
        $assignment->save();

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
}