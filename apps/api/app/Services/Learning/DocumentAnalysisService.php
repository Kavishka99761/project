<?php

namespace App\Services\Learning;

use App\Models\Document;
use App\Services\Nlp\Analysis;
use App\Services\Nlp\ConceptExtractor;
use App\Services\Nlp\DocumentAnalyzer;
use Illuminate\Support\Facades\Cache;

/**
 * BETHMI — cached analysis of a learning document plus the presentation
 * helpers built on it (highlighted reading view, keyword list, concepts).
 *
 * Keyword IDF uses the student's own library as the corpus, so a term that
 * appears in every document of the module ("database") ranks below terms
 * that are distinctive for *this* lecture ("functional dependency").
 */
class DocumentAnalysisService
{
    public function __construct(
        private readonly DocumentAnalyzer $analyzer,
        private readonly ConceptExtractor $concepts,
    ) {}

    public function analyze(Document $document): Analysis
    {
        $key = sprintf('analysis:document:%d:%s', $document->id, md5((string) $document->content.'|'.$document->title));

        return Cache::remember($key, now()->addHours(6), function () use ($document) {
            [$df, $size] = $this->corpusFrequencies($document);

            return $this->analyzer->analyze((string) $document->content, $document->title, $df, $size);
        });
    }

    public function analyzeText(string $text, ?string $title = null): Analysis
    {
        return $this->analyzer->analyze($text, $title);
    }

    /**
     * Reading view: the document's blocks with every sentence tagged
     * "key" (top ~12 %), "important" (next ~18 %) or null.
     *
     * @return list<array<string, mixed>>
     */
    public function highlightedBlocks(Analysis $analysis): array
    {
        [$keyCut, $importantCut] = $this->thresholds($analysis);

        $bySentenceBlock = [];
        foreach ($analysis->sentences as $sentence) {
            $level = null;
            if ($sentence['candidate'] && $sentence['score'] >= $keyCut) {
                $level = 'key';
            } elseif ($sentence['candidate'] && $sentence['score'] >= $importantCut) {
                $level = 'important';
            }
            $bySentenceBlock[$sentence['block']][] = [
                'index' => $sentence['index'],
                'text' => $sentence['text'],
                'level' => $level,
                'score' => $sentence['score'],
            ];
        }

        $blocks = [];
        foreach ($analysis->blocks as $i => $block) {
            $blocks[] = $block['type'] === 'heading'
                ? ['type' => 'heading', 'text' => $block['text']]
                : ['type' => $block['type'], 'sentences' => $bySentenceBlock[$i] ?? []];
        }

        return $blocks;
    }

    /** @return list<array{index: int, text: string, level: string, score: float, section: ?string}> */
    public function importantSentences(Analysis $analysis): array
    {
        [$keyCut, $importantCut] = $this->thresholds($analysis);
        $out = [];
        foreach ($analysis->sentences as $sentence) {
            if (! $sentence['candidate'] || $sentence['score'] < $importantCut) {
                continue;
            }
            $out[] = [
                'index' => $sentence['index'],
                'text' => $sentence['text'],
                'level' => $sentence['score'] >= $keyCut ? 'key' : 'important',
                'score' => $sentence['score'],
                'section' => $sentence['section'],
            ];
        }

        return $out;
    }

    /** Store the top keywords on the document row (powers search & corpus IDF). */
    public function refreshKeywords(Document $document): void
    {
        $analysis = $this->analyze($document);
        $document->forceFill(['keywords' => array_map(
            fn ($k) => ['term' => $k['term'], 'key' => $k['key'], 'score' => $k['score']],
            $analysis->keywords,
        )])->saveQuietly();
    }

    /** @return array{0: float, 1: float} */
    private function thresholds(Analysis $analysis): array
    {
        $scores = array_column(array_filter($analysis->sentences, fn ($s) => $s['candidate']), 'score');
        if ($scores === []) {
            return [INF, INF];
        }
        rsort($scores);
        $n = count($scores);
        $keyCut = $scores[max(0, (int) ceil($n * 0.12) - 1)];
        $importantCut = $scores[max(0, (int) ceil($n * 0.30) - 1)];

        return [$keyCut, $importantCut];
    }

    /** @return array{0: array<string, int>, 1: int} */
    private function corpusFrequencies(Document $document): array
    {
        $df = [];
        $documents = Document::query()
            ->where('user_id', $document->user_id)
            ->whereKeyNot($document->id)
            ->whereNotNull('keywords')
            ->pluck('keywords');

        foreach ($documents as $keywords) {
            foreach (array_unique(array_column((array) $keywords, 'key')) as $key) {
                $df[$key] = ($df[$key] ?? 0) + 1;
            }
        }

        return [$df, $documents->count() + 1];
    }
}
