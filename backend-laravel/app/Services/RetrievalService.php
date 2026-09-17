<?php

namespace App\Services;

use App\Models\AcademicChunk;
use Illuminate\Support\Collection;

/**
 * KAVISHKA — retrieval-augmented generation (RAG) engine.
 *
 * Given a student question, it scores every indexed academic chunk by keyword /
 * token overlap and returns the best match together with a grounded answer that
 * cites its source (document title, section, page).
 *
 * This is a transparent lexical scorer — no external LLM or vector DB required —
 * which keeps the project runnable anywhere while still demonstrating real RAG.
 *
 * This class is the AUTHORITATIVE implementation. mobile-app/src/api/retrieve.js
 * is a deliberate 1:1 port — change the two together, in the same commit.
 * frontend-web/assets/js/assistant.js is a *simplified* whole-document variant
 * (pre-written answers, substring keyword match, raw threshold >= 2); it is not
 * a port of this class and shares neither its constants nor its scoring shape.
 */
class RetrievalService
{
    /** Minimum normalised score for a chunk to be considered a real match. */
    private const MATCH_THRESHOLD = 0.08;

    /**
     * Rank chunks against a query.
     *
     * @param  Collection<int, AcademicChunk>  $chunks
     * @return array<int, array{chunk: AcademicChunk, score: float}>
     */
    public function rank(string $query, Collection $chunks): array
    {
        $queryTokens = $this->tokens($query);
        if (empty($queryTokens)) {
            return [];
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = $this->score($queryTokens, $chunk);
            if ($score > 0) {
                $scored[] = ['chunk' => $chunk, 'score' => round($score, 4)];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Retrieve the single best chunk for a query, or null when nothing matches.
     */
    public function best(string $query, Collection $chunks): ?array
    {
        $ranked = $this->rank($query, $chunks);
        if (empty($ranked) || $ranked[0]['score'] < self::MATCH_THRESHOLD) {
            return null;
        }

        return $ranked[0];
    }

    /**
     * Build the bot reply. Returns the answer text plus an optional source block.
     *
     * @return array{answer:string, source: array<string,mixed>|null, chunk: AcademicChunk|null}
     */
    public function answer(string $query, Collection $chunks): array
    {
        $best = $this->best($query, $chunks);

        if ($best === null) {
            return [
                'answer' => "I couldn't find that in your indexed academic documents. "
                    .'Try rephrasing, or add the relevant handbook/guidelines to the '
                    .'knowledge base so I can ground my answer with a source reference.',
                'source' => null,
                'chunk'  => null,
            ];
        }

        /** @var AcademicChunk $chunk */
        $chunk = $best['chunk'];
        $document = $chunk->document;

        $answer = $this->compose($query, $chunk);

        $source = [
            'document' => $document?->title,
            'category' => $document?->category,
            'section'  => $chunk->section,
            'page'     => $chunk->page,
            'score'    => $best['score'],
            'chunk_id' => $chunk->id,
        ];

        return ['answer' => $answer, 'source' => $source, 'chunk' => $chunk];
    }

    private function compose(string $query, AcademicChunk $chunk): string
    {
        $excerpt = trim(preg_replace('/\s+/', ' ', $chunk->content) ?? $chunk->content);
        if (strlen($excerpt) > 400) {
            $excerpt = substr($excerpt, 0, 397).'...';
        }

        $where = $chunk->section ?: 'the document';
        if ($chunk->page) {
            $where .= " (p. {$chunk->page})";
        }

        return "Based on {$where}: {$excerpt}";
    }

    /**
     * Overlap score between query tokens and a chunk's keywords + content.
     *
     * Keywords are tokenised before matching: authored keyword entries may be
     * phrases ("special consideration", "late submission") while $queryTokens are
     * always single words, so comparing the raw strings makes every phrase keyword
     * inert. Routing them through tokens() keeps phrases working and drops stop
     * words from them. Mirrored exactly by score() in mobile-app/src/api/retrieve.js.
     */
    private function score(array $queryTokens, AcademicChunk $chunk): float
    {
        $keywords = $this->tokens(implode(' ', (array) ($chunk->keywords ?? [])));
        $contentFreq = $this->termFrequency($chunk->content.' '.($chunk->section ?? ''));

        $score = 0.0;
        foreach ($queryTokens as $token) {
            // Exact keyword hits are the strongest signal.
            if (in_array($token, $keywords, true)) {
                $score += 3.0;
                continue;
            }
            // Otherwise reward content term frequency (log-damped).
            if (isset($contentFreq[$token])) {
                $score += 1.0 + log($contentFreq[$token]);
            }
        }

        // Normalise by query size so long queries don't dominate unfairly.
        return $score / (count($queryTokens) + 1);
    }

    /**
     * Raw term counts over a text — no stop-word filtering, no de-duplication.
     *
     * Deliberately NOT built on tokens(): that helper de-duplicates, so counting
     * its output pins every term to a frequency of 1 and silently collapses the
     * `1 + log(freq)` damping in score() to a flat `1 + 0`. Query parsing wants
     * unique meaningful terms; content scoring wants true repetition counts.
     *
     * Stop words and 1-2 character terms may appear in the counts — harmless,
     * because query tokens are already filtered and can never equal them.
     *
     * @return array<string,int>
     */
    private function termFrequency(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', strtolower(strip_tags($text)), $m);

        return array_count_values($m[0] ?? []);
    }

    /**
     * Unique, meaningful query terms (lower-cased, stop words and <=2 char removed).
     *
     * @return array<int,string>
     */
    private function tokens(string $text): array
    {
        $stop = ['what', 'when', 'where', 'how', 'why', 'the', 'a', 'an', 'is',
            'are', 'do', 'does', 'i', 'my', 'to', 'of', 'in', 'for', 'and', 'me'];
        $clean = strtolower(strip_tags($text));
        preg_match_all('/[a-z0-9]+/', $clean, $m);
        $tokens = array_filter($m[0] ?? [], fn ($t) => strlen($t) > 2 && ! in_array($t, $stop, true));

        return array_values(array_unique($tokens));
    }
}
