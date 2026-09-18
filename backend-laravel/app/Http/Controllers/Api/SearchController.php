<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global Search — searches across documents, assignments, academic dates,
 * summaries and modules in a single request.
 *
 * GET /api/search?q={term}&limit={n}
 *
 * Returns a map of result buckets so the UI can render each category separately
 * or merge them into a unified list.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q'     => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $term  = '%' . $request->string('q')->toString() . '%';
        $limit = $request->integer('limit', 8);
        $user  = $request->user();

        // ------------------------------------------------------------------
        // Documents
        // ------------------------------------------------------------------
        $documents = $user->documents()
            ->select(['id', 'title', 'topic', 'type', 'module_id'])
            ->with('module:id,name')
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('topic', 'like', $term)
                  ->orWhere('extracted_text', 'like', $term);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'type'    => 'document',
                'id'      => $d->id,
                'label'   => $d->title,
                'sub'     => $d->module?->name . ' · ' . $d->type,
                'url'     => "/learning?doc={$d->id}",
            ]);

        // ------------------------------------------------------------------
        // Assignments
        // ------------------------------------------------------------------
        $assignments = $user->assignments()
            ->select(['id', 'title', 'module_id', 'deadline', 'progress'])
            ->with('module:id,name')
            ->where('title', 'like', $term)
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'type'  => 'assignment',
                'id'    => $a->id,
                'label' => $a->title,
                'sub'   => ($a->module?->name ?? '') . ' · due ' . $a->deadline,
                'url'   => "/assignments?id={$a->id}",
            ]);

        // ------------------------------------------------------------------
        // Academic dates
        // ------------------------------------------------------------------
        $dates = $user->academicDates()
            ->select(['id', 'title', 'event_date', 'type'])
            ->where('title', 'like', $term)
            ->limit($limit)
            ->get()
            ->map(fn ($d) => [
                'type'  => 'date',
                'id'    => $d->id,
                'label' => $d->title,
                'sub'   => $d->event_date . ' · ' . $d->type,
                'url'   => "/calendar",
            ]);

        // ------------------------------------------------------------------
        // Modules
        // ------------------------------------------------------------------
        $modules = $user->modules()
            ->select(['id', 'name', 'code'])
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('code', 'like', $term);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($m) => [
                'type'  => 'module',
                'id'    => $m->id,
                'label' => $m->name,
                'sub'   => $m->code,
                'url'   => "/learning?module={$m->id}",
            ]);

        $all = $documents
            ->concat($assignments)
            ->concat($dates)
            ->concat($modules)
            ->values();

        return response()->json([
            'query'       => $request->string('q')->toString(),
            'total'       => $all->count(),
            'results'     => $all,
            'buckets'     => [
                'documents'   => $documents->values(),
                'assignments' => $assignments->values(),
                'dates'       => $dates->values(),
                'modules'     => $modules->values(),
            ],
        ]);
    }
}
