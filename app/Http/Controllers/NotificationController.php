<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        $notifications = Notification::where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        $unreadCount = Notification::where('user_id', $request->user_id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]
        ], 200);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        $unreadCount = Notification::where('user_id', $request->user_id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'unread_count' => $unreadCount
            ]
        ], 200);
    }

    public function markAsRead(Request $request): JsonResponse
    {
        if ($request->has('id')) {
            $notification = Notification::findOrFail($request->id);
            $notification->update(['is_read' => true]);

            $unreadCount = Notification::where('user_id', $notification->user_id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read.',
                'data' => [
                    'notification' => $notification,
                    'unread_count' => $unreadCount
                ]
            ], 200);
        }

        $request->validate([
            'user_id' => 'required|integer'
        ]);

        Notification::where('user_id', $request->user_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
            'data' => [
                'unread_count' => 0
            ]
        ], 200);
    }
}
