<?php

namespace App\Services\Study;

use App\Enums\EngagementLevel;
use App\Enums\StudyActivity;
use App\Models\EngagementLog;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PASINDU — productivity analytics: daily and weekly study time, session
 * history, progress against goals, streaks, productivity statistics,
 * preferred study periods, planned vs actual and engagement history.
 */
class StudyAnalyticsService
{
    public function __construct(private readonly StudyInsightEngine $insights) {}

    /** @return array<string, mixed> */
    public function analytics(User $user, int $days = 30): array
    {
        $days = max(7, min(365, $days));
        $from = today()->subDays($days - 1);
        $settings = $user->settingsOrDefault();

        $sessions = $user->studySessions()->completed()
            ->where('started_at', '>=', today()->subDays(max($days, 56) - 1))
            ->with('module:id,code,name,color')
            ->get();
        $inRange = $sessions->filter(fn (StudySession $s) => $s->started_at->greaterThanOrEqualTo($from));

        $daily = $this->daily($user, $inRange, $from, $days);
        $weekly = $this->weekly($sessions, $settings->weekly_goal_minutes);
        $heatmap = $this->heatmap($inRange);
        $streak = $this->streaks($user);

        $todayMinutes = (int) $sessions->filter(fn ($s) => $s->started_at->isToday())->sum('actual_minutes')
            + $this->liveMinutes($user);
        $weekMinutes = (int) $sessions->filter(fn ($s) => $s->started_at->greaterThanOrEqualTo(today()->startOfWeek()))->sum('actual_minutes')
            + $this->liveMinutes($user);

        $planned = (int) $inRange->sum('planned_minutes');
        $actual = (int) $inRange->sum('actual_minutes');
        $engagementLogs = EngagementLog::query()
            ->where('user_id', $user->id)
            ->where('logged_at', '>=', $from)
            ->get(['score', 'level', 'logged_at', 'source']);

        $stats = [
            'total_minutes' => $actual,
            'sessions' => $inRange->count(),
            'average_session_minutes' => $inRange->count() ? (int) round($actual / $inRange->count()) : 0,
            'average_engagement' => $inRange->whereNotNull('avg_engagement')->count() ? (int) round($inRange->whereNotNull('avg_engagement')->avg('avg_engagement')) : null,
            'average_focus_score' => $inRange->whereNotNull('focus_score')->count() ? (int) round($inRange->whereNotNull('focus_score')->avg('focus_score')) : null,
            'completion_rate' => $planned > 0 ? (int) round($actual / $planned * 100) : null,
            'longest_session_minutes' => (int) $inRange->max('actual_minutes'),
            'active_days' => $inRange->groupBy(fn ($s) => $s->started_at->toDateString())->count(),
            'best_day' => collect($daily)->sortByDesc('minutes')->first(),
            'total_breaks' => (int) $inRange->sum('break_count'),
            'total_pauses' => (int) $inRange->sum('pause_count'),
        ];

        $analytics = [
            'range_days' => $days,
            'today' => ['minutes' => $todayMinutes, 'goal' => $settings->daily_goal_minutes, 'percent' => $this->percent($todayMinutes, $settings->daily_goal_minutes)],
            'week' => ['minutes' => $weekMinutes, 'goal' => $settings->weekly_goal_minutes, 'percent' => $this->percent($weekMinutes, $settings->weekly_goal_minutes)],
            'stats' => $stats,
            'streak' => $streak,
            'daily' => $daily,
            'weekly' => $weekly,
            'by_module' => $this->byModule($inRange),
            'by_activity' => $this->byActivity($inRange),
            'heatmap' => $heatmap,
            'preferred_periods' => $this->preferredPeriods($inRange),
            'planned_vs_actual' => [
                'planned_minutes' => $planned,
                'actual_minutes' => $actual,
                'difference_minutes' => $actual - $planned,
                'daily' => array_map(fn ($d) => [
                    'date' => $d['date'],
                    'label' => $d['label'],
                    'planned' => $d['plan_minutes'] ?: $d['session_planned'],
                    'actual' => $d['minutes'],
                ], array_slice($daily, -14)),
            ],
            'engagement' => [
                'daily' => array_map(fn ($d) => ['date' => $d['date'], 'label' => $d['label'], 'score' => $d['avg_engagement']], $daily),
                'distribution' => [
                    'high' => $engagementLogs->where('level', EngagementLevel::High)->count(),
                    'moderate' => $engagementLogs->where('level', EngagementLevel::Moderate)->count(),
                    'low' => $engagementLogs->where('level', EngagementLevel::Low)->count(),
                ],
                'manual_reports' => $engagementLogs->where('source', 'manual')->count(),
            ],
        ];

        $analytics['insights'] = $this->insights->generate($user, $analytics, $inRange);

        return $analytics;
    }

