<?php

namespace App\Services\Firebase;

use App\Models\StudySession;
use App\Models\User;
use App\Models\UserNotification;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;

/**
 * Firebase realtime layer.
 *
 * SQL Server remains the system of record; Firestore holds a realtime mirror
 * that browsers subscribe to:
 *
 *   users/{uid}                          profile summary
 *   users/{uid}/notifications/{id}       notifications (badge + toasts)
 *   users/{uid}/activity/{id}            live activity feed
 *   users/{uid}/live/study               running study timer (cross-tab/device)
 *   users/{uid}/searches/{id}            recent searches
 *
 * Identity: Laravel mints a Firebase *custom token* for the signed-in
 * student (uid "user_{id}"); Firestore security rules then restrict each
 * student to their own subtree. Writes from this service use the Firestore
 * REST API — against the local Emulator Suite in development (no
 * credentials) or with a service-account OAuth token in production.
 *
 * Every call is best-effort: failures open a short circuit breaker so an
 * unreachable Firebase never slows down or breaks the API.
 */
class FirebaseService
{
    private const BREAKER_KEY = 'firebase:circuit-open';

    public function enabled(): bool
    {
        return (bool) config('edusmart.firebase.enabled') && (string) config('edusmart.firebase.project_id') !== '';
    }

    public function projectId(): string
    {
        return (string) config('edusmart.firebase.project_id');
    }

    public function uid(int $userId): string
    {
        return 'user_'.$userId;
    }

    public function usesEmulator(): bool
    {
        return (string) config('edusmart.firebase.firestore_emulator_host') !== '';
    }

