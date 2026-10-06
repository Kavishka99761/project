<?php

namespace App\Http\Controllers\Api\V1\Assignments;

use App\Enums\AssignmentPriority;
use App\Enums\AssignmentStatus;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\AssignmentRequest;
use App\Http\Requests\Assignments\CompleteRequest;
use App\Http\Requests\Assignments\ProgressRequest;
use App\Http\Resources\Assignments\AssignmentResource;
use App\Models\Assignment;
use App\Services\Assignments\AssignmentService;
use App\Services\Assignments\RiskService;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * JITHMI — Assignment Management: add / edit / delete / view, deadline,
 * priority, workload, progress, completion; upcoming / overdue / completed
 * views. Every change re-runs the deadline-risk model.
 */
class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly RiskService $risk,
    ) {}

    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'bucket' => ['nullable', Rule::in(['all', 'active', 'upcoming', 'overdue', 'completed'])],
            'module_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(AssignmentPriority::class)],
            'risk_level' => ['nullable', Rule::enum(RiskLevel::class)],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['rank', 'deadline', 'risk', 'progress', 'title', 'created'])],
        ]);

        $query = $request->user()->assignments()->with(['module', 'academicDate.document']);
        match ($filters['bucket'] ?? 'all') {
            'active' => $query->active(),
            'upcoming' => $query->upcoming(),
            'overdue' => $query->overdue(),
            'completed' => $query->where('status', AssignmentStatus::Completed),
            default => null,
        };
        $query->when($filters['module_id'] ?? null, fn ($q, $id) => $q->where('module_id', $id))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['risk_level'] ?? null, fn ($q, $l) => $q->where('risk_level', $l))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$search->escapeLike($text).'%')
                ->orWhere('description', 'like', '%'.$search->escapeLike($text).'%')));

        match ($filters['sort'] ?? 'rank') {
            'deadline' => $query->orderBy('deadline'),
            'risk' => $query->orderByDesc('risk_score')->orderBy('deadline'),
            'progress' => $query->orderByDesc('progress'),
            'title' => $query->orderBy('title'),
            'created' => $query->latest(),
            default => $query->orderByRaw('CASE WHEN priority_rank IS NULL THEN 1 ELSE 0 END')->orderBy('priority_rank')->orderBy('deadline'),
        };

        $assignments = $query->get();
        $study = $request->user()->studySessions()->completed()->whereNotNull('assignment_id')
            ->selectRaw('assignment_id, SUM(actual_minutes) AS minutes')->groupBy('assignment_id')->pluck('minutes', 'assignment_id');
        foreach ($assignments as $assignment) {
            $assignment->study_minutes = (int) ($study[$assignment->id] ?? 0);
        }
        AssignmentResource::$risk = $this->risk->assess($request->user());

        return AssignmentResource::collection($assignments);
    }

    public function store(AssignmentRequest $request): JsonResponse
    {
        $assignment = $this->assignments->create($request->user(), $request->validated());
        activity()->action('assignments.created')->describe(sprintf('Added assignment “%s” due %s (%s h, %s priority) — risk %d%%',
            $assignment->title, $assignment->deadline->format('d M Y H:i'), $assignment->estimated_hours, $assignment->priority->value, $assignment->risk_score))->on($assignment);

        AssignmentResource::$risk = $this->risk->assess($request->user());

        return AssignmentResource::make($assignment->load(['module', 'academicDate']))->response()->setStatusCode(201);
    }

    /** Full detail: live risk, progress timeline, risk history, submissions, study sessions. */
    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        AssignmentResource::$risk = $this->risk->assess($request->user());
        activity()->describe("Viewed assignment “{$assignment->title}”")->on($assignment);

        return response()->json([
            'assignment' => AssignmentResource::make($assignment->load(['module', 'academicDate.document'])),
            'progress_logs' => $assignment->progressLogs()->latest('created_at')->limit(50)->get()->map(fn ($log) => [
                'id' => $log->id, 'progress_before' => $log->progress_before, 'progress_after' => $log->progress_after,
                'hours_added' => $log->hours_added, 'completed_hours_after' => $log->completed_hours_after,
                'source' => $log->source, 'note' => $log->note, 'study_session_id' => $log->study_session_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'risk_history' => $assignment->riskAssessments()->latest('calculated_at')->limit(60)->get()->reverse()->values()->map(fn ($r) => [
                'score' => $r->score, 'level' => $r->level->value, 'trigger' => $r->trigger, 'remaining_hours' => $r->remaining_hours,
                'available_hours' => $r->available_hours, 'calculated_at' => $r->calculated_at->toIso8601String(),
            ]),
            'submissions' => $assignment->submissions()->latest('submitted_at')->get()->map(fn ($s) => [
                'id' => $s->id, 'submitted_at' => $s->submitted_at->toIso8601String(), 'deadline_at' => $s->deadline_at->toIso8601String(),
                'is_late' => $s->is_late, 'minutes_late' => $s->minutes_late, 'note' => $s->note,
            ]),
            'study_sessions' => $assignment->studySessions()->completed()->latest('started_at')->limit(20)->get()->map(fn ($s) => [
                'id' => $s->id, 'started_at' => $s->started_at->toIso8601String(), 'minutes' => $s->actual_minutes,
                'activity' => $s->activity->label(), 'engagement' => $s->avg_engagement,
            ]),
        ]);
    }

    public function update(AssignmentRequest $request, Assignment $assignment): AssignmentResource
    {
        $oldDeadline = $assignment->deadline->copy();
        $oldRisk = $assignment->risk_score;
        $assignment = $this->assignments->update($assignment, $request->validated());

        $changes = [];
        if (! $assignment->deadline->equalTo($oldDeadline)) {
            $changes[] = 'deadline moved to '.$assignment->deadline->format('d M Y H:i');
        }
        if ($assignment->wasChanged('progress')) {
            $changes[] = "progress {$assignment->progress}%";
        }
        activity()->action('assignments.updated')->describe(sprintf('Updated “%s”%s — risk %d%% → %d%%', $assignment->title,
            $changes ? ' ('.implode(', ', $changes).')' : '', $oldRisk ?? 0, $assignment->risk_score ?? 0))->on($assignment);

        AssignmentResource::$risk = $this->risk->assess($request->user());

        return AssignmentResource::make($assignment->load(['module', 'academicDate']));
    }

    public function destroy(Assignment $assignment): JsonResponse
    {
        $this->assignments->delete($assignment);
        activity()->action('assignments.deleted')->describe("Moved assignment “{$assignment->title}” to trash")->on($assignment);

        return response()->json(['message' => 'Assignment moved to trash.']);
    }

    public function progress(ProgressRequest $request, Assignment $assignment): AssignmentResource
    {
        $before = ['progress' => $assignment->progress, 'risk' => $assignment->risk_score];
        $assignment = $this->assignments->recordProgress(
            $assignment,
            $request->validated('progress') !== null ? (int) $request->validated('progress') : null,
            (float) ($request->validated('hours_added') ?? 0),
            $request->validated('note'),
        );
        activity()->action('assignments.progress_recorded')->describe(sprintf('Recorded progress on “%s”: %d%% → %d%%%s — risk %d%% → %d%%',
            $assignment->title, $before['progress'], $assignment->progress,
            $request->validated('hours_added') ? ' (+'.$request->validated('hours_added').' h)' : '',
            $before['risk'] ?? 0, $assignment->risk_score ?? 0))->on($assignment);

        AssignmentResource::$risk = $this->risk->assess($request->user());

        return AssignmentResource::make($assignment->load(['module', 'academicDate']));
    }

    public function complete(CompleteRequest $request, Assignment $assignment): AssignmentResource
    {
        abort_if($assignment->isCompleted(), 422, 'This assignment is already completed.');
        $submitted = $request->validated('submitted_at') ? Carbon::parse($request->validated('submitted_at')) : null;
        $assignment = $this->assignments->complete($assignment, $submitted, $request->validated('note'));
        $late = $assignment->submissions()->latest('submitted_at')->first()?->is_late;
        activity()->action('assignments.completed')->describe("Completed “{$assignment->title}” ".($late ? '(submitted late)' : '(on time)'))->on($assignment);

        return AssignmentResource::make($assignment->load(['module', 'academicDate']));
    }

    public function reopen(Assignment $assignment): AssignmentResource
    {
        abort_unless($assignment->isCompleted(), 422, 'Only completed assignments can be reopened.');
        $assignment = $this->assignments->reopen($assignment);
        activity()->action('assignments.reopened')->describe("Reopened “{$assignment->title}”")->on($assignment);
        AssignmentResource::$risk = $this->risk->assess($assignment->user);

        return AssignmentResource::make($assignment->load(['module', 'academicDate']));
    }
}
