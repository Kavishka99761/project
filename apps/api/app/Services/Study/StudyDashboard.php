<?php

namespace App\Services\Study;

use App\Enums\AssignmentStatus;
use App\Enums\StudyActivity;
use App\Models\User;
use App\Services\Assignments\RiskService;
use Illuminate\Support\Carbon;

/**
 * PASINDU — Study Dashboard, including the cross-module "recommended next
 * session": JITHMI's top-priority assignment + BETHMI's material for that
 * module + the student's best time and personal focus length.
 */
class StudyDashboard
{
    public function __construct(
        private readonly StudyAnalyticsService $analytics,
        private readonly BreakAdvisor $advisor,
        private readonly RiskService $risk,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $analytics = $this->analytics->analytics($user, 14);
        $live = $user->studySessions()->live()->with(['module', 'document', 'assignment'])->latest('started_at')->first();

        return [
            'today' => $analytics['today'],
            'week' => $analytics['week'],
            'streak' => $analytics['streak'],
            'stats' => $analytics['stats'],
            'daily' => array_slice($analytics['daily'], -7),
            'insights' => array_slice($analytics['insights'], 0, 4),
            'preferred_periods' => $analytics['preferred_periods'],
            'focus_period' => $this->advisor->focusPeriod($user),
            'live_session_id' => $live?->id,
            'recommendation' => $this->recommendation($user, $analytics),
            'recent_sessions' => $user->studySessions()->completed()->with('module:id,code,color')->latest('started_at')->limit(6)->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'activity' => $s->activity->label(), 'module' => $s->module?->code, 'color' => $s->module?->color,
                    'started_at' => $s->started_at->toIso8601String(), 'minutes' => $s->actual_minutes, 'planned' => $s->planned_minutes,
                    'engagement' => $s->avg_engagement, 'focus_score' => $s->focus_score,
                ]),
            'plans_today' => $user->studyPlans()->with('module:id,code,color')->whereDate('plan_date', today())->get()
                ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'minutes' => $p->planned_minutes, 'module' => $p->module?->code, 'done' => $p->is_done]),
        ];
    }

    /** @return array<string, mixed>|null */
    public function recommendation(User $user, array $analytics): ?array
    {
        $focus = $this->advisor->focusPeriod($user);
        $active = $this->risk->activeAssignments($user);
        $assessment = $active->isNotEmpty() ? $this->risk->assess($user, $active) : [];
        $topId = array_key_first($assessment);
        $assignment = $topId ? $active->firstWhere('id', $topId) : null;

        $moduleId = $assignment?->module_id;
        $document = $user->documents()
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->orderByRaw('CASE WHEN last_opened_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->first(['id', 'title', 'module_id', 'kind']);

        $window = $analytics['preferred_periods']['windows'][0] ?? null;
        $when = 'now';
        if ($window) {
            $start = Carbon::today()->setTime($window['start_hour'], 0);
            $when = now()->between($start, $start->copy()->addHours(2)) ? 'now — this is your peak focus time' : $window['label'];
        }

        if (! $assignment && ! $document) {
            return null;
        }

        return [
            'assignment' => $assignment ? [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'risk_score' => $assessment[$assignment->id]['score'],
                'risk_level' => $assessment[$assignment->id]['level']->value,
                'deadline' => $assignment->deadline->toIso8601String(),
            ] : null,
            'module' => $assignment?->module ? ['id' => $assignment->module->id, 'code' => $assignment->module->code, 'name' => $assignment->module->name, 'color' => $assignment->module->color] : null,
            'document' => $document ? ['id' => $document->id, 'title' => $document->title, 'kind' => $document->kind->value] : null,
            'activity' => $assignment ? StudyActivity::Assignment->value : StudyActivity::Revision->value,
            'planned_minutes' => $focus['minutes'],
            'when' => $when,
            'reason' => $assignment
                ? sprintf('“%s” has the highest deadline risk (%d%%).', $assignment->title, $assessment[$assignment->id]['score'])
                : 'Review your latest learning material while it is fresh.',
        ];
    }
}