    /** Connection details the web client needs to join the realtime layer. */
    public function clientConfig(User $user): array
    {
        if (! $this->enabled()) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'project_id' => $this->projectId(),
            'uid' => $this->uid($user->id),
            'token' => $this->createCustomToken($user),
            'emulators' => $this->usesEmulator() ? [
                'firestore' => config('edusmart.firebase.firestore_emulator_host'),
                'auth' => config('edusmart.firebase.auth_emulator_host'),
            ] : null,
        ];
    }

    /**
     * Firebase custom token for signInWithCustomToken(). Unsigned for the
     * Auth emulator (which accepts alg "none"), RS256-signed with the service
     * account key for a real project.
     */
    public function createCustomToken(User $user): ?string
    {
        $now = time();
        $payload = [
            'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
            'iat' => $now,
            'exp' => $now + 3600,
            'uid' => $this->uid($user->id),
            'claims' => ['laravel_id' => $user->id, 'name' => $user->name],
        ];

        if ((string) config('edusmart.firebase.auth_emulator_host') !== '') {
            $payload['iss'] = $payload['sub'] = 'firebase-auth-emulator@example.com';

            return $this->base64Url(json_encode(['alg' => 'none', 'typ' => 'JWT'])).'.'
                .$this->base64Url(json_encode($payload)).'.';
        }

        $account = $this->serviceAccount();
        if ($account === null) {
            return null;
        }
        $payload['iss'] = $payload['sub'] = $account['client_email'];

        return $this->signJwt($payload, $account['private_key']);
    }

    /* ------------------------------------------------------------------ *
     |  Domain mirrors
     * ------------------------------------------------------------------ */

    public function mirrorProfile(User $user): void
    {
        $this->setDocument("users/{$this->uid($user->id)}", [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'updated_at' => now(),
        ]);
    }

    public function mirrorNotification(UserNotification $notification): void
    {
        $this->setDocument("users/{$this->uid($notification->user_id)}/notifications/{$notification->id}", [
            'id' => $notification->id,
            'module' => $notification->module?->value,
            'type' => $notification->type?->value,
            'title' => $notification->title,
            'message' => $notification->message,
            'icon' => $notification->icon,
            'action_url' => $notification->action_url,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at ?? now(),
        ]);
    }

    /** @param  array<string, mixed>  $fields */
    public function patchNotification(int $userId, int $notificationId, array $fields): void
    {
        $this->setDocument("users/{$this->uid($userId)}/notifications/{$notificationId}", $fields, merge: true);
    }

    public function deleteNotification(int $userId, int $notificationId): void
    {
        $this->deleteDocument("users/{$this->uid($userId)}/notifications/{$notificationId}");
    }

    /** @param  array<string, mixed>  $activity */
    public function mirrorActivity(int $userId, array $activity): void
    {
        $this->setDocument("users/{$this->uid($userId)}/activity/{$activity['id']}", $activity);
    }

    /** Live timer state; null clears it when the session ends. */
    public function mirrorStudySession(int $userId, ?StudySession $session): void
    {
        $path = "users/{$this->uid($userId)}/live/study";
        if ($session === null || ! $session->isLive()) {
            $this->setDocument($path, ['status' => 'idle', 'updated_at' => now()]);

            return;
        }

        $this->setDocument($path, [
            'session_id' => $session->id,
            'status' => $session->status->value,
            'activity' => $session->activity?->label(),
            'module' => $session->module?->code,
            'goal' => $session->goal,
            'planned_minutes' => $session->planned_minutes,
            'focus_seconds' => $session->focus_seconds,
            'last_resumed_at' => $session->last_resumed_at,
            'paused_at' => $session->paused_at,
            'started_at' => $session->started_at,
            'updated_at' => now(),
        ]);
    }

    public function mirrorSearch(int $userId, int $searchId, string $query, int $results): void
    {
        $this->setDocument("users/{$this->uid($userId)}/searches/{$searchId}", [
            'query' => $query,
            'results' => $results,
            'created_at' => now(),
        ]);
    }

    /* ------------------------------------------------------------------ *
     |  Firestore REST primitives
     * ------------------------------------------------------------------ */

    /** @param  array<string, mixed>  $data */
    public function setDocument(string $path, array $data, bool $merge = false): bool
    {
        if (! $this->available()) {
            return false;
        }

        $query = $merge
            ? '?'.implode('&', array_map(fn ($field) => 'updateMask.fieldPaths='.rawurlencode($field), array_keys($data)))
            : '';

        return $this->send('patch', $this->documentUrl($path).$query, ['fields' => $this->encodeFields($data)]);
    }

    public function deleteDocument(string $path): bool
    {
        return $this->available() && $this->send('delete', $this->documentUrl($path));
    }

    /** Health probe used by the /system/status endpoint. */
    public function ping(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(2)->connectTimeout(1)
                ->get($this->documentUrl('_health/ping'));

            return $response->successful() || $response->status() === 404;
        } catch (\Throwable) {
            return false;
        }
    }

    private function available(): bool
    {
        return $this->enabled() && ! Cache::get(self::BREAKER_KEY, false);
    }

    private function send(string $method, string $url, array $body = []): bool
    {
        try {
            $request = Http::withToken($this->accessToken())
                ->timeout((int) config('edusmart.firebase.timeout', 3))
                ->connectTimeout(1)
                ->acceptJson();
            $response = $method === 'delete' ? $request->delete($url) : $request->{$method}($url, $body);

            if ($response->serverError()) {
                $this->trip('HTTP '.$response->status());

                return false;
            }

            return $response->successful() || $response->status() === 404;
        } catch (\Throwable $e) {
            $this->trip($e->getMessage());

            return false;
        }
    }

    private function trip(string $reason): void
    {
        Cache::put(self::BREAKER_KEY, true, (int) config('edusmart.firebase.circuit_breaker_seconds', 60));
        Log::warning('Firebase mirror paused: '.$reason);
    }

    private function documentUrl(string $path): string
    {
        $segments = implode('/', array_map('rawurlencode', explode('/', $path)));
        $base = $this->usesEmulator()
            ? 'http://'.config('edusmart.firebase.firestore_emulator_host')
            : 'https://firestore.googleapis.com';

        return "{$base}/v1/projects/{$this->projectId()}/databases/(default)/documents/{$segments}";
    }

    /** "owner" bypasses rules on the emulator; OAuth2 token in production. */
    private function accessToken(): string
    {
        if ($this->usesEmulator()) {
            return 'owner';
        }

        return Cache::remember('firebase:access-token', 3000, function () {
            $account = $this->serviceAccount() ?? throw new RuntimeException('FIREBASE_CREDENTIALS is not configured.');
            $now = time();
            $assertion = $this->signJwt([
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/datastore',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key']);

            $response = Http::asForm()->timeout(5)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw();

            return (string) $response->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function serviceAccount(): ?array
    {
        $path = (string) config('edusmart.firebase.credentials');
        if ($path === '') {
            return null;
        }
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : base_path($path);
        if (! is_file($absolute)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($absolute), true);

        return isset($json['client_email'], $json['private_key']) ? $json : null;
    }

    private function signJwt(array $payload, string $privateKey): string
    {
        $input = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$this->base64Url(json_encode($payload));
        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the Firebase token with the service-account key.');
        }

        return $input.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param  array<string, mixed>  $data */
    private function encodeFields(array $data): array|stdClass
    {
        if ($data === []) {
            return new stdClass;
        }
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }

        return $fields;
    }

    private function encodeValue(mixed $value): array
    {
        return match (true) {
            $value === null => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            $value instanceof \BackedEnum => ['stringValue' => (string) $value->value],
            $value instanceof DateTimeInterface => ['timestampValue' => Carbon::instance($value)->utc()->format('Y-m-d\TH:i:s.u\Z')],
            is_array($value) && array_is_list($value) => ['arrayValue' => $value === []
                ? new stdClass
                : ['values' => array_map(fn ($v) => $this->encodeValue($v), $value)]],
            is_array($value) => ['mapValue' => ['fields' => $this->encodeFields($value)]],
            default => ['stringValue' => (string) $value],
        };
    }
}
