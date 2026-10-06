<?php

namespace App\Services\Assistant;

use App\Enums\AcademicDateType;
use App\Services\Nlp\Text;
use Illuminate\Support\Carbon;

/**
 * KAVISHKA — finds important dates in academic documents and classifies
 * them as assignment deadlines, examinations, project milestones or other
 * academic events.
 *
 * Understands "20 October 2026", "Monday, 3rd November", "Oct 20, 2026",
 * "2026-10-20", "20/10/2026" (day-first), ranges ("1–12 December 2026",
 * which yield their start date) and nearby times ("by 23:59", "at 2 pm").
 * Dates without a year are placed on the next occurrence after the
 * document's reference date. Old revision/copyright dates are ignored.
 */
class DateExtractor
{
    private const MONTHS = [
        'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9, 'october' => 10,
        'oct' => 10, 'november' => 11, 'nov' => 11, 'december' => 12, 'dec' => 12,
    ];

    /** Keyword signals per type (weight 2 = decisive, 1 = supporting). */
    private const SIGNALS = [
        'assignment_deadline' => [
            2 => ['deadline', 'due', 'submission', 'submit', 'submitted', 'hand in', 'hand-in', 'handed in'],
            1 => ['coursework', 'assignment', 'portfolio', 'lab report', 'essay', 'worksheet', 'cw1', 'cw2', 'upload'],
        ],
        'exam' => [
            2 => ['exam', 'exams', 'examination', 'examinations', 'mid-term', 'midterm', 'final paper', 'in-class test', 'class test'],
            1 => ['test', 'quiz', 'paper', 'assessment centre', 'invigilated', 'sit'],
        ],
        'project_milestone' => [
            2 => ['milestone', 'proposal', 'interim', 'progress report', 'viva', 'demonstration', 'demo', 'final report', 'dissertation'],
            1 => ['project', 'presentation', 'prototype', 'poster', 'supervisor meeting', 'sprint', 'review', 'draft'],
        ],
        'event' => [
            2 => ['orientation', 'registration', 'enrolment', 'enrollment', 'induction', 'graduation', 'holiday', 'vacation', 'reading week', 'results', 'semester begins', 'semester starts', 'semester ends', 'term starts', 'term ends'],
            1 => ['workshop', 'seminar', 'guest lecture', 'ceremony', 'fair', 'open day', 'break', 'release', 'begins', 'starts', 'ends', 'commence', 'lecture'],
        ],
    ];

    private const IGNORE_CONTEXT = '/\b(revised|updated|published|copyright|version|last modified|issued|approved by|effective from|printed)\b/iu';

    /**
     * @param  list<string>  $pages
     * @return list<array{date: string, time: ?string, type: AcademicDateType, title: string, confidence: float, context: string, matched_text: string, page: int}>
     */
    public function extract(array $pages, ?Carbon $reference = null): array
    {
        $reference ??= now();
        $found = [];

        foreach ($pages as $pageIndex => $pageText) {
            $section = null;
            foreach (Text::blocks(Text::normalize($pageText)) as $block) {
                if ($block['type'] === 'heading') {
                    $section = $block['text'];
                    foreach ($this->matches($block['text'], $reference) as $match) {
                        $found[] = $this->candidate($match, $block['text'], null, $pageIndex + 1);
                    }

                    continue;
                }
                $sentences = $block['type'] === 'bullet' ? [$block['text']] : Text::sentences($block['text']);
                foreach ($sentences as $sentence) {
                    foreach ($this->matches($sentence, $reference) as $match) {
                        $found[] = $this->candidate($match, $sentence, $section, $pageIndex + 1);
                    }
                }
            }
        }

        $found = array_filter($found, function ($c) use ($reference) {
            return $c !== null
                && Carbon::parse($c['date'])->greaterThanOrEqualTo($reference->copy()->subMonths(6)->startOfDay())
                && Carbon::parse($c['date'])->lessThanOrEqualTo($reference->copy()->addYears(3));
        });

        // One entry per (date, type): keep the most confident reading.
        $unique = [];
        foreach ($found as $candidate) {
            $key = $candidate['date'].'|'.$candidate['type']->value.'|'.mb_strtolower($candidate['title']);
            if (! isset($unique[$key]) || $unique[$key]['confidence'] < $candidate['confidence']) {
                $unique[$key] = $candidate;
            }
        }

        usort($unique, fn ($a, $b) => [$a['date'], $a['time'] ?? ''] <=> [$b['date'], $b['time'] ?? '']);

        return array_values($unique);
    }