    /** @return list<array<string, mixed>> */
    private function daily(User $user, Collection $sessions, Carbon $from, int $days): array
    {
        $plans = $user->studyPlans()->where('plan_date', '>=', $from->toDateString())->get(['plan_date', 'planned_minutes'])
            ->groupBy(fn ($p) => $p->plan_date->toDateString())
            ->map(fn ($group) => (int) $group->sum('planned_minutes'));
        $byDay = $sessions->groupBy(fn ($s) => $s->started_at->toDateString());

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();
            $group = $byDay->get($key, collect());
            $engagement = $group->whereNotNull('avg_engagement');
            $out[] = [
                'date' => $key,
                'label' => $days <= 14 ? $date->format('D') : $date->format('d M'),
                'minutes' => (int) $group->sum('actual_minutes'),
                'sessions' => $group->count(),
                'session_planned' => (int) $group->sum('planned_minutes'),
                'plan_minutes' => $plans->get($key, 0),
                'avg_engagement' => $engagement->count() ? (int) round($engagement->avg('avg_engagement')) : null,
            ];
        }

        return $out;
    }

    /** @return list<array{week_start: string, label: string, minutes: int, sessions: int, goal: int}> */
    private function weekly(Collection $sessions, int $goal): array
    {
        $out = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = today()->startOfWeek()->subWeeks($i);
            $end = $start->copy()->endOfWeek();
            $group = $sessions->filter(fn ($s) => $s->started_at->between($start, $end));
            $out[] = [
                'week_start' => $start->toDateString(),
                'label' => $i === 0 ? 'This week' : $start->format('d M'),
                'minutes' => (int) $group->sum('actual_minutes'),
                'sessions' => $group->count(),
                'goal' => $goal,
            ];
        }

        return $out;
    }

    /** Weekday × hour minutes (starts are spread across the hours a session spans). */
    private function heatmap(Collection $sessions): array
    {
        $minutes = array_fill(0, 7, array_fill(0, 24, 0));
        $engagement = array_fill(0, 7, array_fill(0, 24, []));

        foreach ($sessions as $session) {
            $cursor = $session->started_at->copy();
            $left = $session->actual_minutes;
            while ($left > 0) {
                $slot = min($left, 60 - $cursor->minute);
                $day = ($cursor->dayOfWeek + 6) % 7; // Monday = 0
                $minutes[$day][$cursor->hour] += $slot;
                if ($session->avg_engagement !== null) {
                    $engagement[$day][$cursor->hour][] = $session->avg_engagement;
                }
                $left -= $slot;
                $cursor->addMinutes($slot);
            }
        }

        $cells = [];
        foreach ($minutes as $day => $hours) {
            foreach ($hours as $hour => $value) {
                if ($value > 0) {
                    $scores = $engagement[$day][$hour];
                    $cells[] = [
                        'day' => $day,
                        'hour' => $hour,
                        'minutes' => $value,
                        'engagement' => $scores ? (int) round(array_sum($scores) / count($scores)) : null,
                    ];
                }
            }
        }

        return ['days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 'cells' => $cells];
    }

    /** Two-hour windows ranked by focused minutes weighted by engagement. */
    private function preferredPeriods(Collection $sessions): array
    {
        $windows = [];
        foreach ($sessions as $session) {
            $start = intdiv($session->started_at->hour, 2) * 2;
            $windows[$start]['minutes'] = ($windows[$start]['minutes'] ?? 0) + $session->actual_minutes;
            $windows[$start]['sessions'] = ($windows[$start]['sessions'] ?? 0) + 1;
            if ($session->avg_engagement !== null) {
                $windows[$start]['engagement'][] = $session->avg_engagement;
            }
        }

        $ranked = [];
        foreach ($windows as $start => $data) {
            $engagement = ! empty($data['engagement']) ? array_sum($data['engagement']) / count($data['engagement']) : 60;
            $ranked[] = [
                'start_hour' => $start,
                'label' => sprintf('%02d:00–%02d:00', $start, ($start + 2) % 24),
                'minutes' => $data['minutes'],
                'sessions' => $data['sessions'],
                'engagement' => (int) round($engagement),
                'score' => round($data['minutes'] * ($engagement / 100), 1),
            ];
        }
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        $weekdays = $sessions->groupBy(fn ($s) => $s->started_at->format('l'))
            ->map(fn ($g, $day) => ['day' => $day, 'minutes' => (int) $g->sum('actual_minutes'), 'engagement' => (int) round($g->avg('avg_engagement') ?? 0)])
            ->sortByDesc('minutes')->values();

        return ['windows' => array_slice($ranked, 0, 3), 'best_weekday' => $weekdays->first()];
    }

    private function byModule(Collection $sessions): array
    {
        return $sessions->groupBy(fn ($s) => $s->module_id ?? 0)
            ->map(fn ($group) => [
                'module_id' => $group->first()->module_id,
                'code' => $group->first()->module?->code ?? 'General',
                'name' => $group->first()->module?->name ?? 'No module',
                'color' => $group->first()->module?->color ?? '#94a3b8',
                'minutes' => (int) $group->sum('actual_minutes'),
                'sessions' => $group->count(),
            ])->sortByDesc('minutes')->values()->all();
    }

    private function byActivity(Collection $sessions): array
    {
        return $sessions->groupBy(fn ($s) => $s->activity->value)
            ->map(fn ($group, $activity) => [
                'activity' => $activity,
                'label' => StudyActivity::from($activity)->label(),
                'minutes' => (int) $group->sum('actual_minutes'),
                'sessions' => $group->count(),
                'engagement' => $group->whereNotNull('avg_engagement')->count() ? (int) round($group->avg('avg_engagement')) : null,
            ])->sortByDesc('minutes')->values()->all();
    }

    /** @return array{current: int, best: int, studied_today: bool} */
    public function streaks(User $user): array
    {
        $days = $user->studySessions()->completed()
            ->where('started_at', '>=', today()->subDays(365))
            ->where('actual_minutes', '>=', 5)
            ->pluck('started_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()->sort()->values();

        $set = $days->flip();
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $run = $previous && Carbon::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        $current = 0;
        $cursor = today();
        $studiedToday = $set->has($cursor->toDateString());
        if (! $studiedToday) {
            $cursor->subDay();
        }
        while ($set->has($cursor->toDateString())) {
            $current++;
            $cursor->subDay();
        }

        return ['current' => $current, 'best' => $best, 'studied_today' => $studiedToday];
    }

    /** Minutes of a session that is still running (counted toward today). */
    private function liveMinutes(User $user): int
    {
        $live = $user->studySessions()->live()->first();

        return $live ? (int) floor($live->elapsedFocusSeconds() / 60) : 0;
    }

    private function percent(int $value, int $goal): int
    {
        return $goal > 0 ? (int) min(100, round($value / $goal * 100)) : 0;
    }
}
