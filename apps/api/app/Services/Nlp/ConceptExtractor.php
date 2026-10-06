<?php

namespace App\Services\Nlp;

/**
 * Identifies the key concepts of a document.
 *
 * 1. Definitions — sentences shaped like "X is a … / X refers to … /
 *    X is defined as …" give a concept *and* its explanation.
 * 2. Key phrases — the strongest keywords, each explained by the highest
 *    ranked sentence that mentions it.
 */
final class ConceptExtractor
{
    private const DEFINITION_PATTERN = '/^(?:an?\s+|the\s+)?(?<term>[\p{L}\p{N}][\p{L}\p{N}\s\-\/()\']{1,70}?)\s+(?<connector>is defined as|are defined as|can be defined as|is known as|are known as|refers to|refer to|is a term for|is called|are called|describes|represents|means|is|are)\s+(?<definition>.{12,})$/iu';

    /** Weak connectors need an article after them ("X is a …", "X are the …"). */
    private const WEAK_CONNECTORS = ['is', 'are'];

    private const PRONOUNS = ['it', 'this', 'that', 'these', 'those', 'they', 'there', 'he', 'she', 'we', 'you', 'i', 'which', 'what', 'one', 'each', 'all', 'some', 'here', 'such', 'another', 'other'];

    /** Function words allowed inside a concept name ("separation of concerns"). */
    private const TERM_JOINERS = ['of', 'and', 'in', 'for', 'to'];

    /**
     * @return list<array{term: string, definition: string, importance: float, source: string, sentence: int}>
     */
    public function extract(Analysis $analysis, int $limit = 8): array
    {
        $concepts = [];

        foreach ($analysis->sentences as $sentence) {
            if (! $sentence['candidate'] || ! ($definition = $this->definition($sentence['text']))) {
                continue;
            }
            $key = $this->key($definition['term']);
            if (! isset($concepts[$key]) || $concepts[$key]['importance'] < $sentence['score']) {
                $concepts[$key] = [
                    'term' => $definition['term'],
                    'definition' => $definition['definition'],
                    'importance' => round(0.55 + 0.45 * $sentence['score'], 3),
                    'source' => 'definition',
                    'sentence' => $sentence['index'],
                ];
            }
        }

        foreach ($analysis->keywords as $keyword) {
            $key = $keyword['key'];
            if (isset($concepts[$key]) || $this->coveredBy($key, array_keys($concepts))) {
                continue;
            }
            $best = null;
            foreach ($analysis->sentences as $sentence) {
                $terms = ' '.implode(' ', $sentence['kterms']).' ';
                if ($sentence['candidate'] && str_contains($terms, ' '.$key.' ')
                    && ($best === null || $sentence['score'] > $best['score'])) {
                    $best = $sentence;
                }
            }
            if ($best) {
                $concepts[$key] = [
                    'term' => $this->capitalise($keyword['term']),
                    'definition' => trim($best['text']),
                    'importance' => round($keyword['score'] * 0.9, 3),
                    'source' => 'keyword',
                    'sentence' => $best['index'],
                ];
            }
        }

        usort($concepts, fn ($a, $b) => $b['importance'] <=> $a['importance']);

        return array_slice(array_values($concepts), 0, $limit);
    }

    /** @return array{term: string, definition: string}|null */
    public function definition(string $sentence): ?array
    {
        $sentence = trim($sentence);
        if (Text::wordCount($sentence) > 60 || ! preg_match(self::DEFINITION_PATTERN, $sentence, $m)) {
            return null;
        }

        $term = trim($m['term'], " \t\n\r\0\x0B-'()");
        $connector = mb_strtolower($m['connector']);
        $definition = rtrim(trim($m['definition']), '.');

        if (in_array($connector, self::WEAK_CONNECTORS, true)
            && ! preg_match('/^(a|an|the|one of|any)\s/iu', $definition)) {
            return null;
        }

        $words = preg_split('/\s+/u', $term) ?: [];
        $first = mb_strtolower($words[0] ?? '');
        if (count($words) > 5 || in_array($first, self::PRONOUNS, true) || StopWords::isCommon($first)) {
            return null;
        }
        if (preg_match('/ing$/u', $first) && count($words) > 1) {
            return null; // "Identifying the keys …" is an activity, not a concept
        }
        foreach (array_slice($words, 1) as $word) {
            $lower = mb_strtolower($word);
            if (StopWords::isCommon($lower) && ! in_array($lower, self::TERM_JOINERS, true)) {
                return null;
            }
        }

        return [
            'term' => $this->capitalise($term),
            'definition' => mb_strtoupper(mb_substr($definition, 0, 1)).mb_substr($definition, 1).'.',
        ];
    }

    /** Light-stemmed identity of a concept name (singular/plural agnostic). */
    public function key(string $term): string
    {
        return implode(' ', array_map(fn ($w) => Text::lightStem($w), Text::words($term)));
    }

    private function capitalise(string $term): string
    {
        return preg_match('/^\p{Lu}{2,}/u', $term) ? $term : mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);
    }

    private function coveredBy(string $key, array $existing): bool
    {
        foreach ($existing as $other) {
            if ($other !== $key && (str_contains(" $other ", " $key ") || str_contains(" $key ", " $other "))) {
                return true;
            }
        }

        return false;
    }
}