    /**
     * All date mentions in a sentence.
     *
     * @return list<array{date: Carbon, offset: int, length: int, text: string, explicit_year: bool, ambiguous: bool, time: ?string}>
     */
    public function matches(string $sentence, Carbon $reference): array
    {
        $months = implode('|', array_keys(self::MONTHS));
        $weekday = '(?:(?:mon|tue|tues|wed|thu|thur|thurs|fri|sat|sun)(?:day|nesday|rsday|urday)?\.?,?\s+)?';
        $ordinal = '(?:st|nd|rd|th)?';
        $patterns = [
            // 20 October 2026 · 3rd of Nov · 1–12 December 2026
            'dmy' => "/\b{$weekday}(?:(\d{1,2}){$ordinal}\s*(?:-|–|to|and)\s*)?(\d{1,2}){$ordinal}(?:\s+of)?\s+({$months})\.?,?(?:\s+(\d{4}))?\b/iu",
            // October 20, 2026 · Oct 20th
            'mdy' => "/\b{$weekday}({$months})\.?\s+(\d{1,2}){$ordinal}(?:\s*(?:-|–)\s*\d{1,2}{$ordinal})?(?:,?\s+(\d{4}))?\b/iu",
            // 2026-10-20
            'iso' => '/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u',
            // 20/10/2026 · 20.10.26
            'num' => '/\b(\d{1,2})[\/.](\d{1,2})[\/.](\d{4}|\d{2})\b/u',
        ];

        $results = [];
        $taken = [];
        foreach ($patterns as $kind => $pattern) {
            if (! preg_match_all($pattern, $sentence, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($all as $m) {
                $offset = $m[0][1];
                foreach ($taken as [$start, $end]) {
                    if ($offset >= $start && $offset < $end) {
                        continue 2;
                    }
                }

                [$year, $month, $day, $explicitYear, $ambiguous] = match ($kind) {
                    'dmy' => [$m[4][0] ?? '', self::MONTHS[mb_strtolower($m[3][0])], (int) $m[2][0], ($m[4][0] ?? '') !== '', false],
                    'mdy' => [$m[3][0] ?? '', self::MONTHS[mb_strtolower($m[1][0])], (int) $m[2][0], ($m[3][0] ?? '') !== '', false],
                    'iso' => [$m[1][0], (int) $m[2][0], (int) $m[3][0], true, false],
                    'num' => $this->numeric((int) $m[1][0], (int) $m[2][0], $m[3][0]),
                };

                // A range "1–12 December" starts on its first day.
                if ($kind === 'dmy' && ($m[1][0] ?? '') !== '' && (int) $m[1][0] > 0) {
                    $day = (int) $m[1][0];
                }

                $year = $year === '' ? null : (int) (strlen((string) $year) === 2 ? '20'.$year : $year);
                $date = $this->resolve($day, $month, $year, $reference);
                if (! $date) {
                    continue;
                }

                $length = strlen($m[0][0]);
                $taken[] = [$offset, $offset + $length];
                $results[] = [
                    'date' => $date,
                    'offset' => $offset,
                    'length' => $length,
                    'text' => trim($m[0][0]),
                    'explicit_year' => $explicitYear,
                    'ambiguous' => $ambiguous,
                    'time' => $this->timeNear($sentence, $offset + $length),
                ];
            }
        }

        usort($results, fn ($a, $b) => $a['offset'] <=> $b['offset']);

        // "28 September to 2 October 2026": the second date only ends the range.
        $kept = [];
        foreach ($results as $i => $result) {
            $previous = $results[$i - 1] ?? null;
            if ($previous) {
                $between = substr($sentence, $previous['offset'] + $previous['length'], $result['offset'] - $previous['offset'] - $previous['length']);
                if (preg_match('/^\s*(?:-|–|—|to|until|till|through)\s*$/iu', $between)) {
                    // A range without a year on its first half borrows the second's year.
                    if (! $previous['explicit_year'] && $result['explicit_year'] && $kept !== []) {
                        $last = array_key_last($kept);
                        $kept[$last]['date'] = $kept[$last]['date']->copy()->year($result['date']->year);
                        $kept[$last]['explicit_year'] = true;
                    }

                    continue;
                }
            }
            $kept[] = $result;
        }

        return $kept;
    }

    /** @return array{0: string, 1: int, 2: int, 3: bool, 4: bool} year, month, day, explicit, ambiguous */
    private function numeric(int $a, int $b, string $year): array
    {
        // Day-first (Sri Lanka / UK convention) unless impossible.
        if ($a > 12 && $b <= 12) {
            return [$year, $b, $a, true, false];
        }
        if ($b > 12 && $a <= 12) {
            return [$year, $a, $b, true, false];
        }

        return [$year, $b, $a, true, $a <= 12 && $b <= 12 && $a !== $b];
    }

    private function resolve(int $day, int $month, ?int $year, Carbon $reference): ?Carbon
    {
        $inferYear = $year === null;
        $year ??= $reference->year;
        if (! checkdate($month, $day, $year)) {
            return null;
        }
        $date = Carbon::create($year, $month, $day)->startOfDay();
        // Year omitted and that date already passed → its next occurrence.
        if ($inferYear && $date->lessThan($reference->copy()->subMonths(2))) {
            $date->addYear();
        }

        return $date;
    }

    private function timeNear(string $sentence, int $position): ?string
    {
        $tail = substr($sentence, $position, 40);
        if (preg_match('/^\s*(?:,|at|by|before|from|until|\(|-)?\s*(\d{1,2})(?:[:.](\d{2}))?\s*(a\.?m\.?|p\.?m\.?)/iu', $tail, $m)) {
            $hour = (int) $m[1] % 12 + (str_starts_with(mb_strtolower($m[3]), 'p') ? 12 : 0);

            return sprintf('%02d:%02d', $hour, (int) ($m[2] ?? 0));
        }
        if (preg_match('/^\s*(?:,|at|by|before|from|until|\(|-)?\s*([01]?\d|2[0-3])[:.]([0-5]\d)\b/u', $tail, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^\s*(?:,|at|by)?\s*(midnight|noon|midday)\b/iu', $tail, $m)) {
            return mb_strtolower($m[1]) === 'midnight' ? '23:59' : '12:00';
        }

        return null;
    }

    /** @param  array<string, mixed>  $match */
    private function candidate(array $match, string $sentence, ?string $section, int $page): ?array
    {
        if (preg_match(self::IGNORE_CONTEXT, $sentence)) {
            return null;
        }

        [$type, $strength] = $this->classify($sentence, $section);

        $confidence = 0.45
            + ($match['explicit_year'] ? 0.15 : 0.0)
            + min(0.25, 0.08 * $strength)
            + ($match['time'] ? 0.08 : 0.0)
            - ($match['ambiguous'] ? 0.2 : 0.0)
            - ($type === AcademicDateType::Other ? 0.15 : 0.0);

        return [
            'date' => $match['date']->toDateString(),
            'time' => $match['time'],
            'type' => $type,
            'title' => $this->title($sentence, $match, $type, $section),
            'confidence' => round(max(0.1, min(0.99, $confidence)), 3),
            'context' => Text::excerpt($sentence, 480),
            'matched_text' => mb_substr($match['text'], 0, 118),
            'page' => $page,
        ];
    }

    /** @return array{0: AcademicDateType, 1: int} */
    public function classify(string $sentence, ?string $section = null): array
    {
        $text = ' '.mb_strtolower($sentence.' '.($section ?? '')).' ';
        $scores = [];
        foreach (self::SIGNALS as $type => $groups) {
            $score = 0;
            foreach ($groups as $weight => $keywords) {
                foreach ($keywords as $keyword) {
                    // Allow simple inflections: release → released, milestone → milestones.
                    if (preg_match('/\b'.preg_quote($keyword, '/').'(?:s|es|d|ed)?\b/u', $text)) {
                        $score += $weight;
                    }
                }
            }
            $scores[$type] = $score;
        }

        // Explicit precedence on ties: deadline > exam > milestone > event.
        $best = 'other';
        $bestScore = 0;
        foreach (['assignment_deadline', 'exam', 'project_milestone', 'event'] as $type) {
            if ($scores[$type] > $bestScore) {
                $best = $type;
                $bestScore = $scores[$type];
            }
        }

        return [AcademicDateType::from($best), $bestScore];
    }

    /** Short human title: the subject of the sentence, without the date. */
    private function title(string $sentence, array $match, AcademicDateType $type, ?string $section): string
    {
        // Regex offsets are byte offsets, so slice with substr().
        $before = trim(substr($sentence, 0, $match['offset']));
        $after = trim(substr($sentence, $match['offset'] + $match['length']));

        $clean = function (string $text): string {
            $text = preg_replace('/\([^)]*\)/u', '', $text) ?? $text;
            $text = preg_replace('/^(?:•\s*)?(?:(?:please note that|note that|students (?:must|should|will)|all students|the|a|an|on|by|from|before|until|at|in|is|are|will be|and|:|-|–|,)\s+)+/iu', '', trim($text)) ?? $text;
            $text = preg_replace('/\s+(?:in|during)\s+the\s+week(?:\s+of)?\s*$/iu', '', $text) ?? $text;
            $text = preg_replace('/(?:\s+(?:is|are|will be|was|were|on|by|from|at|before|until|due|held|scheduled|takes? place|starts?|begins?|ends?|opens?|closes?|runs?|released|announced|published|between|of|for|the|a|in|:|,|-|–))+\s*$/iu', '', $text) ?? $text;

            return trim($text, " \t,;:-–.");
        };

        // Only the clause that holds the date: "…19 April 2027, and the final
        // dissertation deadline is 3 May" → "the final dissertation deadline is".
        $clauses = preg_split('/[;,]\s*(?:and|but|while|whereas|then)?\s*/u', $before) ?: [$before];
        $clause = trim((string) array_pop($clauses));
        // "…, and no later than <date>" belongs to the previous clause.
        if ($clauses && preg_match('/^(?:(?:no|not) later than|by|before|until|on|which|that|who|where)\b/iu', $clause)) {
            $clause = trim((string) array_pop($clauses));
        }
        $clause = Text::wordCount($clause) >= 2 ? $clause : $before;

        // Prefer the grammatical subject: "The SQL laboratory test is worth 10% and
        // will be held on" → "The SQL laboratory test".
        if (preg_match('/^(.*?)\s+(?:is|are|was|were|will|must|should|shall|may|can|has|have)\b/iu', $clause, $m)
            && Text::wordCount($m[1]) >= 2) {
            $clause = $m[1];
        }
        $title = $clean($clause);
        if (Text::wordCount($title) < 2) {
            $title = $clean(preg_replace('/^[,;:\s]*(?:at|by|before|\d{1,2}[:.]\d{2}\s*(?:am|pm)?)?\s*/iu', '', $after) ?? $after);
        }
        if (Text::wordCount($title) < 2 && $section) {
            $title = $section;
        }
        if (Text::wordCount($title) < 1) {
            $title = $type->label();
        }

        $words = preg_split('/\s+/u', $title) ?: [];
        if (count($words) > 10) {
            $title = implode(' ', array_slice($words, 0, 10)).'…';
        }

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
    }
}
