<?php

namespace App\Services\Nlp;

use Wamania\Snowball\Stemmer\Stemmer;
use Wamania\Snowball\StemmerFactory;

/**
 * Low-level text toolkit shared by every NLP feature: normalisation,
 * structural blocks (headings / paragraphs / bullets), sentence splitting,
 * tokenisation and stemming. Pure PHP — no external services.
 */
final class Text
{
    private const ABBREVIATIONS = [
        'e.g.', 'i.e.', 'etc.', 'vs.', 'cf.', 'al.', 'approx.', 'dr.', 'mr.', 'mrs.', 'ms.', 'prof.',
        'fig.', 'figs.', 'no.', 'nos.', 'vol.', 'ed.', 'eds.', 'pp.', 'p.', 'sec.', 'ch.', 'st.', 'jr.',
        'sr.', 'inc.', 'ltd.', 'co.', 'dept.', 'univ.', 'jan.', 'feb.', 'mar.', 'apr.', 'jun.', 'jul.',
        'aug.', 'sep.', 'sept.', 'oct.', 'nov.', 'dec.', 'mon.', 'tue.', 'wed.', 'thu.', 'fri.', 'sat.',
        'sun.', 'min.', 'max.', 'hr.', 'hrs.', 'est.', 'ref.', 'eq.',
    ];

    private static ?Stemmer $stemmer = null;

    /** @var array<string, string> */
    private static array $stemCache = [];

