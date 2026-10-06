<?php

namespace App\Services\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Enums\RiskLevel;
use App\Models\Assignment;
use App\Models\AssignmentProgressLog;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Services\Platform\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * JITHMI — applies the RiskEngine to a student's assignments, persists the
 * results (denormalised fields + risk history) and raises alerts when an
 * assignment escalates to High or Critical risk.
 */
class RiskService
{
    public function __construct(
        private readonly RiskEngine $engine,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array<string, float> */
    public function availability(User $user): array
    {
        return array_map('floatval', $user->settingsOrDefault()->availability ?: config('edusmart.risk.default_availability'));
    }

    /** @return Collection<int, Assignment> */
    public function activeAssignments(User $user): Collection
    {
        return $user->assignments()->with('module')->where('status', '!=', AssignmentStatus::Completed)->get();
    }

    /**
     * Live assessment of every active assignment (no persistence).
     *
     * @return array<int, array<string, mixed>>
     */
    public function assess(User $user, ?Collection $assignments = null, float $extraDailyHours = 0.0): array
    {
        $assignments ??= $this->activeAssignments($user);

        return $this->engine->assess($assignments, $this->availability($user), now(), $extraDailyHours, $this->velocities($assignments));
    }

    /**
     * Recalculate and store risk for all active assignments of a student.
     * Called whenever progress, deadline or workload changes, and daily.
     */
    public function recalculate(User $user, string $trigger, ?Assignment $changed = null): array
    {
        $assignments = $this->activeAssignments($user);
        $results = $this->engine->assess($assignments, $this->availability($user), now(), 0.0, $this->velocities($assignments));

        foreach ($assignments as $assignment) {
            $result = $results[$assignment->id] ?? null;
            if (! $result) {
                continue;
            }
            $previousLevel = $assignment->risk_level;

            $assignment->forceFill([
                'risk_score' => $result['score'],
                'risk_level' => $result['level'],
                'risk_updated_at' => now(),
                'priority_rank' => $result['rank'],
            ])->saveQuietly();

            $isChanged = $changed && $changed->id === $assignment->id;
            $levelMoved = $previousLevel !== $result['level'];
            if ($isChanged || $levelMoved || $previousLevel === null) {
                $this->snapshot($assignment, $result, $isChanged ? $trigger : 'rebalanced');
            }

            if ($levelMoved && in_array($result['level'], [RiskLevel::High, RiskLevel::Critical], true)
                && ($previousLevel === null || $result['level']->rank() > $previousLevel->rank())) {
                $this->notifications->send(
                    $user,
                    ModuleKey::Assignments,
                    "Risk is now {$result['level']->label()}: {$assignment->title}",
                    $result['reasons'][0]['text'] ?? null,
                    $result['level'] === RiskLevel::Critical ? NotificationType::Danger : NotificationType::Warning,
                    "/assignments/{$assignment->id}",
                    'shield-exclamation',
                );
            }
        }

        // Completed work carries no risk.
        $user->assignments()->where('status', AssignmentStatus::Completed)->whereNotNull('priority_rank')
            ->update(['risk_score' => 0, 'risk_level' => RiskLevel::Low->value, 'priority_rank' => null]);

        return $results;
    }

    /** @param  array<string, mixed>  $result */
    public function snapshot(Assignment $assignment, array $result, string $trigger): RiskAssessment
    {
        return RiskAssessment::create([
            'assignment_id' => $assignment->id,
            'user_id' => $assignment->user_id,
            'score' => $result['score'],
            'level' => $result['level'],
            'probability' => $result['probability'],
            'remaining_hours' => $result['remaining_hours'],
            'available_hours' => min($result['available_hours'], 99999),
            'required_hours_per_day' => min($result['required_hours_per_day'], 9999),
            'available_hours_per_day' => min($result['available_hours_per_day'], 9999),
            'days_left' => max(-99999, min($result['days_left'], 99999)),
            'load_ratio' => min($result['load_ratio'], 9999),
            'factors' => $result['factors'],
            'reasons' => $result['reasons'],
            'trigger' => $trigger,
            'calculated_at' => now(),
        ]);
    }

    /**
     * What-if scenario: apply hypothetical changes and compare risk before
     * and after for the target assignment and everything it affects.
     *
     * @param  array{deadline?: ?string, estimated_hours?: ?float, progress?: ?int, extra_daily_hours?: ?float}  $changes
     */
    public function whatIf(User $user, Assignment $target, array $changes): array
    {
        $assignments = $this->activeAssignments($user);
        if ($target->isCompleted()) {
            $assignments->push($target);
        }
        $before = $this->engine->assess($assignments, $this->availability($user), now(), 0.0, $this->velocities($assignments));

        $scenario = $assignments->map(function (Assignment $a) use ($target, $changes) {
            if ($a->id !== $target->id) {
                return $a;
            }
            $copy = $a->replicate(['risk_score', 'risk_level']);
            $copy->id = $a->id;
            $copy->created_at = $a->created_at;
            $copy->status = AssignmentStatus::InProgress;
            if (! empty($changes['deadline'])) {
                $copy->deadline = Carbon::parse($changes['deadline']);
            }
            if (isset($changes['estimated_hours'])) {
                $copy->estimated_hours = (float) $changes['estimated_hours'];
            }
            if (isset($changes['progress'])) {
                $copy->progress = (int) $changes['progress'];
            }

            return $copy;
        });

        $extra = (float) ($changes['extra_daily_hours'] ?? 0);
        $after = $this->engine->assess($scenario, $this->availability($user), now(), $extra, $this->velocities($assignments));

        $affected = [];
        foreach ($after as $id => $result) {
            if ($id === $target->id || ! isset($before[$id])) {
                continue;
            }
            if ($before[$id]['score'] !== $result['score']) {
                $affected[] = [
                    'assignment_id' => $id,
                    'title' => $assignments->firstWhere('id', $id)?->title,
                    'before' => $before[$id]['score'],
                    'after' => $result['score'],
                    'before_level' => $before[$id]['level'],
                    'after_level' => $result['level'],
                ];
            }
        }

        return [
            'before' => $before[$target->id] ?? null,
            'after' => $after[$target->id] ?? null,
            'affected' => $affected,
            'plan' => $this->engine->plan($scenario, $after, $this->availability($user), 7, now(), $extra),
            'recommended_daily_hours' => $this->engine->recommendedDailyHours($scenario),
        ];
    }

    /**
     * Recent progress pace in percentage points per day (last 7 days).
     *
     * @param  Collection<int, Assignment>  $assignments
     * @return array<int, float|null>
     */
    public function velocities(Collection $assignments): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }
        $since = now()->subDays(7);
        $logs = AssignmentProgressLog::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->where('created_at', '>=', $since)
            ->selectRaw('assignment_id, SUM(CAST(progress_after AS int) - CAST(progress_before AS int)) AS gained, COUNT(*) AS entries')
            ->groupBy('assignment_id')
            ->get()
            ->keyBy('assignment_id');

        $velocity = [];
        foreach ($assignments as $assignment) {
            $row = $logs->get($assignment->id);
            $velocity[$assignment->id] = $row && $row->entries > 0 ? round(max(0, (float) $row->gained) / 7, 2) : null;
        }

        return $velocity;
    }

    public function engine(): RiskEngine
    {
        return $this->engine;
    }
}
