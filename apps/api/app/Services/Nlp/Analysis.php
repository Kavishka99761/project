<?php

namespace App\Services\Nlp;

/**
 * Result of analysing one document: structural blocks, scored sentences and
 * key phrases. Every downstream feature (summaries, highlights, concepts,
 * revision notes, flashcards, quizzes, mind maps) reads from this object, so
 * a document is only tokenised and ranked once per request.
 */
final class Analysis
{
    /**
     * @param  list<array{type: string, text: string}>  $blocks
     * @param  list<array{index: int, text: string, section: ?string, block: int, words: int, bullet: bool, terms: list<string>, kterms: list<string>, score: float, candidate: bool}>  $sentences
     *         terms = Snowball stems (similarity/retrieval), kterms = light stems (key-phrase matching)
     * @param  list<array{term: string, key: string, score: float, count: int}>  $keywords
     * @param  list<string>  $headings
     */
    public function __construct(
        public readonly ?string $title,
        public readonly array $blocks,
        public readonly array $sentences,
        public readonly array $keywords,
        public readonly array $headings,
        public readonly int $wordCount,
    ) {}

    public function isEmpty(): bool
    {
        return $this->sentences === [];
    }

    public function readingMinutes(): int
    {
        return Text::readingMinutes($this->wordCount);
    }

    /** Sentences eligible for summaries, best first. */
    public function rankedCandidates(): array
    {
        $candidates = array_values(array_filter($this->sentences, fn ($s) => $s['candidate']));
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }

    /** @return list<string> */
    public function keywordTerms(int $limit = 12): array
    {
        return array_slice(array_column($this->keywords, 'term'), 0, $limit);
    }

    /** Keywords that occur in a sentence. */
    public function keywordsIn(array $sentence): array
    {
        $found = [];
        $terms = ' '.implode(' ', $sentence['kterms']).' ';
        foreach ($this->keywords as $keyword) {
            if (str_contains($terms, ' '.$keyword['key'].' ')) {
                $found[] = $keyword;
            }
        }

        return $found;
    }
}
