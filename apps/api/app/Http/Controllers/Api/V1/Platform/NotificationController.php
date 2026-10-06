<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\NotificationResource;
use App\Models\UserNotification;
use App\Services\Platform\NotificationService;
use App\Services\Platform\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Common Services — notifications centre (also delivered in realtime via
 * Firestore). Polling this endpoint doubles as the reminder safety-net when
 * no scheduler is running.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ReminderService $reminders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->reminders->dispatchForUserThrottled($user);

        $page = $user->userNotifications()
            ->when($request->input('filter') === 'unread', fn ($q) => $q->unread())
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->latest()
            ->paginate(min(100, $request->integer('per_page', 20)));

        return NotificationResource::collection($page)->additional([
            'unread_count' => $user->userNotifications()->unread()->count(),
        ])->response();
    }

    public function markRead(UserNotification $notification): NotificationResource
    {
        $this->notifications->markRead($notification);
        activity()->skip();

        return NotificationResource::make($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user());
        activity()->action('notifications.read_all')->describe("Marked {$count} notification(s) as read");

        return response()->json(['message' => 'All notifications marked as read.', 'updated' => $count]);
    }

    public function destroy(UserNotification $notification): JsonResponse
    {
        $this->notifications->delete($notification);
        activity()->action('notifications.deleted')->describe('Deleted a notification');

        return response()->json(['message' => 'Notification deleted.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->userNotifications()->whereNotNull('read_at')->get();
        foreach ($notifications as $notification) {
            $this->notifications->delete($notification);
        }
        activity()->action('notifications.cleared')->describe("Cleared {$notifications->count()} read notification(s)");

        return response()->json(['message' => 'Read notifications cleared.', 'deleted' => $notifications->count()]);
    }
}
