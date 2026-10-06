<?php

namespace App\Http\Controllers\Api\V1\Assignments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\WhatIfRequest;
use App\Models\Assignment;
use App\Services\Assignments\AssignmentDashboard;
use App\Services\Assignments\AssignmentService;
use App\Services\Assignments\RiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JITHMI — Workload & Risk Management: risk dashboard, priority ranking,
 * "work on this first", daily study hours, 7-day plan, what-if scenarios
 * and on-demand recalculation.
 */
class RiskController extends Controller
{
    public function __construct(
        private readonly RiskService $risk,
        private readonly AssignmentService $assignments,
        private readonly AssignmentDashboard $dashboard,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->build($request->user()));
    }

    /** All active assignments ranked by urgency, with full risk breakdown. */
    public function ranking(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $this->risk->activeAssignments($user);
        $assessment = $this->risk->assess($user, $active);

        $rows = [];
        foreach ($assessment as $id => $result) {
            $rows[] = $this->dashboard->row($active->firstWhere('id', $id), $result) + [
                'reasons' => $result['reasons'],
                'probability' => $result['probability'],
                'load_ratio' => $result['load_ratio'],
                'available_hours' => $result['available_hours'],
                'competing_hours' => $result['competing_hours'],
            ];
        }

        return response()->json($rows);
    }

    public function recommendation(Request $request): JsonResponse
    {
        $dashboard = $this->dashboard->build($request->user());

        return response()->json([
            'recommendation' => $dashboard['recommendation'],
            'top' => $dashboard['priority'][0] ?? null,
            'recommended_daily_hours' => $dashboard['workload']['recommended_daily_hours'],
            'average_available_per_day' => $dashboard['workload']['average_available_per_day'],
            'plan_today' => $dashboard['plan'][0] ?? null,
        ]);
    }

    public function workload(Request $request): JsonResponse
    {
        return response()->json($this->assignments->workload($request->user()));
    }

    public function plan(Request $request): JsonResponse
    {
        $days = max(1, min(14, $request->integer('days', 7)));
        $user = $request->user();
        $active = $this->risk->activeAssignments($user);

        return response()->json($this->risk->engine()->plan($active, $this->risk->assess($user, $active), $this->risk->availability($user), $days));
    }

    /** Hypothetical scenario — nothing is saved (the run itself is audited). */
    public function whatIf(WhatIfRequest $request): JsonResponse
    {
        $assignment = Assignment::query()->ownedBy($request->user())->findOrFail($request->validated('assignment_id'));
        $result = $this->risk->whatIf($request->user(), $assignment, $request->safe()->except('assignment_id'));

        $before = $result['before']['score'] ?? null;
        $after = $result['after']['score'] ?? null;
        activity()->action('assignments.what_if')->describe(sprintf('Ran a what-if scenario for “%s”: risk %s%% → %s%%', $assignment->title, $before ?? '–', $after ?? '–'))
            ->on($assignment)->with(['scenario' => $request->safe()->except('assignment_id')]);

        $level = fn ($r) => $r ? array_merge($r, ['level' => $r['level']->value]) : null;

        return response()->json([
            'before' => $level($result['before']),
            'after' => $level($result['after']),
            'delta' => $before !== null && $after !== null ? $after - $before : null,
            'affected' => array_map(fn ($a) => array_merge($a, ['before_level' => $a['before_level']->value, 'after_level' => $a['after_level']->value]), $result['affected']),
            'plan' => $result['plan'],
            'recommended_daily_hours' => $result['recommended_daily_hours'],
        ]);
    }

    public function recalculate(Request $request): JsonResponse
    {
        $results = $this->risk->recalculate($request->user(), 'manual');
        activity()->action('assignments.risk_recalculated')->describe('Recalculated deadline risk for '.count($results).' assignment(s)');

        return response()->json(['message' => 'Risk recalculated.', 'assignments' => count($results)]);
    }

    /** Risk history for one assignment (chart). */
    public function history(Assignment $assignment): JsonResponse
    {
        return response()->json($assignment->riskAssessments()->orderBy('calculated_at')->get()->map(fn ($r) => [
            'score' => $r->score,
            'level' => $r->level->value,
            'probability' => $r->probability,
            'trigger' => $r->trigger,
            'reasons' => $r->reasons,
            'remaining_hours' => $r->remaining_hours,
            'available_hours' => $r->available_hours,
            'calculated_at' => $r->calculated_at->toIso8601String(),
        ]));
    }
}
