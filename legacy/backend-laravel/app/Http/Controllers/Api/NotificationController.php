<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Common Platform Layer — cross-module notifications shown in the app shell.
 * Any module can push a notification (module_source = bethmi|pasindu|kavishka|
 * jithmi|common); the shell lists and clears them.
 */
class NotificationController extends Controller
{
    /** GET /api/notifications?unread=1 */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)->orderByDesc('created_at');

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        return response()->json($query->limit(50)->get());
    }

    /** POST /api/notifications */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_source' => ['required', 'in:bethmi,pasindu,kavishka,jithmi,common'],
            'title'         => ['required', 'string', 'max:160'],
            'message'       => ['nullable', 'string', 'max:500'],
        ]);

        $notification = Notification::create($data + [
            'user_id'    => $request->user()->id,
            'is_read'    => false,
            'created_at' => now(),
        ]);

        return response()->json($notification, 201);
    }

    /** PATCH /api/notifications/{notification} — mark one as read. */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['is_read' => true]);

        return response()->json($notification);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /** DELETE /api/notifications/{notification} */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }
}
