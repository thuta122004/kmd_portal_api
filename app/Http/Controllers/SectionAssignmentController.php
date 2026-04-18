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
        $query = SectionAssignment::query()->with(['section', 'subject', 'user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->get()->map(function ($item) {
            return [
                'id'           => $item->id,
                'section_name' => $item->section->name ?? null,
                'subject_name' => $item->subject->name ?? null,
                'lecturer_name'    => $item->user->name ?? null,
                'is_primary'   => (bool) $item->is_primary,
                'status'       => $item->status,
                'created_at'   => $item->created_at->format('Y-m-d H:i:s'),
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
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'user_id'    => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role_id', 4);
                }),
                Rule::unique('section_assignments')->where(function ($query) use ($request) {
                    return $query->where('section_id', $request->section_id)
                                ->where('subject_id', $request->subject_id)
                                ->where('user_id', $request->user_id);
                }),
            ],
            'is_primary' => 'boolean'
        ], [
            'user_id.exists' => 'The selected user is invalid or does not have the required permissions.',
            'user_id.unique' => 'This lecturer is already assigned to this subject in this section.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $assignment = SectionAssignment::create([
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'user_id'    => $request->user_id,
            'is_primary' => $request->input('is_primary', true),
            'status'     => 'active',
        ]);

        $assignment->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Lecturer assigned to subject and section successfully',
            'data'    => [
                'assignment' => [
                    'id'         => $assignment->id,
                    'section_name' => $item->section->name ?? null,
                    'subject_name' => $item->subject->name ?? null,
                    'lecturer_name'    => $item->user->name ?? null,
                    'is_primary' => (bool) $assignment->is_primary,
                    'status'     => $assignment->status,
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $assignment = SectionAssignment::with(['section', 'subject', 'user'])->find($id);

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
                    'id'           => $assignment->id,
                    'section_name' => $item->section->name ?? null,
                    'subject_name' => $item->subject->name ?? null,
                    'lecturer_name'    => $item->user->name ?? null,
                    'is_primary'   => (bool) $assignment->is_primary,
                    'status'       => $assignment->status,
                    'created_at'   => $assignment->created_at->format('Y-m-d H:i:s'),
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
            'section_id' => 'exists:sections,id',
            'subject_id' => 'exists:subjects,id',
            'user_id'    => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role_id', 4);
                }),
                Rule::unique('section_assignments')->where(function ($query) use ($request) {
                    return $query->where('section_id', $request->section_id)
                                ->where('subject_id', $request->subject_id)
                                ->where('user_id', $request->user_id);
                }),
            ],
            'is_primary' => 'boolean'
        ], [
            'user_id.exists' => 'The selected user is invalid or does not have the required permissions.',
            'user_id.unique' => 'This lecturer is already assigned to this subject in this section.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $assignment->update($request->only([
            'section_id', 
            'subject_id', 
            'user_id', 
            'is_primary'
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Section Assignment updated successfully',
            'data'    => [
                'assignment' => [
                    'id'         => $assignment->id,
                    'section_name' => $item->section->name ?? null,
                    'subject_name' => $item->subject->name ?? null,
                    'lecturer_name'    => $item->user->name ?? null,
                    'is_primary' => (bool) $assignment->is_primary,
                    'status'     => $assignment->status,
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
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
