<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Exception;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()->with('section');

        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        $announcements = $query->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($announcement) {
                return [
                    'id'           => $announcement->id,
                    'section_id'   => $announcement->section_id,
                    'section_name' => $announcement->section ? $announcement->section->name : 'Global',
                    'title'        => $announcement->title,
                    'content'      => $announcement->content,
                    'banner_url'   => $announcement->banner_photo ? asset('storage/' . $announcement->banner_photo) : null,
                    'is_pinned'    => $announcement->is_pinned,
                    'created_at'   => $announcement->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'status'  => 'success',
            'message' => 'Announcements retrieved successfully',
            'data'    => ['announcements' => $announcements]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'nullable|exists:sections,id',
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'banner'     => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
            'is_pinned'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $path = null;
            if ($request->hasFile('banner')) {
                $path = $request->file('banner')->store('announcements', 'public');
            }

            $announcement = Announcement::create([
                'section_id'   => $request->section_id,
                'title'        => $request->title,
                'content'      => $request->content,
                'banner_photo' => $path,
                'is_pinned'    => $request->input('is_pinned', false),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Announcement created successfully',
                'data'    => ['announcement' => $announcement]
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Server Error.'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $announcement = Announcement::with('section')->find($id);

        if (!$announcement) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'announcement' => [
                    'id'           => $announcement->id,
                    'section_id'   => $announcement->section_id,
                    'title'        => $announcement->title,
                    'content'      => $announcement->content,
                    'banner_url'   => $announcement->banner_photo ? asset('storage/' . $announcement->banner_photo) : null,
                    'is_pinned'    => $announcement->is_pinned,
                    'created_at'   => $announcement->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $announcement = Announcement::find($id);
        if (!$announcement) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);

        $validator = Validator::make($request->all(), [
            'section_id' => 'nullable|exists:sections,id',
            'title'      => 'string|max:255',
            'content'    => 'string',
            'banner'     => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
        ]);

        if ($validator->fails()) return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);

        if ($request->hasFile('banner')) {
            if ($announcement->banner_photo && Storage::disk('public')->exists($announcement->banner_photo)) {
                Storage::disk('public')->delete($announcement->banner_photo);
            }
            $announcement->banner_photo = $request->file('banner')->store('announcements', 'public');
        }

        $announcement->fill($request->only(['section_id', 'title', 'content', 'is_pinned']));
        $announcement->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Announcement updated successfully',
            'data'    => ['announcement' => $announcement]
        ], 200);
    }

    public function togglePinned(Request $request, $id): JsonResponse
    {
        $announcement = Announcement::find($id);
        if (!$announcement) return response()->json(['status' => 'error', 'message' => 'Not found'], 404);

        $validator = Validator::make($request->all(), [
            'is_pinned' => 'required|boolean',
        ]);

        if ($validator->fails()) return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);

        $announcement->is_pinned = $request->input('is_pinned');
        $announcement->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pin status updated successfully',
            'data'    => ['announcement' => $announcement]
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        if ($announcement->banner_photo) {
            Storage::disk('public')->delete($announcement->banner_photo);
        }

        $announcement->delete();

        return response()->json(['status' => 'success', 'message' => 'Announcement deleted successfully'], 200);
    }

    public function getStudentFeed(Request $request)
    {
        $student = $request->user()->student;
        $sectionIds = $student->enrolments()->pluck('section_id')->toArray();

        $announcements = Announcement::with('section')
            ->whereNull('section_id')
            ->orWhereIn('section_id', $sectionIds)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($announcement) {
                return [
                    'id'           => $announcement->id,
                    'section_id'   => $announcement->section_id,
                    'section_name' => $announcement->section ? $announcement->section->name : 'Global',
                    'title'        => $announcement->title,
                    'content'      => $announcement->content,
                    'banner_url'   => $announcement->banner_photo ? asset('storage/' . $announcement->banner_photo) : null,
                    'is_pinned'    => $announcement->is_pinned,
                    'created_at'   => $announcement->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'status'  => 'success',
            'message' => 'Announcements retrieved successfully',
            'data'    => ['announcements' => $announcements]
        ], 200);
    }
}
