<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\RiskCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * JITHMI — Assignment & Deadline Risk Management.
 * CRUD for assignments plus the risk engine endpoints: ranked workload, risk
 * recalculation, daily-hour recommendation and what-if scenario planning.
 *
 * Integration:
 *   • academic_date_id links a deadline extracted by KAVISHKA.
 *   • The "start session" recommendation hands the top task to PASINDU.
 *   • PASINDU study sessions feed done_hours/progress back, then risk recalcs.
 */
class AssignmentController extends Controller
{
    public function __construct(private readonly RiskCalculator $risk) {}

    /** GET /api/assignments?status=upcoming|overdue|completed */
    public function index(Request $request): JsonResponse
    {
        $assignments = $request->user()->assignments()->with('module')->get();

        $payload = $assignments->map(function (Assignment $a) {
            $assessment = $this->risk->assess($a);

            return [
                'assignment' => $a,
                'risk'       => $assessment,
                'status'     => $this->statusOf($a),
            ];
        });

        if ($request->filled('status')) {
            $payload = $payload->where('status', $request->string('status')->toString());
        }

        // Highest risk first, then nearest deadline.
        $payload = $payload->sortBy([
            fn ($x, $y) => $y['risk']['score'] <=> $x['risk']['score'],
            fn ($x, $y) => strcmp((string) $x['assignment']->deadline, (string) $y['assignment']->deadline),
        ])->values();

        return response()->json($payload);
    }

    /** GET /api/assignments/{assignment} */
    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeOwner($request, $assignment);

        return response()->json([
            'assignment' => $assignment->load(['module', 'academicDate', 'riskAssessments' => fn ($q) => $q->latest('calculated_at')->limit(5)]),
            'risk'       => $this->risk->assess($assignment),
            'status'     => $this->statusOf($assignment),
        ]);
    }

    /** POST /api/assignments */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateAssignment($request);

        $assignment = $request->user()->assignments()->create($data + [
            'progress'  => $data['progress'] ?? 0,
            'completed' => $data['completed'] ?? false,
        ]);

        // Persist the first risk snapshot.
        $assessment = $this->risk->recalculate($assignment);

        return response()->json([
            'assignment' => $assignment->load('module'),
            'risk'       => $this->risk->assess($assignment),
            'assessment' => $assessment,
        ], 201);
    }

    /** PUT /api/assignments/{assignment} */
    public function update(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeOwner($request, $assignment);

        $data = $this->validateAssignment($request, updating: true);
        $assignment->update($data);

        // Recompute + persist risk whenever workload/progress/deadline changes.
        $assessment = $this->risk->recalculate($assignment);

        return response()->json([
            'assignment' => $assignment->fresh('module'),
            'risk'       => $this->risk->assess($assignment),
            'assessment' => $assessment,
        ]);
    }

    /** DELETE /api/assignments/{assignment} */
    public function destroy(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeOwner($request, $assignment);
        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted']);
    }

    /** GET /api/assignments/rank — priority ranking across all active work. */
    public function rank(Request $request): JsonResponse
    {
        $assignments = $request->user()->assignments()
            ->with('module')
            ->where('completed', false)
            ->get();

        $ranked = collect($this->risk->rank($assignments))->map(function ($row, $i) {
            return ['rank' => $i + 1, 'assignment' => $row['assignment'], 'risk' => $row['risk']];
        });

        return response()->json($ranked);
    }

    /**
     * GET /api/assignments/recommendation
     * The single most urgent task + a suggested daily study plan, ready to hand
     * off to PASINDU ("Start study session").
     */
    public function recommendation(Request $request): JsonResponse
    {
        $assignments = $request->user()->assignments()->with('module')->where('completed', false)->get();
        $ranked = $this->risk->rank($assignments);

        if (empty($ranked)) {
            return response()->json(['message' => 'No active assignments — you are all caught up.']);
        }

        $top = $ranked[0];
        /** @var Assignment $assignment */
        $assignment = $top['assignment'];
        $riskData = $top['risk'];

        $remainingHours = max((float) $assignment->est_hours - (float) $assignment->done_hours, 0.0);
        $days = max($riskData['days'], 1);
        $suggestedPerDay = round(min($remainingHours / $days, 6.0), 1);
        $suggestedMinutes = (int) round($suggestedPerDay * 60);

        return response()->json([
            'assignment'        => $assignment,
            'risk'              => $riskData,
            'remaining_hours'   => round($remainingHours, 1),
            'days_left'         => $riskData['days'],
            'suggested_per_day' => $suggestedPerDay,
            'suggested_minutes' => $suggestedMinutes,
            'message'           => sprintf(
                'Focus on "%s" — %s. Aim for ~%.1fh/day over the next %d day(s).',
                $assignment->title,
                strtolower($riskData['level']).' risk',
                $suggestedPerDay,
                $days
            ),
        ]);
    }

    /**
     * POST /api/assignments/whatif
     * Simulate risk without persisting: change deadline / est_hours / progress /
     * daily capacity and see the resulting score and level.
     */
    public function whatIf(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignment_id' => ['nullable', 'integer', 'exists:assignments,id'],
            'deadline'      => ['nullable', 'date'],
            'est_hours'     => ['nullable', 'numeric', 'min:0', 'max:500'],
            'done_hours'    => ['nullable', 'numeric', 'min:0', 'max:500'],
            'progress'      => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Start from an existing assignment or a blank slate, then overlay inputs.
        $assignment = isset($data['assignment_id'])
            ? Assignment::findOrFail($data['assignment_id'])
            : new Assignment(['deadline' => Carbon::today()->addWeek(), 'est_hours' => 0, 'done_hours' => 0, 'progress' => 0]);

        $assignment->deadline   = $data['deadline'] ?? $assignment->deadline;
        $assignment->est_hours  = $data['est_hours'] ?? $assignment->est_hours;
        $assignment->done_hours = $data['done_hours'] ?? $assignment->done_hours;
        $assignment->progress   = $data['progress'] ?? $assignment->progress;

        return response()->json([
            'simulated' => [
                'deadline'   => Carbon::parse($assignment->deadline)->toDateString(),
                'est_hours'  => (float) $assignment->est_hours,
                'done_hours' => (float) $assignment->done_hours,
                'progress'   => (int) $assignment->progress,
            ],
            'risk' => $this->risk->assess($assignment),
            'note' => 'Scenario only — nothing was saved.',
        ]);
    }

    /** @return array<string,mixed> */
    private function validateAssignment(Request $request, bool $updating = false): array
    {
        $rule = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title'            => [$rule, 'string', 'max:200'],
            'module_id'        => ['nullable', 'integer', 'exists:modules,id'],
            'academic_date_id' => ['nullable', 'integer', 'exists:academic_dates,id'],
            'deadline'         => [$rule, 'date'],
            'priority'         => ['nullable', 'in:Low,Medium,High'],
            'est_hours'        => ['nullable', 'numeric', 'min:0', 'max:500'],
            'done_hours'       => ['nullable', 'numeric', 'min:0', 'max:500'],
            'progress'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'completed'        => ['nullable', 'boolean'],
        ]);
    }

    private function statusOf(Assignment $a): string
    {
        if ($a->completed) {
            return 'completed';
        }

        return Carbon::parse($a->deadline)->isPast() ? 'overdue' : 'upcoming';
    }

    private function authorizeOwner(Request $request, Assignment $assignment): void
    {
        abort_unless($assignment->user_id === $request->user()->id, 403, 'This assignment belongs to another user.');
    }
}
