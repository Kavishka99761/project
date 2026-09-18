<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EngagementLog;
use App\Models\StudySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * PASINDU — Study Session & Engagement.
 * Start / pause / resume / stop a focus session, log engagement, and produce
 * productivity analytics (weekly minutes, planned-vs-actual, streaks).
 *
 * Integration:
 *   • A session may carry assignment_id  -> recommended task from JITHMI.
 *   • A session may carry document_id    -> material supplied by BETHMI.
 *   • On stop, the linked assignment's done_hours/progress are advanced (JITHMI).
 */
class StudySessionController extends Controller
{
    /** GET /api/study/sessions?from=&to= */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->studySessions()
            ->with(['module', 'assignment:id,title', 'document:id,title'])
            ->latest('started_at');

        if ($request->filled('from')) {
            $query->whereDate('started_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('started_at', '<=', $request->date('to'));
        }

        return response()->json($query->limit(100)->get());
    }

    /** GET /api/study/current — the active (or paused) session, if any. */
    public function current(Request $request): JsonResponse
    {
        $session = $request->user()->studySessions()
            ->with(['module', 'assignment:id,title', 'document:id,title'])
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();

        return response()->json($session);
    }

    /** POST /api/study/sessions — start a new focus session. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_id'       => ['nullable', 'integer', 'exists:modules,id'],
            'assignment_id'   => ['nullable', 'integer', 'exists:assignments,id'],
            'document_id'     => ['nullable', 'integer', 'exists:documents,id'],
            'activity'        => ['nullable', 'string', 'max:80'],
            'planned_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        // Only one live session at a time — auto-complete any stray active one.
        $request->user()->studySessions()
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'completed', 'ended_at' => now()]);

        $session = $request->user()->studySessions()->create([
            'module_id'       => $data['module_id'] ?? null,
            'assignment_id'   => $data['assignment_id'] ?? null,
            'document_id'     => $data['document_id'] ?? null,
            'activity'        => $data['activity'] ?? 'Revision',
            'planned_minutes' => $data['planned_minutes'] ?? 25,
            'actual_minutes'  => 0,
            'status'          => 'active',
            'started_at'      => now(),
        ]);

        return response()->json($session->load(['module', 'assignment:id,title', 'document:id,title']), 201);
    }

    /**
     * PATCH /api/study/sessions/{session}
     * Transition the timer: pause | resume | stop. On stop, actual_minutes is
     * finalised and any linked assignment progress is advanced.
     */
    public function update(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        $data = $request->validate([
            'status'         => ['sometimes', 'in:active,paused,completed'],
            'actual_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);

        if (isset($data['actual_minutes'])) {
            $session->actual_minutes = $data['actual_minutes'];
        }

        $status = $data['status'] ?? $session->status;
        $session->status = $status === 'active' ? 'active' : $status; // resume => active

        if ($status === 'completed') {
            // Derive elapsed minutes if the client didn't send an explicit value.
            if (! isset($data['actual_minutes']) && $session->started_at) {
                $session->actual_minutes = (int) $session->started_at->diffInMinutes(now());
            }
            $session->ended_at = now();
            $this->applyProgressToAssignment($session);
        }

        $session->save();

        return response()->json($session->fresh(['module', 'assignment:id,title', 'document:id,title']));
    }

    /** POST /api/study/sessions/{session}/engagement — log a concentration reading. */
    public function logEngagement(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeOwner($request, $session);

        $data = $request->validate([
            'level'   => ['required', 'in:Low,Moderate,Good'],
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'source'  => ['nullable', 'in:auto,manual'],
        ]);

        $log = EngagementLog::create([
            'user_id'          => $request->user()->id,
            'study_session_id' => $session->id,
            'level'            => $data['level'],
            'percent'          => $data['percent'],
            'source'           => $data['source'] ?? 'manual',
            'logged_at'        => now(),
        ]);

        return response()->json($log, 201);
    }

    /**
     * GET /api/study/analytics
     * Weekly minutes, planned-vs-actual, average engagement and current streak.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = 7;
        $start = Carbon::today()->subDays($days - 1);

        $sessions = $user->studySessions()
            ->where('started_at', '>=', $start->copy()->startOfDay())
            ->get();

        $weekly = [];
        $planned = 0;
        $actual = 0;
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $minutes = $sessions->where('started_at', '>=', $date->copy()->startOfDay())
                ->where('started_at', '<=', $date->copy()->endOfDay())
                ->sum('actual_minutes');
            $weekly[] = ['date' => $key, 'label' => $date->format('D'), 'minutes' => (int) $minutes];
        }

        $completed = $sessions->where('status', 'completed');
        $planned = (int) $completed->sum('planned_minutes');
        $actual = (int) $completed->sum('actual_minutes');

        $avgEngagement = (int) round(
            EngagementLog::where('user_id', $user->id)
                ->where('logged_at', '>=', $start->copy()->startOfDay())
                ->avg('percent') ?? 0
        );

        return response()->json([
            'weekly'            => $weekly,
            'planned_minutes'   => $planned,
            'actual_minutes'    => $actual,
            'completion_rate'   => $planned > 0 ? (int) round(($actual / $planned) * 100) : 0,
            'sessions_count'    => $completed->count(),
            'average_engagement' => $avgEngagement,
            'streak_days'       => $this->streak($user->studySessions()->where('status', 'completed')->pluck('started_at')->toArray()),
            'daily_target'      => $user->daily_target_minutes,
        ]);
    }

    /** Advance the linked assignment (JITHMI) using the minutes just studied. */
    private function applyProgressToAssignment(StudySession $session): void
    {
        if (! $session->assignment_id) {
            return;
        }
        $assignment = $session->assignment;
        if (! $assignment || $assignment->completed) {
            return;
        }

        $hours = round($session->actual_minutes / 60, 1);
        $assignment->done_hours = round((float) $assignment->done_hours + $hours, 1);

        $est = max((float) $assignment->est_hours, 0.1);
        $assignment->progress = (int) min(100, round(((float) $assignment->done_hours / $est) * 100));
        $assignment->save();
    }

    /** Consecutive-day study streak ending today (or yesterday). */
    private function streak(array $startedAts): int
    {
        $days = collect($startedAts)->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())->unique()->sortDesc()->values();
        if ($days->isEmpty()) {
            return 0;
        }
        $streak = 0;
        $cursor = \Illuminate\Support\Carbon::today();
        if ($days->first() === $cursor->copy()->subDay()->toDateString()) {
            $cursor->subDay();
        }
        foreach ($days as $day) {
            if ($day === $cursor->toDateString()) {
                $streak++;
                $cursor->subDay();
            } elseif ($day < $cursor->toDateString()) {
                break;
            }
        }

        return $streak;
    }

    private function authorizeOwner(Request $request, StudySession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 403, 'This session belongs to another user.');
    }
}
