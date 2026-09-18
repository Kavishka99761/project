<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * FirebaseService — wraps kreait/firebase-php for server-side operations.
 *
 * Responsibilities:
 *  1. Send FCM push notifications to a student's device.
 *  2. Back-up a user's data snapshot to Firestore.
 *  3. Sync academic-calendar events to Firestore in real-time.
 *
 * The service degrades gracefully when Firebase credentials are not
 * configured (e.g., local dev without a service account), logging a
 * warning instead of throwing.
 */
class FirebaseService
{
    private bool $available;

    /** @var \Kreait\Firebase\Contract\Database|\Kreait\Firebase\Contract\Firestore|null */
    private mixed $firestore = null;

    /** @var \Kreait\Firebase\Contract\Messaging|null */
    private mixed $messaging = null;

    public function __construct()
    {
        $this->available = $this->boot();
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Send an FCM push notification to a single device token.
     *
     * @param  string  $deviceToken  FCM registration token stored on users.firebase_token
     * @param  string  $title
     * @param  string  $body
     * @param  array<string,string>  $data  Optional key-value payload
     */
    public function sendPushNotification(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (! $this->available || ! $this->messaging) {
            Log::warning('[Firebase] sendPushNotification skipped — service not configured.');
            return false;
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('[Firebase] Push notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Write a complete data snapshot for the given user to Firestore.
     * Path: backups/{userId}/latest
     *
     * @param  \App\Models\User  $user
     * @param  array<string,mixed>  $payload  The data to back up
     */
    public function backupUserData(\App\Models\User $user, array $payload): bool
    {
        if (! $this->available || ! $this->firestore) {
            Log::warning('[Firebase] backupUserData skipped — Firestore not configured.');
            return false;
        }

        try {
            $this->firestore
                ->collection('backups')
                ->document((string) $user->id)
                ->set([
                    'userId'     => $user->id,
                    'email'      => $user->email,
                    'backedUpAt' => now()->toIso8601String(),
                    'data'       => $payload,
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[Firebase] Backup failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upsert an academic-calendar event in Firestore for real-time client sync.
     * Path: academicDates/{userId}/events/{eventId}
     */
    public function syncAcademicDate(\App\Models\User $user, \App\Models\AcademicDate $date): bool
    {
        if (! $this->available || ! $this->firestore) {
            return false;
        }

        try {
            $this->firestore
                ->collection('academicDates')
                ->document((string) $user->id)
                ->collection('events')
                ->document((string) $date->id)
                ->set([
                    'id'        => $date->id,
                    'title'     => $date->title,
                    'eventDate' => $date->event_date instanceof \Carbon\Carbon
                        ? $date->event_date->toDateString()
                        : (string) $date->event_date,
                    'type'      => $date->type,
                    'reminder'  => $date->reminder,
                    'updatedAt' => now()->toIso8601String(),
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[Firebase] syncAcademicDate failed: ' . $e->getMessage());
            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Bootstrap
    // -----------------------------------------------------------------------

    private function boot(): bool
    {
        $credPath = config('services.firebase.credentials');

        // kreait/firebase-php is optional — only activate when installed + configured.
        if (! $credPath || ! file_exists($credPath) || ! class_exists(\Kreait\Firebase\Factory::class)) {
            return false;
        }

        try {
            $factory = (new \Kreait\Firebase\Factory())->withServiceAccount($credPath);
            $this->firestore = $factory->createFirestore()->database();
            $this->messaging = $factory->createMessaging();
            return true;
        } catch (\Throwable $e) {
            Log::warning('[Firebase] Boot failed: ' . $e->getMessage());
            return false;
        }
    }
}
