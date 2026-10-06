<?php

namespace App\Services\Nlp;

/**
 * Turns an analysed document into bullet-point revision notes, grouped under
 * the document's headings (or, for unstructured notes, its key concepts).
 */
final class RevisionNotesBuilder
{
    private const OPENERS = [
        'however', 'in addition', 'additionally', 'furthermore', 'moreover', 'therefore', 'thus', 'hence',
        'as a result', 'consequently', 'in other words', 'in contrast', 'similarly', 'also', 'finally',
        'firstly', 'secondly', 'lastly', 'in summary', 'to summarise', 'to summarize', 'overall', 'note that',
        'it is important to note that', 'importantly', 'in practice', 'for this reason',
    ];

    /**
     * @return list<array{heading: string, bullets: list<string>}>
     */
    public function build(Analysis $analysis, int $maxBullets = 18): array
    {
        $ranked = $analysis->rankedCandidates();
        if ($ranked === []) {
            return [];
        }
        $picked = array_slice($ranked, 0, max(4, min($maxBullets, (int) ceil(count($ranked) * 0.35))));
        usort($picked, fn ($a, $b) => $a['index'] <=> $b['index']);

        $groups = [];
        $sections = array_unique(array_filter(array_column($picked, 'section')));
        if (count($sections) >= 2) {
            foreach ($picked as $sentence) {
                $groups[$sentence['section'] ?? 'Overview'][] = $this->bullet($sentence['text']);
            }
        } else {
            $keywords = array_slice($analysis->keywords, 0, 5);
            foreach ($picked as $sentence) {
                $heading = 'Key points';
                foreach ($keywords as $keyword) {
                    if (str_contains(' '.implode(' ', $sentence['kterms']).' ', ' '.$keyword['key'].' ')) {
                        $heading = mb_strtoupper(mb_substr($keyword['term'], 0, 1)).mb_substr($keyword['term'], 1);
                        break;
                    }
                }
                $groups[$heading][] = $this->bullet($sentence['text']);
            }
        }

        $notes = [];
        foreach ($groups as $heading => $bullets) {
            $notes[] = ['heading' => (string) $heading, 'bullets' => array_values(array_unique($bullets))];
        }

        return $notes;
    }

    /** Condense a sentence into a revision bullet. */
    public function bullet(string $sentence): string
    {
        $text = trim($sentence);
        foreach (self::OPENERS as $opener) {
            if (preg_match('/^'.preg_quote($opener, '/').'\s*,?\s+/iu', $text)) {
                $text = preg_replace('/^'.preg_quote($opener, '/').'\s*,?\s+/iu', '', $text) ?? $text;
                break;
            }
        }
        $text = rtrim($text, " .;:");

        $words = preg_split('/\s+/u', $text) ?: [];
        if (count($words) > 30) {
            // Prefer cutting at a clause boundary before falling back to a hard cut.
            $short = implode(' ', array_slice($words, 0, 30));
            foreach ([';', ' - ', ', which', ', because', ', where', ', while'] as $boundary) {
                $pos = mb_strpos($short, $boundary);
                if ($pos !== false && $pos > 40) {
                    $short = mb_substr($short, 0, $pos);
                    break;
                }
            }
            $text = rtrim($short, ' ,;:').(mb_strlen($short) < mb_strlen($text) ? '…' : '');
        }

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
