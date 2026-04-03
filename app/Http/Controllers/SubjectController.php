<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subject::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $subjects = $query->get()->map(function ($subject) {
            return [
                'id'         => $subject->id,
                'name'       => $subject->name,
                'code'       => $subject->code,
                'status'     => $subject->status,
                'created_at' => $subject->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Subjects retrieved successfully',
            'data'    => [
                'subjects' => $subjects
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|alpha|string|unique:subjects,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $subject = Subject::create([
            'name'   => $request->name,
            'code'   => $request->code,
            'status' => 'active', 
        ]);

        $subject->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Subject created successfully',
            'data'    => [
                'subject' => [
                    'id'         => $subject->id,
                    'name'       => $subject->name,
                    'code'       => $subject->code,
                    'status'     => $subject->status,
                    'created_at' => $subject->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Subject not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success', 
            'message' => 'Subject retrieved successfully',
            'data'    => [
                'subject' => [
                    'id'         => $subject->id,
                    'name'       => $subject->name,
                    'code'       => $subject->code,
                    'status'     => $subject->status,
                    'created_at' => $subject->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Subject not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'code' => 'string|alpha|unique:subjects,code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $subject->update($request->only(['name', 'code']));

        return response()->json([
            'status'  => 'success',
            'message' => 'Subject updated successfully',
            'data'    => [
                'subject' => [
                    'id'         => $subject->id,
                    'name'       => $subject->name,
                    'code'       => $subject->code,
                    'status'     => $subject->status,
                    'created_at' => $subject->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Subject not found'
            ], 404);
        }

        $subject->status = ($subject->status === 'active') ? 'inactive' : 'active';
        $subject->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Subject status updated to {$subject->status}",
            'data'    => [
                'subject' => [
                    'id'         => $subject->id,
                    'name'       => $subject->name,
                    'code'       => $subject->code,
                    'status'     => $subject->status,
                    'created_at' => $subject->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }
}
