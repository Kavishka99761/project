<?php

namespace App\Services\Platform;

use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Firebase\FirebaseService;

/**
 * Common Services — notifications. Every notification is stored in SQL
 * Server and pushed to Firestore so open browser tabs receive it instantly
 * (and can raise a desktop notification).
 */
class NotificationService
{
    public function __construct(private readonly FirebaseService $firebase) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $user,
        ModuleKey $module,
        string $title,
        ?string $message = null,
        NotificationType $type = NotificationType::Info,
        ?string $actionUrl = null,
        ?string $icon = null,
        array $data = [],
    ): ?UserNotification {
        if (! $user->settingsOrDefault()->notify_in_app) {
            return null;
        }

        $notification = $user->userNotifications()->create([
            'module' => $module,
            'type' => $type,
            'title' => mb_substr($title, 0, 160),
            'message' => $message ? mb_substr($message, 0, 1000) : null,
            'icon' => $icon ?? $type->meta()['icon'] ?? null,
            'action_url' => $actionUrl,
            'data' => $data ?: null,
        ]);

        $this->firebase->mirrorNotification($notification);

        return $notification;
    }

    public function markRead(UserNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
            $this->firebase->mirrorNotification($notification);
        }
    }

    public function markAllRead(User $user): int
    {
        $ids = $user->userNotifications()->unread()->pluck('id');
        $user->userNotifications()->whereIn('id', $ids)->update(['read_at' => now()]);
        foreach ($ids as $id) {
            $this->firebase->patchNotification($user->id, $id, ['read' => true]);
        }

        return $ids->count();
    }

    public function delete(UserNotification $notification): void
    {
        $this->firebase->deleteNotification($notification->user_id, $notification->id);
        $notification->delete();
    }
}
