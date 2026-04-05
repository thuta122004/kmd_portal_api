<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class SectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Section::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sections = $query->get()->map(function ($section) {
            return [
                'id'         => $section->id,
                'name'       => $section->name,
                'code'       => $section->code,
                'start_date' => $section->start_date,
                'end_date'   => $section->end_date,
                'status'     => $section->status,
                'created_at' => $section->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Sections retrieved successfully',
            'data'    => [
                'sections' => $sections
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|unique:sections,code',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $section = Section::create([
            'name'       => $request->name,
            'code'       => $request->code,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'status'     => 'pending',
        ]);

        $section->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Section created successfully',
            'data'    => [
                'section' => [
                    'id'         => $section->id,
                    'name'       => $section->name,
                    'code'       => $section->code,
                    'start_date' => $section->start_date,
                    'end_date'   => $section->end_date,
                    'status'     => $section->status,
                    'created_at' => $section->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $section = Section::find($id);

        if (!$section) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Section not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success', 
            'message' => 'Section retrieved successfully',
            'data'    => [
                'section' => [
                    'id'         => $section->id,
                    'name'       => $section->name,
                    'code'       => $section->code,
                    'start_date' => $section->start_date,
                    'end_date'   => $section->end_date,
                    'status'     => $section->status,
                    'created_at' => $section->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $section = Section::find($id);

        if (!$section) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Section not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'string|max:255',
            'code'       => 'string|unique:sections,code,' . $id,
            'start_date' => 'date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $section->update($request->only(['name', 'code', 'start_date', 'end_date']));

        return response()->json([
            'status'  => 'success',
            'message' => 'Section updated successfully',
            'data'    => [
                'section' => [
                    'id'         => $section->id,
                    'name'       => $section->name,
                    'code'       => $section->code,
                    'start_date' => $section->start_date,
                    'end_date'   => $section->end_date,
                    'status'     => $section->status,
                    'created_at' => $section->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $section = Section::find($id);

        if (!$section) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Section not found'
            ], 404);
        }

        /**
         * If pending -> active
         * If active -> inactive
         * If inactive -> active
         */
        if ($section->status === 'pending' || $section->status === 'inactive') {
            $section->status = 'active';
        } else {
            $section->status = 'inactive';
        }
        
        $section->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Section status updated to {$section->status}",
            'data'    => [
                'section' => [
                    'id'         => $section->id,
                    'name'       => $section->name,
                    'code'       => $section->code,
                    'status'     => $section->status,
                    'created_at' => $section->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }
}
