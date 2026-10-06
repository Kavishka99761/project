<?php

namespace App\Services\Nlp;

/**
 * Key-phrase extraction tuned for lecture material.
 *
 * Candidates are maximal runs of content words between punctuation, stop
 * words and filler ("functional dependency", "third normal form", "SQL").
 * Each candidate is scored by frequency, phrase length, presence in the
 * title/headings and — when a corpus is supplied — inverse document
 * frequency, so terms that make *this* document distinctive rise to the top.
 *
 * Candidates are keyed by light stems (plural folding only) so that related
 * but distinct words such as "normalization" and "normal" stay separate.
 * Single words that behave like verbs (usually followed by "the", "a", …)
 * or adverbs ("-ly") are filtered out — students want nouns and concepts.
 */
final class KeywordExtractor
{
    private const MAX_PHRASE_WORDS = 4;

    /** Words that typically follow a verb, never a noun. */
    private const VERB_FOLLOWERS = [
        'the', 'a', 'an', 'its', 'their', 'his', 'her', 'our', 'your', 'them', 'it', 'whether', 'how',
        'what', 'each', 'every', 'any', 'some', 'many', 'no', 'this', 'these', 'those', 'him', 'us',
    ];

    /**
     * @param  list<string>  $sentences
     * @param  list<string>  $headings
     * @param  array<string, int>  $documentFrequencies  phrase key => number of corpus docs containing it
     * @return list<array{term: string, key: string, score: float, count: int}>
     */
    public function extract(
        array $sentences,
        int $limit = 12,
        array $headings = [],
        ?string $title = null,
        array $documentFrequencies = [],
        int $corpusSize = 0,
    ): array {
        $candidates = [];
        foreach ($sentences as $sentence) {
            foreach ($this->phrases($sentence) as $phrase) {
                $words = $phrase['words'];
                $this->collect($candidates, $words, count($words) === 1 ? $phrase['next'] : null);
                // Sub-words of a phrase count too, so a word used both on its
                // own and inside phrases is measured correctly.
                if (count($words) > 1) {
                    foreach ($words as $word) {
                        $this->collect($candidates, [$word], null);
                    }
                }
            }
        }
        if ($candidates === []) {
            return [];
        }

        $headingKeys = [];
        foreach (array_filter([...$headings, $title]) as $heading) {
            foreach ($this->phrases((string) $heading) as $phrase) {
                foreach ($phrase['words'] as $word) {
                    $headingKeys[Text::lightStem($word)] = true;
                }
            }
        }

        $sentenceCount = max(count($sentences), 1);
        $scored = [];
        foreach ($candidates as $key => $candidate) {
            $words = $candidate['words'];
            $count = $candidate['count'];
            $minCount = $words > 1 ? 2 : ($sentenceCount > 12 ? 2 : 1);
            if ($count < $minCount) {
                continue;
            }

            if ($words === 1) {
                if ($candidate['verb_like'] / $count > 0.4) {
                    continue;
                }
                if (preg_match('/\p{Ll}{3,}ly$/u', $key) && ! in_array($key, ['assembly', 'family', 'supply', 'reply', 'anomaly', 'italy'], true)) {
                    continue;
                }
            }

            $score = (1 + log($count)) * (1 + 0.65 * ($words - 1));
            if ($words === 1 && preg_match('/\p{Ll}{3,}ed$/u', $key)) {
                $score *= 0.4;
            }

            $stems = explode(' ', $key);
            $inHeading = array_filter($stems, fn ($s) => isset($headingKeys[$s]));
            if ($inHeading) {
                $score *= 1 + 0.5 * count($inHeading) / count($stems);
            }

            if ($corpusSize > 1) {
                $df = $documentFrequencies[$key] ?? 0;
                $score *= log(($corpusSize + 1) / ($df + 1)) + 1;
            }

            $scored[$key] = [
                'term' => $this->displayForm($candidate['forms']),
                'key' => $key,
                'score' => $score,
                'count' => $count,
                'words' => $words,
            ];
        }
        if ($scored === []) {
            return [];
        }

        uasort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Drop words that are (almost) only ever seen inside a stronger phrase.
        $selected = [];
        foreach ($scored as $key => $candidate) {
            foreach ($selected as $chosen) {
                $chosenWords = explode(' ', $chosen['key']);
                $ownWords = explode(' ', $key);
                if ($chosen['words'] > $candidate['words']
                    && array_intersect($ownWords, $chosenWords) === $ownWords
                    && $candidate['count'] <= $chosen['count'] * 1.5) {
                    continue 2;
                }
            }
            $selected[$key] = $candidate;
            if (count($selected) >= $limit) {
                break;
            }
        }

        $max = max(array_column($selected, 'score')) ?: 1;

        return array_values(array_map(fn ($c) => [
            'term' => $c['term'],
            'key' => $c['key'],
            'score' => round($c['score'] / $max, 3),
            'count' => $c['count'],
        ], $selected));
    }

