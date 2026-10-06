<?php

namespace App\Services\Platform;

use App\Enums\ModuleKey;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Firebase\FirebaseService;
use App\Support\Activity\ActivityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persists the audit trail. Requests are written by the RecordActivity
 * middleware (one row per API call); background jobs and scheduled tasks
 * use log() directly. Each entry is also mirrored to the student's realtime
 * activity feed in Firestore.
 */
class ActivityLogger
{
    public function __construct(private readonly FirebaseService $firebase) {}

    /**
     * Record a system-initiated event (scheduler, seeding, background work).
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ?User $user,
        ModuleKey $module,
        string $action,
        string $description,
        ?object $subject = null,
        array $properties = [],
    ): ActivityLog {
        $log = ActivityLog::create([
            'user_id' => $user?->id,
            'module' => $module,
            'action' => $action,
            'description' => Str::limit($description, 495),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'method' => 'SYSTEM',
        ]);

        $this->mirror($log);

        return $log;
    }

    /** Persist the activity of an HTTP request (called by middleware). */
    public function recordRequest(Request $request, Response $response, ActivityContext $context, float $startedAt): ?ActivityLog
    {
        $route = $request->route();
        $routeName = $route?->getName();
        $action = $context->getAction() ?? $routeName ?? strtolower($request->method()).' '.$request->path();
        $status = $response->getStatusCode();

        $properties = $context->getProperties();
        if ($status >= 400) {
            $properties['outcome'] = 'failed';
            if ($status === 422 && method_exists($response, 'getData')) {
                $properties['errors'] = array_keys((array) ($response->getData(true)['errors'] ?? []));
            }
        }

        $log = ActivityLog::create([
            'user_id' => $context->getUserId() ?? $request->user()?->id,
            'module' => $context->getModule() ?? $this->moduleFor($routeName),
            'action' => Str::limit($action, 78, ''),
            'description' => $context->getDescription() ?? $this->describe($request, $routeName, $status),
            'subject_type' => $context->getSubjectType(),
            'subject_id' => $context->getSubjectId(),
            'properties' => $properties ?: null,
            'method' => $request->method(),
            'route' => Str::limit($request->path(), 158, ''),
            'status_code' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 495, ''),
        ]);

        if ($status < 400) {
            $this->mirror($log);
        }

        return $log;
    }

    private function mirror(ActivityLog $log): void
    {
        if (! $log->user_id) {
            return;
        }

        $this->firebase->mirrorActivity($log->user_id, [
            'id' => $log->id,
            'module' => $log->module?->value,
            'action' => $log->action,
            'description' => $log->description,
            'created_at' => $log->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    /** "documents.store" → learning, "assignments.*" → assignments, … */
    private function moduleFor(?string $routeName): ModuleKey
    {
        $prefix = Str::before((string) $routeName, '.');

        return match ($prefix) {
            'learning', 'documents', 'summaries', 'study-aids' => ModuleKey::Learning,
            'study' => ModuleKey::Study,
            'assistant', 'knowledge', 'academic-dates' => ModuleKey::Assistant,
            'assignments' => ModuleKey::Assignments,
            default => ModuleKey::Platform,
        };
    }

    private function describe(Request $request, ?string $routeName, int $status): string
    {
        $verb = match ($request->method()) {
            'POST' => 'Created',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Viewed',
        };
        $subject = str_replace(['.', '-', '_'], ' ', (string) ($routeName ?? $request->path()));
        $text = $verb.' — '.$subject;

        return $status >= 400 ? $text." (failed: HTTP {$status})" : $text;
    }
}
