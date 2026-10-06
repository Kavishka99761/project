<?php

namespace App\Http\Controllers\Api\V1\Study;

use App\Enums\SessionStatus;
use App\Enums\StudyActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Study\EngagementRequest;
use App\Http\Requests\Study\StartSessionRequest;
use App\Http\Requests\Study\StopSessionRequest;
use App\Http\Resources\Study\EngagementLogResource;
use App\Http\Resources\Study\StudySessionResource;
use App\Models\StudySession;
use App\Services\Study\BreakAdvisor;
use App\Services\Study\EngagementService;
use App\Services\Study\StudySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * PASINDU — study sessions (start / pause / resume / break / stop / cancel),
 * session history, engagement monitoring and break advice.
 */
class StudySessionController extends Controller
{
    public function __construct(
        private readonly StudySessionService $sessions,
        private readonly EngagementService $engagement,
        private readonly BreakAdvisor $advisor,
    ) {}

    /** Session history with filters. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'module_id' => ['nullable', 'integer'],
            'activity' => ['nullable', Rule::enum(StudyActivity::class)],
            'status' => ['nullable', Rule::enum(SessionStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $sessions = $request->user()->studySessions()->with(['module', 'document', 'assignment'])
            ->when($filters['module_id'] ?? null, fn ($q, $id) => $q->where('module_id', $id))
            ->when($filters['activity'] ?? null, fn ($q, $a) => $q->where('activity', $a))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', SessionStatus::Cancelled))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('started_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('started_at', '<=', $to.' 23:59:59'))
            ->latest('started_at')
            ->paginate($filters['per_page'] ?? 20)->withQueryString();

        return StudySessionResource::collection($sessions);
    }

    /** The running session (null when idle). */
    public function active(Request $request): JsonResponse
    {
        $session = $this->sessions->live($request->user());
        activity()->skip();

        return response()->json([
            'session' => $session ? StudySessionResource::make($session) : null,
            'advice' => $session ? $this->advisor->advise($session) : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function store(StartSessionRequest $request): JsonResponse
    {
        $session = $this->sessions->start($request->user(), $request->validated());
        activity()->action('study.session_started')->describe(sprintf('Started a %d-min %s session%s%s',
            $session->planned_minutes, mb_strtolower($session->activity->label()),
            $session->module ? ' for '.$session->module->code : '',
            $session->assignment ? ' on “'.$session->assignment->title.'”' : ''))->on($session);

        return StudySessionResource::make($session)->response()->setStatusCode(201);
    }

    public function show(StudySession $session): StudySessionResource
    {
        return StudySessionResource::make($session->load(['module', 'document', 'assignment', 'events', 'engagementLogs' => fn ($q) => $q->orderBy('logged_at')]));
    }

    public function pause(StudySession $session): StudySessionResource
    {
        $session = $this->sessions->pause($session);
        activity()->action('study.session_paused')->describe('Paused the study timer at '.gmdate('H:i:s', $session->focus_seconds))->on($session);

        return StudySessionResource::make($session);
    }

    public function resume(StudySession $session): StudySessionResource
    {
        $session = $this->sessions->resume($session);
        activity()->action('study.session_resumed')->describe('Resumed the study timer')->on($session);

        return StudySessionResource::make($session);
    }

    public function startBreak(Request $request, StudySession $session): StudySessionResource
    {
        $minutes = $request->validate(['minutes' => ['nullable', 'integer', 'between:1,60']])['minutes'] ?? null;
        $session = $this->sessions->startBreak($session, $minutes);
        activity()->action('study.break_started')->describe('Started a '.($minutes ? "{$minutes}-minute " : '').'break')->on($session);

        return StudySessionResource::make($session);
    }

    public function stop(StopSessionRequest $request, StudySession $session): StudySessionResource
    {
        $session = $this->sessions->stop($session, $request->validated());
        activity()->action('study.session_completed')->describe(sprintf('Completed a study session — %d of %d planned minutes, focus score %d',
            $session->actual_minutes, $session->planned_minutes, $session->focus_score ?? 0))->on($session);

        return StudySessionResource::make($session->load('engagementLogs'));
    }

    public function cancel(StudySession $session): StudySessionResource
    {
        $session = $this->sessions->cancel($session);
        activity()->action('study.session_cancelled')->describe('Cancelled a study session')->on($session);

        return StudySessionResource::make($session);
    }

    /** Edit the reflection of a finished session. */
    public function update(Request $request, StudySession $session): StudySessionResource
    {
        $data = $request->validate([
            'goal' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'mood' => ['nullable', Rule::in(['great', 'good', 'okay', 'tired', 'stressed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $session->update($data);
        activity()->action('study.session_updated')->describe('Updated session notes')->on($session);

        return StudySessionResource::make($session->load(['module', 'document', 'assignment']));
    }

    public function destroy(StudySession $session): JsonResponse
    {
        abort_if($session->isLive(), 422, 'Stop or cancel the session before deleting it.');
        $session->delete();
        activity()->action('study.session_deleted')->describe('Deleted a study session from history');

        return response()->json(['message' => 'Session deleted.']);
    }

    /** Engagement sample (auto, every minute) or manual concentration report. */
    public function engagement(EngagementRequest $request, StudySession $session): JsonResponse
    {
        $data = $request->validated();
        $result = $data['source'] === 'manual'
            ? $this->engagement->recordManual($session, (int) $data['concentration'], $data['note'] ?? null)
            : $this->engagement->recordAutomatic($session, $data['signals'] ?? []);

        if ($data['source'] === 'manual') {
            activity()->action('study.concentration_reported')->describe("Reported concentration {$data['concentration']}/5")->on($session);
        } elseif ($result['changed']) {
            activity()->action('study.engagement_changed')->describe("Engagement changed from {$result['previous']} to {$result['log']->level->value}")->on($session);
        } else {
            activity()->skip(); // routine telemetry is stored in engagement_logs
        }

        return response()->json([
            'log' => EngagementLogResource::make($result['log']),
            'changed' => $result['changed'],
            'previous' => $result['previous'],
            'advice' => $this->advisor->advise($session->refresh()),
        ], 201);
    }

    public function advice(StudySession $session): JsonResponse
    {
        activity()->skip();

        return response()->json($this->advisor->advise($session));
    }

    /** Engagement history across sessions. */
    public function engagementHistory(Request $request): AnonymousResourceCollection
    {
        return EngagementLogResource::collection($request->user()->engagementLogs()
            ->when($request->integer('session_id'), fn ($q, $id) => $q->where('study_session_id', $id))
            ->when($request->input('source'), fn ($q, $source) => $q->where('source', $source))
            ->latest('logged_at')
            ->paginate(min(200, $request->integer('per_page', 50))));
    }
}
