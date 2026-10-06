<?php

namespace App\Services;

/**
 * BETHMI — extractive summariser + keyword extractor.
 *
 * A dependency-free implementation that mirrors the client-side logic in
 * frontend-web/assets/js/learning.js so summaries look identical whether they
 * are generated in the browser (offline demo) or by the API (production).
 *
 * The algorithm is intentionally simple and transparent:
 *   1. split the text into sentences,
 *   2. score each sentence by how many high-frequency keywords it contains,
 *   3. keep the top N sentences (N depends on the requested length),
 *   4. return them in their original order.
 */
class SummaryGenerator
{
    /** Common English stop words removed before keyword frequency counting. */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be',
        'been', 'being', 'to', 'of', 'in', 'on', 'for', 'with', 'as', 'by',
        'at', 'from', 'this', 'that', 'these', 'those', 'it', 'its', 'as',
        'not', 'no', 'can', 'will', 'just', 'should', 'now', 'also', 'such',
        'into', 'than', 'then', 'there', 'their', 'they', 'them', 'he', 'she',
        'you', 'your', 'we', 'our', 'which', 'when', 'where', 'while', 'if',
    ];

    /** Number of sentences kept for each length option. */
    private const LENGTH_SENTENCES = ['Short' => 2, 'Medium' => 4, 'Detailed' => 7];

    /**
     * Generate a summary of the given text.
     *
     * @return array{title:string, length_type:string, body:string, keywords:array<int,string>}
     */
    public function generate(string $text, string $lengthType = 'Medium', ?string $title = null): array
    {
        $lengthType = ucfirst(strtolower($lengthType));
        if (! array_key_exists($lengthType, self::LENGTH_SENTENCES)) {
            $lengthType = 'Medium';
        }

        $keywords = $this->extractKeywords($text, 8);
        $sentences = $this->sentences($text);
        $keep = self::LENGTH_SENTENCES[$lengthType];

        if (count($sentences) <= $keep) {
            $body = implode(' ', $sentences);
        } else {
            $scores = [];
            foreach ($sentences as $i => $sentence) {
                $scores[$i] = $this->scoreSentence($sentence, $keywords);
            }
            arsort($scores);
            $picked = array_slice(array_keys($scores), 0, $keep);
            sort($picked); // preserve original reading order
            $body = implode(' ', array_map(fn ($i) => $sentences[$i], $picked));
        }

        return [
            'title'       => $title ?: $this->deriveTitle($body),
            'length_type' => $lengthType,
            'body'        => trim($body),
            'keywords'    => $keywords,
        ];
    }

    /**
     * Frequency-based keyword extraction.
     *
     * @return array<int,string>
     */
    public function extractKeywords(string $text, int $limit = 6): array
    {
        $words = $this->tokenize($text);
        $freq = [];
        foreach ($words as $word) {
            if (in_array($word, self::STOP_WORDS, true) || strlen($word) < 3) {
                continue;
            }
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }
        arsort($freq);

        return array_slice(array_keys($freq), 0, $limit);
    }

    /** @return array<int,string> */
    private function sentences(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+/', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    /** @return array<int,string> lower-cased alphanumeric tokens */
    private function tokenize(string $text): array
    {
        $clean = strtolower(strip_tags($text));
        preg_match_all('/[a-z0-9]+/', $clean, $m);

        return $m[0] ?? [];
    }

    /**
     * @param array<int,string> $keywords
     */
    private function scoreSentence(string $sentence, array $keywords): float
    {
        $tokens = $this->tokenize($sentence);
        if (empty($tokens)) {
            return 0.0;
        }
        $score = 0.0;
        foreach ($tokens as $token) {
            if (in_array($token, $keywords, true)) {
                $score += 1.0;
            }
        }
        // Normalise by length so very long sentences don't always win.
        return $score / sqrt(count($tokens));
    }

    private function deriveTitle(string $body): string
    {
        $first = $this->sentences($body)[0] ?? 'Summary';
        $title = trim(preg_replace('/[.!?]$/', '', $first) ?? $first);

        return strlen($title) > 60 ? substr($title, 0, 57).'...' : $title;
    }
}
