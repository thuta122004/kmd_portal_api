<?php

namespace App\Http\Controllers;

use App\Models\AcademicDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Exception;

class AcademicDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AcademicDocument::query()->with('student.user');

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $documents = $query->get()->map(function ($doc) {
            return [
                'id'            => $doc->id,
                'student_id'    => $doc->student_id,
                'student_name'  => $doc->student->user->name,
                'document_type' => $doc->document_type,
                'title'         => $doc->title,
                'file_url'      => asset('storage/' . $doc->file_path),
                'is_verified'   => $doc->is_verified,
                'created_at'    => $doc->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Documents retrieved successfully',
            'data'    => ['documents' => $documents]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id'    => 'required|exists:students,id',
            'document_type' => 'required|string|max:50',
            'title'         => 'required|string|max:255',
            'file'          => 'required|file|mimes:pdf,jpg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $path = $request->file('file')->store('documents', 'public');

            $document = AcademicDocument::create([
                'student_id'    => $request->student_id,
                'document_type' => $request->document_type,
                'title'         => $request->title,
                'file_path'     => $path,
                'is_verified'   => false,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Document uploaded successfully',
                'data'    => ['document' => $document]
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Server Error.'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $doc = AcademicDocument::with('student.user')->find($id);

        if (!$doc) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'document' => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'file_url' => asset('storage/' . $doc->file_path),
                    'is_verified' => $doc->is_verified
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $doc = AcademicDocument::find($id);
        if (!$doc) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);

        $validator = Validator::make($request->all(), [
            'title'       => 'string|max:255',
            'is_verified' => 'boolean',
            'file'        => 'nullable|file|mimes:pdf,jpg,png|max:5120',
        ]);

        if ($validator->fails()) return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($doc->file_path);
            $doc->file_path = $request->file('file')->store('documents', 'public');
        }

        $doc->fill($request->only(['title', 'is_verified']));
        $doc->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Document updated successfully',
            'data'    => ['document' => $doc]
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $doc = AcademicDocument::find($id);

        if (!$doc) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        Storage::disk('public')->delete($doc->file_path);
        $doc->delete();

        return response()->json(['status' => 'success', 'message' => 'Document deleted successfully'], 200);
    }
}