    /**
     * Clean raw extracted text: unify line endings, quotes and dashes, join
     * words hyphenated across line breaks and squeeze whitespace.
     */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r", "\f", "\v"], "\n", $text);
        $text = strtr($text, [
            "\u{FB01}" => 'fi', "\u{FB02}" => 'fl', "\u{FB00}" => 'ff', "\u{FB03}" => 'ffi',
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2013}" => '-', "\u{2014}" => ' - ', "\u{00A0}" => ' ', "\u{2009}" => ' ',
            "\u{00AD}" => '', "\u{2026}" => '...', "\t" => ' ',
        ]);
        $text = preg_replace('/(\p{L})-\n(\p{Ll})/u', '$1$2', $text) ?? $text;
        $text = preg_replace('/[\x{0000}-\x{0008}\x{000E}-\x{001F}\x{007F}]/u', '', $text) ?? $text;
        $text = preg_replace('/ {2,}/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Split normalised text into structural blocks.
     *
     * @return list<array{type: 'heading'|'paragraph'|'bullet', text: string}>
     */
    public static function blocks(string $text): array
    {
        $blocks = [];
        $buffer = [];
        $flush = function () use (&$buffer, &$blocks): void {
            if ($buffer !== []) {
                $blocks[] = ['type' => 'paragraph', 'text' => trim(implode(' ', $buffer))];
                $buffer = [];
            }
        };

        $lines = explode("\n", $text);
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                $flush();

                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/u', $line, $m)) {
                $flush();
                $blocks[] = ['type' => 'heading', 'text' => trim($m[1], " #\t")];

                continue;
            }

            if (preg_match('/^(?:[\x{2022}\x{25AA}\x{25CF}\x{25E6}\x{2023}\x{2043}*\-]|\d{1,2}[.)]|[a-z][.)])\s+(.+)$/u', $line, $m)
                && ! self::looksLikeHeading($line, $lines[$i - 1] ?? '', $lines[$i + 1] ?? '')) {
                $flush();
                $blocks[] = ['type' => 'bullet', 'text' => trim($m[1])];

                continue;
            }

            if (self::looksLikeHeading($line, $lines[$i - 1] ?? '', $lines[$i + 1] ?? '')) {
                $flush();
                $blocks[] = ['type' => 'heading', 'text' => rtrim($line, ':')];

                continue;
            }

            $buffer[] = $line;
        }
        $flush();

        return $blocks;
    }

    /**
     * Heuristic heading detector for text extracted from PDFs/slides, where
     * headings arrive as short stand-alone lines without end punctuation.
     */
    public static function looksLikeHeading(string $line, string $previous = '', string $next = ''): bool
    {
        $line = trim($line);
        $words = preg_split('/\s+/u', $line) ?: [];
        $wordCount = count($words);

        if ($wordCount === 0 || $wordCount > 12 || mb_strlen($line) > 90 || mb_strlen($line) < 3) {
            return false;
        }
        if (preg_match('/[.,;!?]$/u', $line) || ! preg_match('/\p{L}/u', $line)) {
            return false;
        }

        // "3.2 Late submissions", "Chapter 4: Normal forms", "Week 5 - Joins"
        if (preg_match('/^(?:\d+(?:\.\d+)*\.?\s+\p{Lu}|(?:chapter|section|part|unit|week|lecture|topic|appendix)\s+[\dIVX]+)/iu', $line)) {
            return true;
        }

        $isolated = trim($previous) === '' || trim($next) === '';
        if (mb_strtoupper($line) === $line && preg_match('/\p{Lu}{3,}/u', $line)) {
            return true;
        }

        $significant = array_filter($words, fn ($w) => mb_strlen($w) > 3);
        if ($significant === []) {
            return false;
        }
        $capitalised = array_filter($significant, fn ($w) => preg_match('/^[\p{Lu}\d]/u', $w));
        $titleCase = count($capitalised) / count($significant) >= 0.75;

        return $titleCase && ($isolated || str_ends_with($line, ':')) && $wordCount <= 10;
    }

    /**
     * Sentence segmentation that survives abbreviations, initials, decimals
     * and quoted endings.
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $protected = $text;
        foreach (self::ABBREVIATIONS as $abbr) {
            $protected = preg_replace('/(?<![\p{L}])'.preg_quote($abbr, '/').'/iu', str_replace('.', '§', $abbr), $protected) ?? $protected;
        }
        // Single-letter initials ("Edgar F. Codd", "J. Smith") — only when the
        // neighbouring word is capitalised, so "B determines C. Most…" still splits.
        $protected = preg_replace('/^(\p{Lu})\.(?=\s\p{Lu})/u', '$1§', $protected) ?? $protected;
        $protected = preg_replace_callback(
            '/(?<=\s)(\S+)(\s)(\p{Lu})\.(?=\s\p{Lu}\p{Ll})/u',
            fn ($m) => preg_match('/^\p{Lu}/u', $m[1]) && ! preg_match('/[.!?]$/u', $m[1])
                ? $m[1].$m[2].$m[3].'§'
                : $m[0],
            $protected,
        ) ?? $protected;

        $parts = preg_split('/(?<=[.!?])["\')\]]*\s+(?=["\'(\[]?[\p{Lu}\p{N}])/u', $protected) ?: [$protected];

        $sentences = [];
        foreach ($parts as $part) {
            $part = trim(str_replace('§', '.', $part));
            if ($part !== '') {
                $sentences[] = $part;
            }
        }

        return $sentences;
    }

    /**
     * Lower-cased word tokens (letters/digits, inner apostrophes kept).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'][\p{L}]+)?/u', mb_strtolower($text), $m);

        return array_map(fn ($w) => preg_replace("/'s$/u", '', $w) ?? $w, $m[0]);
    }

    /**
     * Content terms: stop words removed, then stemmed. Used for indexing,
     * similarity and retrieval.
     *
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $terms = [];
        foreach (self::words($text) as $word) {
            if (mb_strlen($word) < 2 || StopWords::isCommon($word) || ctype_digit($word) && mb_strlen($word) < 4) {
                continue;
            }
            $terms[] = self::stem($word);
        }

        return $terms;
    }

    /** @return array<string, int> term => frequency */
    public static function termFrequencies(string $text): array
    {
        return array_count_values(self::terms($text));
    }

    /**
     * Light inflectional stemmer for key phrases: folds plurals and
     * possessives ("dependencies" → "dependency") without the aggressive
     * conflation of Snowball ("normalization" must not become "normal").
     */
    public static function lightStem(string $word): string
    {
        $w = mb_strtolower($word);
        if (mb_strlen($w) <= 3 || preg_match('/\d/u', $w)) {
            return $w;
        }
        $w = preg_replace("/'s$/u", '', $w) ?? $w;

        return match (true) {
            (bool) preg_match('/[^aeiou]ies$/u', $w) => mb_substr($w, 0, -3).'y',
            (bool) preg_match('/(sses|shes|ches|xes|zes)$/u', $w) => mb_substr($w, 0, -2),
            (bool) preg_match('/(ss|us|is)$/u', $w),
            in_array($w, ['alias', 'canvas', 'atlas', 'bias', 'whereas', 'chaos', 'series', 'species'], true) => $w,
            str_ends_with($w, 's') => mb_substr($w, 0, -1),
            default => $w,
        };
    }

    /**
     * Light-stemmed content words (stop words removed) — the vocabulary in
     * which key phrases are matched against sentences.
     *
     * @return list<string>
     */
    public static function keyTerms(string $text): array
    {
        $terms = [];
        foreach (self::words($text) as $word) {
            if (mb_strlen($word) >= 2 && ! StopWords::isCommon($word)) {
                $terms[] = self::lightStem($word);
            }
        }

        return $terms;
    }

    public static function stem(string $word): string
    {
        if (isset(self::$stemCache[$word])) {
            return self::$stemCache[$word];
        }
        self::$stemmer ??= StemmerFactory::create('english');

        try {
            $stem = self::$stemmer->stem($word);
        } catch (\Throwable) {
            $stem = $word;
        }

        if (count(self::$stemCache) > 50000) {
            self::$stemCache = [];
        }

        return self::$stemCache[$word] = $stem;
    }

    public static function wordCount(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}]+/u', $text);
    }

    /** Average adult reading speed ~ 220 words per minute. */
    public static function readingMinutes(int $words): int
    {
        return $words === 0 ? 0 : max(1, (int) round($words / 220));
    }

    /** Cosine similarity between two sparse vectors (term => weight). */
    public static function cosine(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }
        $dot = 0.0;
        foreach ($a as $term => $weight) {
            if (isset($b[$term])) {
                $dot += $weight * $b[$term];
            }
        }
        if ($dot === 0.0) {
            return 0.0;
        }
        $normA = sqrt(array_sum(array_map(fn ($w) => $w * $w, $a)));
        $normB = sqrt(array_sum(array_map(fn ($w) => $w * $w, $b)));

        return $normA > 0 && $normB > 0 ? $dot / ($normA * $normB) : 0.0;
    }

    /** Shorten text on a word boundary. */
    public static function excerpt(string $text, int $max = 220): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space ?: $max), ' ,;:-').'…';
    }
}
