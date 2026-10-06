<?php

namespace App\Services\Learning;

use App\Enums\DocumentKind;
use App\Enums\ExtractionStatus;
use App\Models\User;

/**
 * BETHMI — Learning Dashboard: library statistics, materials by module and
 * type, topics, the most frequent keywords across all notes and recent work.
 */
class LearningDashboard
{
    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $documents = $user->documents()->with('module:id,code,name,color')->get([
            'id', 'module_id', 'title', 'topic', 'kind', 'pages', 'word_count', 'size_bytes', 'extraction_status', 'keywords', 'created_at', 'last_opened_at', 'is_favorite',
        ]);

        $keywordWeights = [];
        foreach ($documents as $document) {
            foreach ((array) $document->keywords as $keyword) {
                $term = $keyword['term'] ?? null;
                if ($term) {
                    $keywordWeights[$term] = ($keywordWeights[$term] ?? 0) + ($keyword['score'] ?? 0.5);
                }
            }
        }
        arsort($keywordWeights);

        return [
            'stats' => [
                'documents' => $documents->count(),
                'summaries' => $user->summaries()->count(),
                'study_aids' => $user->studyAids()->count(),
                'pages' => (int) $documents->sum('pages'),
                'words' => (int) $documents->sum('word_count'),
                'storage_bytes' => (int) $documents->sum('size_bytes'),
                'favorites' => $documents->where('is_favorite', true)->count(),
                'needs_attention' => $documents->whereIn('extraction_status', [ExtractionStatus::Failed, ExtractionStatus::Empty])->count(),
            ],
            'by_module' => $documents->groupBy(fn ($d) => $d->module_id ?? 0)->map(fn ($group) => [
                'module_id' => $group->first()->module_id,
                'code' => $group->first()->module?->code ?? 'Unfiled',
                'name' => $group->first()->module?->name ?? 'No module',
                'color' => $group->first()->module?->color ?? '#94a3b8',
                'documents' => $group->count(),
                'pages' => (int) $group->sum('pages'),
            ])->sortByDesc('documents')->values(),
            'by_type' => collect(DocumentKind::cases())->map(fn ($kind) => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'color' => $kind->meta()['color'],
                'count' => $documents->where('kind', $kind)->count(),
            ])->filter(fn ($row) => $row['count'] > 0)->values(),
            'topics' => $documents->whereNotNull('topic')->groupBy('topic')->map(fn ($g, $topic) => ['topic' => $topic, 'count' => $g->count()])
                ->sortByDesc('count')->values()->take(12),
            'keywords' => collect($keywordWeights)->take(24)->map(fn ($weight, $term) => ['term' => $term, 'weight' => round($weight, 2)])->values(),
            'recent_documents' => $documents->sortByDesc('created_at')->take(5)->values()->map(fn ($d) => [
                'id' => $d->id, 'title' => $d->title, 'kind' => $d->kind->value, 'module' => $d->module?->code,
                'pages' => $d->pages, 'created_at' => $d->created_at->toIso8601String(),
            ]),
            'recently_opened' => $documents->whereNotNull('last_opened_at')->sortByDesc('last_opened_at')->take(4)->values()->map(fn ($d) => [
                'id' => $d->id, 'title' => $d->title, 'kind' => $d->kind->value, 'opened_at' => $d->last_opened_at->toIso8601String(),
            ]),
            'recent_summaries' => $user->summaries()->with('document:id,title')->latest()->limit(5)->get()->map(fn ($s) => [
                'id' => $s->id, 'title' => $s->title, 'length' => $s->length->value, 'document' => $s->document?->title,
                'word_count' => $s->word_count, 'created_at' => $s->created_at->toIso8601String(),
            ]),
        ];
    }
}
