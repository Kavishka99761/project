<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\DataChangeResource;
use App\Models\DataChange;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Row-level database audit — every INSERT / UPDATE / DELETE that SQL Server
 * recorded for the student's data (dbo.data_change_log), as a filterable,
 * paginated table plus the before/after of a single change.
 */
class DataChangeController extends Controller
{
    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'table' => ['nullable', 'string', 'max:128'],
            'operation' => ['nullable', Rule::in(array_values(DataChange::OPERATIONS))],
            'record_id' => ['nullable', 'string', 'max:64'],
            'request_id' => ['nullable', 'uuid'],
            'source' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:5,200'],
        ]);

        $changes = $this->filtered($request, $filters, $search)
            ->with('activity:id,request_id,action,description')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);

        return DataChangeResource::collection($changes);
    }

    public function show(Request $request, int $id): DataChangeResource
    {
        $change = DataChange::visibleTo($request->user())->with('activity')->findOrFail($id);

        return (new DataChangeResource($change))->withDiff();
    }

    /** Every recorded version of one record, oldest first. */
    public function history(Request $request, string $table, string $recordId): JsonResponse
    {
        $changes = DataChange::visibleTo($request->user())
            ->where('table_name', $table)->where('record_id', $recordId)
            ->with('activity:id,request_id,action,description')
            ->orderBy('id')->limit(500)->get();

        return response()->json($changes->map(fn ($c) => (new DataChangeResource($c))->withDiff()->resolve($request))->values());
    }

    /** Totals per operation, per table and per day — for the filters and the chart. */
    public function stats(Request $request): JsonResponse
    {
        $base = fn () => DataChange::visibleTo($request->user());
        $since = now()->subDays(13)->startOfDay();

        $byOperation = $base()->selectRaw('operation, COUNT(*) AS total')->groupBy('operation')->pluck('total', 'operation');
        $byTable = $base()->selectRaw('table_name, COUNT(*) AS total')->groupBy('table_name')->orderByDesc('total')->pluck('total', 'table_name');
        $byDay = $base()->where('changed_at', '>=', $since)
            ->selectRaw('CAST(changed_at AS date) AS day, operation, COUNT(*) AS total')
            ->groupByRaw('CAST(changed_at AS date), operation')->get();

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();
            $row = ['date' => $date];
            foreach (DataChange::OPERATIONS as $code => $name) {
                $row[$name] = (int) $byDay->first(fn ($r) => substr((string) $r->day, 0, 10) === $date && $r->operation === $code)?->total;
            }
            $days[] = $row;
        }

        return response()->json([
            'total' => (int) $byOperation->sum(),
            'by_operation' => collect(DataChange::OPERATIONS)->mapWithKeys(fn ($name, $code) => [$name => (int) ($byOperation[$code] ?? 0)]),
            'by_table' => $byTable->map(fn ($total, $table) => ['table' => $table, 'total' => (int) $total])->values(),
            'by_day' => $days,
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    private function filtered(Request $request, array $filters, SearchService $search)
    {
        $operation = array_search($filters['operation'] ?? null, DataChange::OPERATIONS, true);

        return DataChange::visibleTo($request->user())
            ->when($filters['table'] ?? null, fn ($q, $table) => $q->where('table_name', $table))
            ->when($operation, fn ($q) => $q->where('operation', $operation))
            ->when($filters['record_id'] ?? null, fn ($q, $id) => $q->where('record_id', $id))
            ->when($filters['request_id'] ?? null, fn ($q, $id) => $q->where('request_id', $id))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where(fn ($q) => $q
                ->where('new_values', 'like', '%'.$search->escapeLike($text).'%')
                ->orWhere('old_values', 'like', '%'.$search->escapeLike($text).'%')))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('changed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('changed_at', '<=', $to.' 23:59:59'));
    }
}
