<?php

namespace App\Services\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\RiskLevel;
use App\Models\Assignment;
use App\Models\User;

/**
 * JITHMI — Assignment Risk Dashboard:
 *   Today's priority (ranked, with risk %), upcoming deadlines, today's
 *   recommendation ("Study X for approximately N hours"), workload
 *   (remaining vs available) and the 7-day study plan.
 */
class AssignmentDashboard
{
    public function __construct(
        private readonly RiskService $risk,
        private readonly AssignmentService $assignments,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $active = $this->risk->activeAssignments($user);
        $assessment = $this->risk->assess($user, $active);
        $engine = $this->risk->engine();
        $plan = $engine->plan($active, $assessment, $this->risk->availability($user));

        $priority = [];
        foreach ($assessment as $id => $result) {
            $assignment = $active->firstWhere('id', $id);
            $priority[] = $this->row($assignment, $result);
        }

        $upcoming = $active->filter(fn (Assignment $a) => ! $a->isOverdue())->sortBy('deadline')->take(6)
            ->map(fn (Assignment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'module' => $a->module?->code,
                'deadline' => $a->deadline->toIso8601String(),
                'days_left' => (int) floor(max(0, now()->diffInHours($a->deadline, false)) / 24),
                'hours_left' => (int) max(0, now()->diffInHours($a->deadline, false)),
                'level' => $assessment[$a->id]['level']->value ?? null,
            ])->values();

        $today = $plan[0]['allocations'] ?? [];
        $focus = $today[0] ?? null;
        $top = $priority[0] ?? null;
        $recommendation = null;
        if ($focus || $top) {
            $title = $focus['title'] ?? $top['title'];
            $hours = $focus['hours'] ?? $top['recommended_hours_per_day'];
            $recommendation = [
                'assignment_id' => $focus['assignment_id'] ?? $top['id'],
                'title' => $title,
                'hours' => $hours,
                'minutes' => (int) round($hours * 60),
                'message' => sprintf('Study %s for approximately %s.', $title, $this->duration($hours)),
                'other' => array_slice($today, 1),
                'total_today' => $plan[0]['planned_hours'] ?? 0,
            ];
        }

        $distribution = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        foreach ($assessment as $result) {
            $distribution[$result['level']->value]++;
        }

        return [
            'priority' => array_slice($priority, 0, 8),
            'upcoming' => $upcoming,
            'recommendation' => $recommendation,
            'workload' => $this->assignments->workload($user, $assessment),
            'plan' => $plan,
            'risk_distribution' => $distribution,
            'overdue' => $active->filter(fn ($a) => $a->isOverdue())->values()->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'deadline' => $a->deadline->toIso8601String(), 'progress' => $a->progress,
            ]),
            'counts' => [
                'active' => $active->count(),
                'completed' => $user->assignments()->where('status', AssignmentStatus::Completed)->count(),
                'overdue' => $active->filter(fn ($a) => $a->isOverdue())->count(),
                'critical' => $distribution['critical'],
            ],
        ];
    }

    /** @param  array<string, mixed>  $result */
    public function row(Assignment $assignment, array $result): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'module' => $assignment->module ? ['id' => $assignment->module->id, 'code' => $assignment->module->code, 'color' => $assignment->module->color] : null,
            'deadline' => $assignment->deadline->toIso8601String(),
            'priority' => $assignment->priority->value,
            'progress' => $assignment->progress,
            'status' => $assignment->bucket(),
            'score' => $result['score'],
            'level' => $result['level']->value,
            'rank' => $result['rank'],
            'urgency' => $result['urgency'],
            'remaining_hours' => $result['remaining_hours'],
            'days_left' => $result['days_left'],
            'recommended_hours_per_day' => $result['recommended_hours_per_day'],
            'top_reason' => $result['reasons'][0]['text'] ?? null,
        ];
    }

    private function duration(float $hours): string
    {
        if ($hours < 1) {
            return round($hours * 60).' minutes';
        }
        $rounded = round($hours * 2) / 2;

        return ($rounded == (int) $rounded ? (int) $rounded : $rounded).' hour'.($rounded == 1 ? '' : 's');
    }
}
