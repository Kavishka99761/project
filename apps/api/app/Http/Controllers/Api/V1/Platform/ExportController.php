<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Exports\DatasetRegistry;
use App\Services\Exports\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Common Services — data export centre: any dataset (or a full backup) as
 * PDF, Excel, CSV or JSON, generated live from SQL Server.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exports,
        private readonly DatasetRegistry $registry,
    ) {}

    public function catalogue(Request $request): JsonResponse
    {
        return response()->json([
            'formats' => [
                ['value' => 'pdf', 'label' => 'PDF report', 'icon' => 'file-earmark-pdf'],
                ['value' => 'xlsx', 'label' => 'Excel workbook', 'icon' => 'file-earmark-excel'],
                ['value' => 'csv', 'label' => 'CSV', 'icon' => 'filetype-csv'],
                ['value' => 'json', 'label' => 'JSON', 'icon' => 'filetype-json'],
            ],
            'datasets' => $this->exports->catalogue($request->user()),
        ]);
    }

    public function download(Request $request, string $dataset): Response
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(ExportService::FORMATS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        abort_unless($this->registry->get($dataset), 404, 'Unknown dataset.');

        return $this->exports->export($request->user(), $dataset, $data['format'], $data['from'] ?? null, $data['to'] ?? null)->toResponse();
    }

    public function backup(Request $request): Response
    {
        $data = $request->validate(['format' => ['required', Rule::in(ExportService::FORMATS)]]);

        return $this->exports->backup($request->user(), $data['format'])->toResponse();
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json($request->user()->exportLogs()->latest('created_at')->limit(50)->get()->map(fn ($log) => [
            'id' => $log->id,
            'dataset' => $log->dataset,
            'format' => $log->format,
            'rows' => $log->row_count,
            'file_name' => $log->file_name,
            'file_size' => $log->file_size,
            'filters' => $log->filters,
            'created_at' => $log->created_at?->toIso8601String(),
        ]));
    }
}