    /**
     * Candidate phrases (original-case words) in one sentence, each with the
     * lower-cased word that followed it.
     *
     * @return list<array{words: list<string>, next: ?string}>
     */
    public function phrases(string $sentence): array
    {
        $phrases = [];
        $fragments = preg_split('/[,.;:!?()\[\]{}"\/\\\\|<>=+*&%$#@~`^]+|\s[-–—]\s/u', $sentence) ?: [];

        foreach ($fragments as $fragment) {
            preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'\-]*/u', $fragment, $m);
            $current = [];
            foreach ($m[0] as $word) {
                $clean = trim($word, "-'");
                $lower = mb_strtolower($clean);
                $isBoundary = $lower === ''
                    || StopWords::isGeneric($lower)
                    || preg_match('/^\d+([.,]\d+)?$/u', $lower)
                    || (mb_strlen($lower) < 3 && ! preg_match('/^\p{Lu}{2}$/u', $clean));

                if ($isBoundary) {
                    $this->pushPhrase($phrases, $current, $lower);
                    $current = [];

                    continue;
                }
                $current[] = $clean;
            }
            $this->pushPhrase($phrases, $current, null);
        }

        return $phrases;
    }

    /** @param  list<array{words: list<string>, next: ?string}>  $phrases */
    private function pushPhrase(array &$phrases, array $words, ?string $next): void
    {
        if ($words === []) {
            return;
        }
        // Long runs are usually noise — keep bounded sub-phrases instead.
        $chunks = array_chunk($words, self::MAX_PHRASE_WORDS);
        foreach ($chunks as $i => $chunk) {
            $phrases[] = ['words' => $chunk, 'next' => $i === count($chunks) - 1 ? $next : null];
        }
    }

    /** @param  array<string, array<string, mixed>>  $candidates */
    private function collect(array &$candidates, array $words, ?string $next): void
    {
        $key = implode(' ', array_map(fn ($w) => Text::lightStem($w), $words));
        if (! isset($candidates[$key])) {
            $candidates[$key] = ['count' => 0, 'words' => count($words), 'forms' => [], 'verb_like' => 0];
        }
        $candidates[$key]['count']++;
        if ($next !== null && in_array($next, self::VERB_FOLLOWERS, true)) {
            $candidates[$key]['verb_like']++;
        }
        $form = implode(' ', $words);
        $candidates[$key]['forms'][$form] = ($candidates[$key]['forms'][$form] ?? 0) + 1;
    }

    /** Most frequent surface form; lower-cased unless it is an acronym. */
    private function displayForm(array $forms): string
    {
        arsort($forms);
        $form = (string) array_key_first($forms);

        return implode(' ', array_map(function ($word) {
            return preg_match('/^[\p{Lu}\d]{2,}s?$/u', $word) && preg_match('/\p{Lu}/u', $word)
                ? $word
                : mb_strtolower($word);
        }, explode(' ', $form)));
    }
}
