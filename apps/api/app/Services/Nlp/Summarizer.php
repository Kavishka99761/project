<?php

namespace App\Services\Nlp;

use App\Enums\SummaryLength;

/**
 * Extractive summariser.
 *
 * Picks the highest-ranked TextRank sentences with Maximal Marginal
 * Relevance (MMR) so the summary covers different ideas instead of
 * repeating the same point, then restores the original reading order.
 * Detailed summaries are grouped under the document's own section headings.
 */
final class Summarizer
{
    /** Relevance vs. novelty trade-off for MMR (1.0 = relevance only). */
    private const LAMBDA = 0.72;

    /**
     * @return array{content: string, sentences: list<int>, sentence_count: int, word_count: int}
     */
    public function summarize(Analysis $analysis, SummaryLength $length): array
    {
        $candidates = $analysis->rankedCandidates();
        if ($candidates === []) {
            // Very short notes: fall back to whatever sentences exist.
            $candidates = $analysis->sentences;
        }
        if ($candidates === []) {
            return ['content' => '', 'sentences' => [], 'sentence_count' => 0, 'word_count' => 0];
        }

        $target = $this->targetCount(count($candidates), $length);

        // Anchor: if the document defines its own subject ("Normalization is
        // the process of …"), every summary opens with that definition.
        $anchor = null;
        foreach ($candidates as $key => $candidate) {
            if (! empty($candidate['defines_title']) && ($anchor === null || $candidate['index'] < $candidates[$anchor]['index'])) {
                $anchor = $key;
            }
        }
        $seed = [];
        if ($anchor !== null) {
            $seed[] = $candidates[$anchor];
            unset($candidates[$anchor]);
        }

        $selected = $this->mmr(array_values($candidates), $target, $seed);
        usort($selected, fn ($a, $b) => $a['index'] <=> $b['index']);

        $content = $length === SummaryLength::Detailed
            ? $this->groupBySection($selected)
            : $this->paragraphs($selected, $length);

        return [
            'content' => $content,
            'sentences' => array_column($selected, 'index'),
            'sentence_count' => count($selected),
            'word_count' => Text::wordCount($content),
        ];
    }

    public function targetCount(int $available, SummaryLength $length): int
    {
        [$ratio, $min, $max] = config('edusmart.nlp.summary_lengths.'.$length->value);
        $target = (int) round($available * $ratio);

        return max(1, min($available, max($min, min($max, $target))));
    }

    /**
     * @param  list<array<string, mixed>>  $candidates  best first
     * @param  list<array<string, mixed>>  $seed        sentences already chosen
     * @return list<array<string, mixed>>
     */
    private function mmr(array $candidates, int $target, array $seed = []): array
    {
        $vectors = [];
        foreach ([...$seed, ...$candidates] as $c) {
            $vectors[$c['index']] = array_count_values($c['terms']);
        }

        $selected = $seed;
        $pool = $candidates;
        while (count($selected) < $target && $pool !== []) {
            $bestKey = null;
            $bestValue = -INF;
            foreach ($pool as $key => $candidate) {
                $redundancy = 0.0;
                foreach ($selected as $chosen) {
                    $redundancy = max($redundancy, Text::cosine($vectors[$candidate['index']], $vectors[$chosen['index']]));
                }
                $value = self::LAMBDA * $candidate['score'] - (1 - self::LAMBDA) * $redundancy;
                if ($value > $bestValue) {
                    $bestValue = $value;
                    $bestKey = $key;
                }
            }
            $selected[] = $pool[$bestKey];
            unset($pool[$bestKey]);
        }

        return $selected;
    }

    /** @param  list<array<string, mixed>>  $selected */
    private function paragraphs(array $selected, SummaryLength $length): string
    {
        $texts = array_map(fn ($s) => $this->clean($s['text']), $selected);
        if ($length === SummaryLength::Short || count($texts) < 6) {
            return implode(' ', $texts);
        }
        $half = (int) ceil(count($texts) / 2);

        return implode(' ', array_slice($texts, 0, $half))."\n\n".implode(' ', array_slice($texts, $half));
    }

    /** @param  list<array<string, mixed>>  $selected */
    private function groupBySection(array $selected): string
    {
        $groups = [];
        foreach ($selected as $sentence) {
            $groups[$sentence['section'] ?? ''][] = $this->clean($sentence['text']);
        }
        if (count($groups) === 1) {
            $texts = reset($groups);
            $third = (int) ceil(count($texts) / 3);

            return implode("\n\n", array_map(fn ($chunk) => implode(' ', $chunk), array_chunk($texts, max($third, 1))));
        }

        $out = [];
        foreach ($groups as $section => $texts) {
            $out[] = ($section !== '' ? '### '.$section."\n\n" : '').implode(' ', $texts);
        }

        return implode("\n\n", $out);
    }

    private function clean(string $sentence): string
    {
        $sentence = trim($sentence);

        return preg_match('/[.!?]["\')\]]?$/u', $sentence) ? $sentence : $sentence.'.';
    }
}
