<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\ModuleKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\ActivityLogResource;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Audit trail — every action the student performed, filterable by module,
 * action, outcome, date range and free text.
 */
class ActivityController extends Controller
{
    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'module' => ['nullable', Rule::enum(ModuleKey::class)],
            'action' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'outcome' => ['nullable', Rule::in(['success', 'failed'])],
            'per_page' => ['nullable', 'integer', 'between:5,200'],
        ]);

        $logs = $request->user()->activityLogs()
            ->when($filters['module'] ?? null, fn ($q, $module) => $q->where('module', $module))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', $search->escapeLike($action).'%'))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where('description', 'like', '%'.$search->escapeLike($text).'%'))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->when(($filters['outcome'] ?? null) === 'failed', fn ($q) => $q->where('status_code', '>=', 400))
            ->when(($filters['outcome'] ?? null) === 'success', fn ($q) => $q->where(fn ($q) => $q->whereNull('status_code')->orWhere('status_code', '<', 400)))
            ->latest('created_at')->latest('id')
            ->paginate($filters['per_page'] ?? 30);

        return ActivityLogResource::collection($logs);
    }

    /** Counts per module and per day for the activity overview charts. */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = now()->subDays(13)->startOfDay();

        $byModule = $user->activityLogs()->selectRaw('module, COUNT(*) AS total')->groupBy('module')->pluck('total', 'module');
        $byDay = $user->activityLogs()->where('created_at', '>=', $since)
            ->selectRaw('CAST(created_at AS date) AS day, COUNT(*) AS total')
            ->groupByRaw('CAST(created_at AS date)')->pluck('total', 'day');

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();
            $days[] = ['date' => $date, 'total' => (int) ($byDay[$date] ?? 0)];
        }

        return response()->json([
            'total' => $user->activityLogs()->count(),
            'failed' => $user->activityLogs()->where('status_code', '>=', 400)->count(),
            'by_module' => collect(ModuleKey::cases())->map(fn ($m) => [
                'module' => $m->value, 'label' => $m->label(), 'color' => $m->meta()['color'], 'total' => (int) ($byModule[$m->value] ?? 0),
            ]),
            'by_day' => $days,
        ]);
    }
}
