<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Optional semantic re-ranking through the Python sentence-transformers
 * service (services/ai-engine). When it is not running, retrieval silently
 * stays BM25-only; a short back-off avoids waiting on a dead service.
 */
class SemanticReranker
{
    private const DOWN_KEY = 'ai-engine:down';

    public function available(): bool
    {
        return (string) config('edusmart.ai_engine.url') !== '' && ! Cache::get(self::DOWN_KEY, false);
    }

    /**
     * Blend cosine similarity into BM25 relevance.
     *
     * @param  list<array<string, mixed>>  $hits
     * @return array{hits: list<array<string, mixed>>, semantic: bool}
     */
    public function rerank(string $query, array $hits): array
    {
        if ($hits === [] || ! $this->available()) {
            return ['hits' => $hits, 'semantic' => false];
        }

        try {
            $response = Http::timeout((int) config('edusmart.ai_engine.timeout', 6))
                ->connectTimeout(1)
                ->post(rtrim((string) config('edusmart.ai_engine.url'), '/').'/rank', [
                    'query' => $query,
                    'passages' => array_map(fn ($h) => trim(($h['chunk']->section ?? '').'. '.$h['chunk']->content), $hits),
                ]);
            $scores = $response->successful() ? $response->json('scores') : null;
        } catch (\Throwable) {
            $scores = null;
        }

        if (! is_array($scores) || count($scores) !== count($hits)) {
            Cache::put(self::DOWN_KEY, true, 60);

            return ['hits' => $hits, 'semantic' => false];
        }

        foreach ($hits as $i => &$hit) {
            $cosine = max(0.0, (float) $scores[$i]);
            $hit['semantic'] = round($cosine, 4);
            $hit['relevance'] = round(0.55 * $hit['relevance'] + 0.45 * $cosine, 4);
        }
        unset($hit);
        usort($hits, fn ($a, $b) => $b['relevance'] <=> $a['relevance']);

        return ['hits' => $hits, 'semantic' => true];
    }
}
