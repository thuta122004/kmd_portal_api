<?php

namespace App\Http\Controllers;

use App\Models\SectionSubjectUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class SectionSubjectUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SectionSubjectUser::query()->with(['section', 'subject', 'user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->get()->map(function ($item) {
            return [
                'id'           => $item->id,
                'section_name' => $item->section->name ?? null,
                'subject_name' => $item->subject->name ?? null,
                'user_name'    => $item->user->name ?? null,
                'is_primary'   => (bool) $item->is_primary,
                'status'       => $item->status,
                'created_at'   => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignments retrieved successfully',
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
            'user_id'    => 'required|exists:users,id',
            'is_primary' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $assignment = SectionSubjectUser::create([
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'user_id'    => $request->user_id,
            'is_primary' => $request->input('is_primary', true),
            'status'     => 'active',
        ]);

        $assignment->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'User assigned to subject and section successfully',
            'data'    => [
                'assignment' => [
                    'id'         => $assignment->id,
                    'section_id' => $assignment->section_id,
                    'subject_id' => $assignment->subject_id,
                    'user_id'    => $assignment->user_id,
                    'is_primary' => (bool) $assignment->is_primary,
                    'status'     => $assignment->status,
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $assignment = SectionSubjectUser::with(['section', 'subject', 'user'])->find($id);

        if (!$assignment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignment details retrieved',
            'data'    => [
                'assignment' => [
                    'id'           => $assignment->id,
                    'section_name' => $assignment->section->name,
                    'subject_name' => $assignment->subject->name,
                    'user_name'    => $assignment->user->name,
                    'is_primary'   => (bool) $assignment->is_primary,
                    'status'       => $assignment->status,
                    'created_at'   => $assignment->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $assignment = SectionSubjectUser::find($id);

        if (!$assignment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment not found'
            ], 404);
        }

        $assignment->status = ($assignment->status === 'active') ? 'inactive' : 'active';
        $assignment->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Assignment status updated to {$assignment->status}",
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
