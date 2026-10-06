<?php

namespace App\Services\Study;

use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Enums\SessionStatus;
use App\Enums\StudyActivity;
use App\Models\Assignment;
use App\Models\StudySession;
use App\Models\User;
use App\Services\Assignments\AssignmentService;
use App\Services\Firebase\FirebaseService;
use App\Services\Platform\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PASINDU — the study timer as a server-side state machine:
 *
 *   start → active ⇄ paused
 *                  ⇄ on_break
 *          → completed (stop)  |  cancelled
 *
 * focus_seconds accumulates every active stretch, so elapsed time is exact
 * no matter how often the page reloads or which device asks. Each transition
 * is recorded in study_session_events and mirrored to Firestore so every
 * open tab shows the same live timer.
 *
 * Integration on stop: the focused time is added to the linked assignment
 * (JITHMI), which recalculates its deadline risk.
 */
class StudySessionService
{
    public function __construct(
        private readonly EngagementService $engagement,
        private readonly SessionFeedbackBuilder $feedback,
        private readonly AssignmentService $assignments,
        private readonly NotificationService $notifications,
        private readonly FirebaseService $firebase,
    ) {}

    public function live(User $user): ?StudySession
    {
        return $user->studySessions()->live()->with(['module', 'document', 'assignment'])->latest('started_at')->first();
    }

