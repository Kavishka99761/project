<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EngagementLog;
use App\Services\RiskCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Common Platform Layer — the Overview hub.
 * Aggregates a single "day at a glance" payload from all four modules so the
 * central dashboard can render without calling each module separately.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly RiskCalculator $risk) {}

    /** GET /api/dashboard */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        // --- Pasindu: today's study -------------------------------------
        $todaySessions = $user->studySessions()->whereDate('started_at', $today)->get();
        $studiedToday = (int) $todaySessions->sum('actual_minutes');
        $activeSession = $user->studySessions()->whereIn('status', ['active', 'paused'])->latest('started_at')->first();
        $avgEngagement = (int) round(
            EngagementLog::where('user_id', $user->id)->whereDate('logged_at', $today)->avg('percent') ?? 0
        );

        // --- Jithmi: risk overview --------------------------------------
        $assignments = $user->assignments()->with('module')->get();
        $active = $assignments->where('completed', false);
        $riskCounts = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Critical' => 0];
        $upcomingDeadlines = [];
        foreach ($active as $assignment) {
            $assessment = $this->risk->assess($assignment);
            $riskCounts[$assessment['level']] = ($riskCounts[$assessment['level']] ?? 0) + 1;
            if ($assignment->deadline && Carbon::parse($assignment->deadline)->diffInDays($today, false) <= 7) {
                $upcomingDeadlines[] = [
                    'id'       => $assignment->id,
                    'title'    => $assignment->title,
                    'deadline' => Carbon::parse($assignment->deadline)->toDateString(),
                    'days_left' => $assessment['days'],
                    'level'    => $assessment['level'],
                    'score'    => $assessment['score'],
                ];
            }
        }
        usort($upcomingDeadlines, fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        // --- Kavishka: next academic dates ------------------------------
        $dates = $user->academicDates()
            ->whereDate('event_date', '>=', $today)
            ->orderBy('event_date')
            ->limit(5)
            ->get(['id', 'title', 'event_date', 'type'])
            ->map(fn ($d) => [
                'id'         => $d->id,
                'title'      => $d->title,
                'event_date' => Carbon::parse($d->event_date)->toDateString(),
                'type'       => $d->type,
                'days_left'  => (int) $today->diffInDays($d->event_date, false),
            ]);

        // --- Counts for the module cards --------------------------------
        $counts = [
            'documents'   => $user->documents()->count(),
            'assignments' => $active->count(),
            'overdue'     => $active->filter(fn ($a) => Carbon::parse($a->deadline)->isPast())->count(),
        ];

        // Top assignments with risk for the dashboard priority list
        $topAssignments = $active->map(function ($a) {
            $r = $this->risk->assess($a);
            return [
                'id'       => $a->id,
                'title'    => $a->title,
                'module'   => $a->module ? ['name' => $a->module->name] : null,
                'deadline' => $a->deadline ? Carbon::parse($a->deadline)->toDateString() : null,
                'progress' => (int) $a->progress,
                'risk'     => ['level' => $r['level'], 'score' => $r['score']],
            ];
        })->sortByDesc(fn ($x) => $x['risk']['score'])->values()->take(4);

        // Streak calculation
        $allSessionDates = $user->studySessions()
            ->where('status', 'completed')
            ->pluck('started_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()->sortDesc()->values();
        $streak = 0;
        $cursor = Carbon::today();
        foreach ($allSessionDates as $day) {
            if ($day === $cursor->toDateString()) { $streak++; $cursor->subDay(); }
            elseif ($day < $cursor->toDateString()) { break; }
        }

        // Highest risk score across active assignments
        $topRiskScore = $active->map(fn ($a) => $this->risk->assess($a)['score'])->max() ?? 0;

        return response()->json([
            // Flat fields the React dashboard expects
            'documents_count'        => $counts['documents'],
            'active_assignments'     => $counts['assignments'],
            'risk_score'             => (int) $topRiskScore,
            'upcoming_dates'         => $dates->count(),
            'streak_days'            => $streak,
            'sessions_today'         => $todaySessions->where('status', 'completed')->count(),
            'studied_today_minutes'  => $studiedToday,
            'daily_target_minutes'   => (int) $user->daily_target_minutes,
            'top_assignments'        => $topAssignments->values(),
            'upcoming_events'        => $dates->values(),
            // Detailed sub-objects (used by other consumers)
            'greeting'               => $this->greeting(),
            'user'                   => $user->only(['id', 'name', 'email', 'program', 'daily_target_minutes', 'dark_mode']),
            'modules'                => $user->modules()->orderBy('code')->get(),
            'study_today'            => [
                'minutes'            => $studiedToday,
                'target'             => $user->daily_target_minutes,
                'percent'            => $user->daily_target_minutes > 0 ? (int) min(100, round(($studiedToday / $user->daily_target_minutes) * 100)) : 0,
                'average_engagement' => $avgEngagement,
                'active_session'     => $activeSession,
            ],
            'risk_summary'           => $riskCounts,
            'upcoming_deadlines'     => array_slice($upcomingDeadlines, 0, 5),
            'academic_dates'         => $dates,
            'counts'                 => $counts,
        ]);
    }

    private function greeting(): string
    {
        $hour = (int) Carbon::now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
    }
}
