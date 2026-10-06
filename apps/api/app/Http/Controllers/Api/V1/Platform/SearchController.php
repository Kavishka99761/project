<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Common Services — Search API (global search + search history).
 */
class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
            'scope' => ['nullable', Rule::in(SearchService::SCOPES)],
            'limit' => ['nullable', 'integer', 'between:1,25'],
        ]);

        $results = $this->search->search($request->user(), $data['q'], $data['scope'] ?? 'all', $data['limit'] ?? 6);
        activity()->describe(sprintf('Searched for “%s” (%d results)', $data['q'], $results['total']))
            ->with(['query' => $data['q'], 'scope' => $data['scope'] ?? 'all', 'results' => $results['total']]);

        return response()->json($results);
    }

    public function history(Request $request): JsonResponse
    {
        $history = $request->user()->searchHistory()->latest('created_at')->limit(50)->get(['id', 'query', 'scope', 'results_count', 'created_at']);

        // Most recent first, one entry per distinct query.
        return response()->json($history->unique(fn ($h) => mb_strtolower($h->query))->values()->take(12)->map(fn ($h) => [
            'id' => $h->id,
            'query' => $h->query,
            'scope' => $h->scope,
            'results' => $h->results_count,
            'created_at' => $h->created_at?->toIso8601String(),
        ]));
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $count = $request->user()->searchHistory()->delete();
        activity()->action('search.history_cleared')->describe("Cleared search history ({$count} searches)");

        return response()->json(['message' => 'Search history cleared.', 'deleted' => $count]);
    }
}
