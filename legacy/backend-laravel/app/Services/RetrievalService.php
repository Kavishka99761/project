<?php

namespace App\Services;

use App\Models\AcademicChunk;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * KAVISHKA — retrieval-augmented generation (RAG) engine.
 *
 * Given a student question, it scores every indexed academic chunk with
 * sentence-transformers and returns the best match with its source citation.
 * Lexical scoring remains available when the Python service is not configured
 * or cannot be reached.
 *
 * This class is the AUTHORITATIVE online implementation. The
 * mobile-app/src/api/retrieve.js is the lexical offline fallback.
 * frontend-web/assets/js/assistant.js is a *simplified* whole-document variant
 * (pre-written answers, substring keyword match, raw threshold >= 2); it is not
 * a port of this class and shares neither its constants nor its scoring shape.
 */
class RetrievalService
{
    /** Minimum score for a chunk to be considered a match in lexical fallback mode. */
    private const MATCH_THRESHOLD = 0.08;

    /** Minimum cosine similarity for a semantic match. */
    private const SEMANTIC_MATCH_THRESHOLD = 0.30;

    /**
     * Rank chunks against a query.
     *
     * @param  Collection<int, AcademicChunk>  $chunks
     * @return array<int, array{chunk: AcademicChunk, score: float}>
     */
    public function rank(string $query, Collection $chunks): array
    {
        [$semanticRanked] = $this->semanticRank($query, $chunks);
        if ($semanticRanked !== null) {
            return $semanticRanked;
        }

        return $this->rankLexically($query, $chunks);
    }

    /**
     * Retrieve the single best chunk for a query, or null when nothing matches.
     */
    public function best(string $query, Collection $chunks): ?array
    {
        [$ranked, $semantic] = $this->semanticRank($query, $chunks);
        if ($ranked === null) {
            $ranked = $this->rankLexically($query, $chunks);
        }

        $threshold = $semantic ? self::SEMANTIC_MATCH_THRESHOLD : self::MATCH_THRESHOLD;
        if (empty($ranked) || $ranked[0]['score'] < $threshold) {
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

    /**
     * @return array{0: array<int, array{chunk: AcademicChunk, score: float}>|null, 1: bool}
     */
    private function semanticRank(string $query, Collection $chunks): array
    {
        $url = (string) config('services.sentence_transformers.url', '');
        if ($url === '' || trim($query) === '' || $chunks->isEmpty()) {
            return [null, false];
        }

        $passages = $chunks->values()->map(fn (AcademicChunk $chunk) => implode(' ', array_filter([
            $chunk->section,
            implode(' ', (array) ($chunk->keywords ?? [])),
            $chunk->content,
        ])))->all();

        try {
            $response = Http::timeout((int) config('services.sentence_transformers.timeout', 60))
                ->post(rtrim($url, '/').'/rank', [
                    'query' => $query,
                    'passages' => $passages,
                ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Sentence-transformers retrieval failed; using lexical retrieval.', [
                'message' => $exception->getMessage(),
            ]);

            return [null, false];
        }

        $scores = $response->json('scores');
        if (! is_array($scores) || count($scores) !== count($passages)) {
            throw new UnexpectedValueException('Sentence-transformers returned an invalid score list.');
        }

        $ranked = [];
        foreach ($scores as $index => $score) {
            if (! is_float($score) && ! is_int($score)) {
                throw new UnexpectedValueException('Sentence-transformers returned a non-numeric score.');
            }
            if ($score <= 0) {
                continue;
            }

            $ranked[] = ['chunk' => $chunks->values()->get($index), 'score' => round((float) $score, 4)];
        }
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [$ranked, true];
    }

    /**
     * @param  Collection<int, AcademicChunk>  $chunks
     * @return array<int, array{chunk: AcademicChunk, score: float}>
     */
    private function rankLexically(string $query, Collection $chunks): array
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
