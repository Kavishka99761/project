<?php

namespace App\Services\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Enums\RiskLevel;
use App\Models\Assignment;
use App\Models\AssignmentProgressLog;
use App\Models\User;
use App\Services\Platform\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * JITHMI — assignment lifecycle. Every change that can move the risk
 * (progress, deadline, workload, completion) records history and triggers a
 * recalculation for the student's whole workload.
 */
class AssignmentService
{
    public function __construct(
        private readonly RiskService $risk,
        private readonly NotificationService $notifications,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(User $user, array $data): Assignment
    {
        $assignment = DB::transaction(function () use ($user, $data) {
            $assignment = $user->assignments()->create($data + [
                'progress' => 0,
                'completed_hours' => 0,
                'status' => AssignmentStatus::NotStarted,
            ]);
            if ($assignment->progress > 0) {
                $assignment->update(['status' => AssignmentStatus::InProgress, 'started_at' => now()]);
            }

            return $assignment;
        });

        $this->risk->recalculate($user, 'created', $assignment);

        return $assignment->refresh();
    }

    /** @param  array<string, mixed>  $data */
    public function update(Assignment $assignment, array $data): Assignment
    {
        $before = $assignment->only(['deadline', 'estimated_hours', 'progress', 'completed_hours']);
        $assignment->fill($data);

        $trigger = match (true) {
            $assignment->isDirty('deadline') => 'deadline_changed',
            $assignment->isDirty(['progress', 'completed_hours']) => 'progress_changed',
            $assignment->isDirty('estimated_hours') => 'workload_changed',
            default => 'updated',
        };

        DB::transaction(function () use ($assignment, $before) {
            if ($assignment->isDirty('progress') && ! $assignment->isCompleted()) {
                $assignment->status = $assignment->progress > 0 ? AssignmentStatus::InProgress : AssignmentStatus::NotStarted;
                $assignment->started_at ??= $assignment->progress > 0 ? now() : null;
                $this->logProgress($assignment, (int) $before['progress'], (int) $assignment->progress, 0, 'manual', 'Progress edited');
            }
            $assignment->save();
        });

        $this->risk->recalculate($assignment->user, $trigger, $assignment);

        return $assignment->refresh();
    }

    /**
     * Record progress: an explicit percentage, extra hours worked, or both.
     * When only hours are given, progress follows hours / estimated hours.
     */
    public function recordProgress(
        Assignment $assignment,
        ?int $progress = null,
        float $hoursAdded = 0.0,
        ?string $note = null,
        string $source = 'manual',
        ?int $studySessionId = null,
    ): Assignment {
        if ($assignment->isCompleted()) {
            throw new \DomainException('This assignment is already completed. Reopen it to record more progress.');
        }

        DB::transaction(function () use ($assignment, $progress, $hoursAdded, $note, $source, $studySessionId) {
            $previous = (int) $assignment->progress;
            $assignment->completed_hours = round((float) $assignment->completed_hours + max(0, $hoursAdded), 2);

            if ($progress === null && $hoursAdded > 0) {
                $byHours = (int) round($assignment->completed_hours / max((float) $assignment->estimated_hours, 0.1) * 100);
                // Hours alone never mark the work done — the student confirms completion.
                $progress = max($previous, min(95, $byHours));
            }
            $assignment->progress = max(0, min(100, $progress ?? $previous));
            $assignment->status = $assignment->progress > 0 ? AssignmentStatus::InProgress : AssignmentStatus::NotStarted;
            $assignment->started_at ??= $assignment->progress > 0 || $hoursAdded > 0 ? now() : null;
            $assignment->save();

            $this->logProgress($assignment, $previous, (int) $assignment->progress, $hoursAdded, $source, $note, $studySessionId);
        });

        $this->risk->recalculate($assignment->user, 'progress_changed', $assignment);

        return $assignment->refresh();
    }

    public function complete(Assignment $assignment, ?Carbon $submittedAt = null, ?string $note = null): Assignment
    {
        $submittedAt ??= now();

        DB::transaction(function () use ($assignment, $submittedAt, $note) {
            $previous = (int) $assignment->progress;
            $late = $submittedAt->greaterThan($assignment->deadline);

            $assignment->forceFill([
                'status' => AssignmentStatus::Completed,
                'progress' => 100,
                'completed_at' => $submittedAt,
                'was_overdue' => $assignment->was_overdue || $late,
                'risk_score' => 0,
                'risk_level' => RiskLevel::Low,
                'priority_rank' => null,
            ])->save();

            $assignment->submissions()->create([
                'user_id' => $assignment->user_id,
                'submitted_at' => $submittedAt,
                'deadline_at' => $assignment->deadline,
                'is_late' => $late,
                'minutes_late' => $late ? (int) $assignment->deadline->diffInMinutes($submittedAt) : 0,
                'note' => $note,
            ]);

            $this->logProgress($assignment, $previous, 100, 0, 'completion', $note ?? 'Marked as completed');
        });

        $this->notifications->send(
            $assignment->user,
            ModuleKey::Assignments,
            'Completed: '.$assignment->title,
            $assignment->was_overdue ? 'Submitted after the deadline — it is recorded in your overdue history.' : 'Submitted on time. Great work!',
            NotificationType::Success,
            "/assignments/{$assignment->id}",
            'trophy',
        );

        $this->risk->recalculate($assignment->user, 'completed', $assignment);

        return $assignment->refresh();
    }

    public function reopen(Assignment $assignment): Assignment
    {
        $assignment->forceFill([
            'status' => AssignmentStatus::InProgress,
            'progress' => min(95, (int) $assignment->progress),
            'completed_at' => null,
        ])->save();

        $this->logProgress($assignment, 100, (int) $assignment->progress, 0, 'manual', 'Reopened');
        $this->risk->recalculate($assignment->user, 'reopened', $assignment);

        return $assignment->refresh();
    }

    public function delete(Assignment $assignment): void
    {
        $user = $assignment->user;
        $assignment->delete();
        $this->risk->recalculate($user, 'deleted');
    }

    /**
     * Workload summary for the dashboard (remaining vs available time).
     *
     * @return array<string, mixed>
     */
    public function workload(User $user, ?array $risk = null): array
    {
        $active = $this->risk->activeAssignments($user);
        $risk ??= $this->risk->assess($user, $active);
        $availability = $this->risk->availability($user);
        $engine = $this->risk->engine();

        $remaining = round($active->sum(fn (Assignment $a) => $a->remainingHours()), 1);
        $latest = $active->max('deadline');
        $horizonEnd = $latest ? Carbon::parse($latest)->min(now()->addDays((int) config('edusmart.risk.horizon_days', 30)))->max(now()->addDays(7)) : now()->addDays(7);
        $available = $engine->availableHours(now(), $horizonEnd, $availability);

        $dueThisWeek = $active->filter(fn ($a) => $a->deadline->lessThanOrEqualTo(now()->addDays(7)));
        $weekRemaining = round($dueThisWeek->sum(fn ($a) => $a->remainingHours()), 1);
        $weekAvailable = $engine->availableHours(now(), now()->addDays(7), $availability);

        $ratio = $available > 0 ? $remaining / $available : ($remaining > 0 ? 9.9 : 0);
        [$status, $level] = match (true) {
            $remaining == 0.0 => ['All clear', RiskLevel::Low],
            $ratio <= 0.6 => ['Comfortable', RiskLevel::Low],
            $ratio <= 0.85 => ['Manageable', RiskLevel::Medium],
            $ratio <= 1.0 => ['Tight', RiskLevel::High],
            default => ['Overloaded', RiskLevel::Critical],
        };

        $estimated = $active->sum('estimated_hours');
        $done = $active->sum(fn ($a) => (float) $a->estimated_hours * $a->progress / 100);

        return [
            'remaining_hours' => $remaining,
            'available_hours' => round($available, 1),
            'horizon_days' => (int) ceil(now()->diffInDays($horizonEnd)),
            'ratio' => round($ratio, 2),
            'status' => $status,
            'level' => $level,
            'week' => [
                'remaining_hours' => $weekRemaining,
                'available_hours' => round($weekAvailable, 1),
                'count' => $dueThisWeek->count(),
            ],
            'overall_progress' => $estimated > 0 ? (int) round($done / $estimated * 100) : 0,
            'active_count' => $active->count(),
            'overdue_count' => $active->filter(fn ($a) => $a->isOverdue())->count(),
            'recommended_daily_hours' => $engine->recommendedDailyHours($active),
            'average_available_per_day' => round(array_sum($availability) / 7, 1),
        ];
    }

    private function logProgress(Assignment $assignment, int $before, int $after, float $hours, string $source, ?string $note, ?int $sessionId = null): void
    {
        AssignmentProgressLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $assignment->user_id,
            'study_session_id' => $sessionId,
            'progress_before' => $before,
            'progress_after' => $after,
            'hours_added' => round($hours, 2),
            'completed_hours_after' => (float) $assignment->completed_hours,
            'source' => $source,
            'note' => $note,
        ]);
    }
}
