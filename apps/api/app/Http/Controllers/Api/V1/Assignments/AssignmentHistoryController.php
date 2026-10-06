<?php

namespace App\Http\Controllers\Api\V1\Assignments;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assignments\AssignmentResource;
use App\Models\AssignmentProgressLog;
use App\Models\AssignmentSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JITHMI — Assignment History: progress timeline, submission history,
 * completed assignments and overdue history.
 */
class AssignmentHistoryController extends Controller
{
    public function progress(Request $request): JsonResponse
    {
        $logs = AssignmentProgressLog::query()->ownedBy($request->user())->with('assignment:id,title,module_id')
            ->when($request->integer('assignment_id'), fn ($q, $id) => $q->where('assignment_id', $id))
            ->latest('created_at')->paginate(min(100, $request->integer('per_page', 40)));

        return response()->json($logs->through(fn ($log) => [
            'id' => $log->id,
            'assignment' => $log->assignment ? ['id' => $log->assignment->id, 'title' => $log->assignment->title] : null,
            'progress_before' => $log->progress_before,
            'progress_after' => $log->progress_after,
            'hours_added' => $log->hours_added,
            'source' => $log->source,
            'note' => $log->note,
            'study_session_id' => $log->study_session_id,
            'created_at' => $log->created_at?->toIso8601String(),
        ]));
    }

    public function submissions(Request $request): JsonResponse
    {
        $submissions = AssignmentSubmission::query()->ownedBy($request->user())->with('assignment:id,title,module_id')
            ->latest('submitted_at')->paginate(min(100, $request->integer('per_page', 40)));
        $all = AssignmentSubmission::query()->ownedBy($request->user());

        return response()->json([
            'stats' => [
                'total' => (clone $all)->count(),
                'on_time' => (clone $all)->where('is_late', false)->count(),
                'late' => (clone $all)->where('is_late', true)->count(),
            ],
            'items' => $submissions->through(fn ($s) => [
                'id' => $s->id,
                'assignment' => $s->assignment ? ['id' => $s->assignment->id, 'title' => $s->assignment->title] : null,
                'submitted_at' => $s->submitted_at->toIso8601String(),
                'deadline_at' => $s->deadline_at->toIso8601String(),
                'is_late' => $s->is_late,
                'minutes_late' => $s->minutes_late,
                'note' => $s->note,
            ]),
        ]);
    }

    public function completed(Request $request): JsonResponse
    {
        $assignments = $request->user()->assignments()->with('module')->where('status', AssignmentStatus::Completed)
            ->latest('completed_at')->get();

        return response()->json(AssignmentResource::collection($assignments));
    }

    /** Assignments that ever went past their deadline (overdue history). */
    public function overdue(Request $request): JsonResponse
    {
        $assignments = $request->user()->assignments()->with('module')
            ->where(fn ($q) => $q->where('was_overdue', true)
                ->orWhere(fn ($q) => $q->where('status', '!=', AssignmentStatus::Completed)->where('deadline', '<', now())))
            ->orderByDesc('deadline')->get();

        return response()->json(AssignmentResource::collection($assignments));
    }
}