    /** @param  array<string, mixed>  $data */
    public function start(User $user, array $data): StudySession
    {
        if ($existing = $this->live($user)) {
            throw new \DomainException("You already have a study session running ({$existing->activity->label()}). Stop it before starting a new one.");
        }

        $session = DB::transaction(function () use ($user, $data) {
            $assignment = isset($data['assignment_id']) ? Assignment::find($data['assignment_id']) : null;

            $session = $user->studySessions()->create([
                'module_id' => $data['module_id'] ?? $assignment?->module_id,
                'assignment_id' => $data['assignment_id'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'activity' => $data['activity'] ?? StudyActivity::Revision,
                'goal' => $data['goal'] ?? null,
                'planned_minutes' => $data['planned_minutes'] ?? (int) config('edusmart.study.default_planned_minutes', 25),
                'status' => SessionStatus::Active,
                'started_at' => now(),
                'last_resumed_at' => now(),
            ]);
            $this->event($session, 'started', [
                'planned_minutes' => $session->planned_minutes,
                'activity' => $session->activity->value,
            ]);

            return $session;
        });

        $this->firebase->mirrorStudySession($user->id, $session->load('module'));

        return $session->load(['module', 'document', 'assignment']);
    }

    public function pause(StudySession $session): StudySession
    {
        $this->guard($session, [SessionStatus::Active], 'pause');

        return $this->transition($session, function (StudySession $s) {
            $s->focus_seconds = $s->elapsedFocusSeconds();
            $s->status = SessionStatus::Paused;
            $s->paused_at = now();
            $s->last_resumed_at = null;
            $s->pause_count++;
        }, 'paused');
    }

    public function startBreak(StudySession $session, ?int $minutes = null): StudySession
    {
        $this->guard($session, [SessionStatus::Active, SessionStatus::Paused], 'start a break on');

        return $this->transition($session, function (StudySession $s) {
            if ($s->status === SessionStatus::Active) {
                $s->focus_seconds = $s->elapsedFocusSeconds();
            }
            $s->status = SessionStatus::OnBreak;
            $s->paused_at = now();
            $s->last_resumed_at = null;
            $s->break_count++;
        }, 'break_started', ['minutes' => $minutes]);
    }

    public function resume(StudySession $session): StudySession
    {
        $this->guard($session, [SessionStatus::Paused, SessionStatus::OnBreak], 'resume');
        $wasBreak = $session->status === SessionStatus::OnBreak;

        return $this->transition($session, function (StudySession $s) use ($wasBreak) {
            if ($wasBreak) {
                $s->break_seconds += $s->currentBreakSeconds();
            }
            $s->status = SessionStatus::Active;
            $s->paused_at = null;
            $s->last_resumed_at = now();
        }, $wasBreak ? 'break_ended' : 'resumed');
    }

    /**
     * Stop and record the session; returns it with end-of-session feedback.
     *
     * @param  array{rating?: ?int, mood?: ?string, notes?: ?string, progress?: ?int}  $reflection
     */
    public function stop(StudySession $session, array $reflection = []): StudySession
    {
        $this->guard($session, [SessionStatus::Active, SessionStatus::Paused, SessionStatus::OnBreak], 'stop');

        $session = DB::transaction(function () use ($session, $reflection) {
            if ($session->status === SessionStatus::OnBreak) {
                $session->break_seconds += $session->currentBreakSeconds();
            }
            $session->focus_seconds = $session->elapsedFocusSeconds();
            $session->status = SessionStatus::Completed;
            $session->ended_at = now();
            $session->last_resumed_at = null;
            $session->paused_at = null;
            $session->actual_minutes = (int) round($session->focus_seconds / 60);
            $session->avg_engagement = $this->engagement->averageFor($session);
            $session->rating = $reflection['rating'] ?? null;
            $session->mood = $reflection['mood'] ?? null;
            $session->notes = $reflection['notes'] ?? null;
            $session->save();
            $this->event($session, 'stopped', ['focus_minutes' => $session->actual_minutes]);

            return $session;
        });

        $assignmentUpdate = $this->applyToAssignment($session, $reflection['progress'] ?? null);
        $feedback = $this->feedback->build($session, $assignmentUpdate);
        $session->forceFill([
            'feedback' => $feedback,
            'focus_score' => $feedback['focus_score'],
        ])->save();

        $this->firebase->mirrorStudySession($session->user_id, null);
        $this->notifications->send(
            $session->user,
            ModuleKey::Study,
            sprintf('Session complete: %d min focused', $session->actual_minutes),
            $feedback['headline'],
            NotificationType::Success,
            "/study/sessions/{$session->id}",
            'stopwatch',
        );

        return $session->refresh()->load(['module', 'document', 'assignment']);
    }

    public function cancel(StudySession $session): StudySession
    {
        $this->guard($session, [SessionStatus::Active, SessionStatus::Paused, SessionStatus::OnBreak], 'cancel');

        $session = $this->transition($session, function (StudySession $s) {
            $s->focus_seconds = $s->elapsedFocusSeconds();
            $s->status = SessionStatus::Cancelled;
            $s->ended_at = now();
            $s->last_resumed_at = null;
            $s->actual_minutes = (int) round($s->focus_seconds / 60);
        }, 'cancelled');
        $this->firebase->mirrorStudySession($session->user_id, null);

        return $session;
    }

    /** JITHMI integration: log the studied hours against the linked assignment. */
    private function applyToAssignment(StudySession $session, ?int $progress): ?array
    {
        $assignment = $session->assignment_id ? Assignment::find($session->assignment_id) : null;
        if (! $assignment || $assignment->isCompleted() || $session->focus_seconds < 60) {
            return null;
        }

        try {
            $before = ['progress' => $assignment->progress, 'risk' => $assignment->risk_score, 'level' => $assignment->risk_level?->value];
            $hours = round($session->focus_seconds / 3600, 2);
            $updated = $this->assignments->recordProgress(
                $assignment,
                $progress,
                $hours,
                sprintf('%d min %s session', $session->actual_minutes, mb_strtolower($session->activity->label())),
                'study_session',
                $session->id,
            );

            return [
                'assignment_id' => $updated->id,
                'title' => $updated->title,
                'hours_added' => $hours,
                'progress_before' => $before['progress'],
                'progress_after' => $updated->progress,
                'risk_before' => $before['risk'],
                'risk_after' => $updated->risk_score,
                'level_after' => $updated->risk_level?->value,
            ];
        } catch (\Throwable $e) {
            Log::warning('Could not apply study session to assignment: '.$e->getMessage());

            return null;
        }
    }

    private function transition(StudySession $session, callable $mutate, string $event, array $payload = []): StudySession
    {
        DB::transaction(function () use ($session, $mutate, $event, $payload) {
            $mutate($session);
            $session->save();
            $this->event($session, $event, array_filter($payload, fn ($v) => $v !== null) + ['focus_seconds' => $session->focus_seconds]);
        });
        $this->firebase->mirrorStudySession($session->user_id, $session->load('module'));

        return $session->load(['module', 'document', 'assignment']);
    }

    /** @param  list<SessionStatus>  $allowed */
    private function guard(StudySession $session, array $allowed, string $verb): void
    {
        if (! in_array($session->status, $allowed, true)) {
            throw new \DomainException("You can't {$verb} a session that is {$session->status->label()}.");
        }
    }

    public function event(StudySession $session, string $type, array $payload = []): void
    {
        $session->events()->create([
            'user_id' => $session->user_id,
            'type' => $type,
            'payload' => $payload ?: null,
            'occurred_at' => now(),
        ]);
    }
}
