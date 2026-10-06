<?php

namespace App\Services\Assistant;

use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeChunk;
use App\Services\Nlp\Text;
use Illuminate\Support\Collection;

/**
 * KAVISHKA — BM25 passage retrieval over the student's knowledge base.
 *
 * Relevance is reported on an absolute 0–1 scale that combines how much of
 * the question's (IDF-weighted) vocabulary a passage covers with the BM25
 * score, so the assistant can honestly say "I couldn't find that" instead of
 * presenting the least-bad passage as an answer.
 */
class Bm25Retriever
{
    /** @var array<string, float> IDF of each query term in the last search */
    private array $lastIdf = [];

    /** Academic vocabulary bridges (expansion terms get 40 % weight). */
    private const SYNONYMS = [
        'deadline' => ['due', 'submission', 'submit'],
        'due' => ['deadline', 'submission'],
        'submit' => ['submission', 'deadline', 'upload'],
        'late' => ['penalty', 'overdue', 'extension'],
        'penalty' => ['late', 'deduction', 'deduct', 'cap'],
        'exam' => ['examination', 'test', 'assessment'],
        'examination' => ['exam', 'assessment'],
        'test' => ['exam', 'assessment', 'quiz'],
        'resit' => ['repeat', 'referral', 'reassessment'],
        'repeat' => ['resit', 'referral', 'reassessment'],
        'plagiarism' => ['misconduct', 'similarity', 'collusion', 'cheating'],
        'cheat' => ['misconduct', 'plagiarism', 'malpractice'],
        'extension' => ['mitigating', 'extenuating', 'consideration', 'deferral'],
        'sick' => ['illness', 'medical', 'mitigating'],
        'ill' => ['illness', 'medical', 'mitigating'],
        'grade' => ['mark', 'gpa', 'grading', 'classification'],
        'mark' => ['grade', 'score', 'grading'],
        'pass' => ['minimum', 'threshold', 'passing'],
        'attendance' => ['attend', 'absence', 'absent'],
        'absent' => ['absence', 'attendance'],
        'word' => ['length', 'limit'],
        'length' => ['word', 'limit'],
        'supervisor' => ['advisor', 'tutor', 'mentor'],
        'viva' => ['oral', 'defence', 'presentation'],
        'fail' => ['failure', 'resit', 'referral'],
        'appeal' => ['complaint', 'review'],
        'register' => ['registration', 'enrol', 'enrolment'],
        'lecturer' => ['tutor', 'instructor', 'staff'],
        'report' => ['dissertation', 'thesis'],
    ];

    /**
     * @param  array{categories?: list<string>, document_ids?: list<int>}  $scope
     * @return list<array{chunk: KnowledgeChunk, score: float, relevance: float, coverage: float}>
     */
    public function search(int $userId, string $query, array $scope = [], int $limit = 5): array
    {
        $weights = $this->queryWeights($query);
        if ($weights === []) {
            return [];
        }

        $chunks = $this->candidates($userId, $scope, array_keys($weights));
        if ($chunks->isEmpty()) {
            return [];
        }

        $n = $chunks->count();
        $avgdl = max(1.0, (float) $chunks->avg('token_count'));
        $k1 = (float) config('edusmart.assistant.bm25_k1', 1.4);
        $b = (float) config('edusmart.assistant.bm25_b', 0.72);

        $df = [];
        foreach ($chunks as $chunk) {
            foreach (array_keys($weights) as $term) {
                if (isset($chunk->terms[$term])) {
                    $df[$term] = ($df[$term] ?? 0) + 1;
                }
            }
        }

        $idf = [];
        foreach (array_keys($weights) as $term) {
            $idf[$term] = log(1 + ($n - ($df[$term] ?? 0) + 0.5) / (($df[$term] ?? 0) + 0.5));
        }
        $this->lastIdf = $idf;
        // Only the student's own question words count toward "coverage".
        $original = array_filter($weights, fn ($w) => $w >= 1.0);
        $totalIdf = array_sum(array_map(fn ($t) => $idf[$t], array_keys($original))) ?: 1.0;

        $bigrams = $this->bigrams($query);
        $results = [];
        foreach ($chunks as $chunk) {
            $terms = $chunk->terms ?? [];
            $dl = max(1, (int) $chunk->token_count);
            $score = 0.0;
            $covered = 0.0;
            foreach ($weights as $term => $weight) {
                $tf = $terms[$term] ?? 0;
                if ($tf === 0) {
                    continue;
                }
                $score += $weight * $idf[$term] * ($tf * ($k1 + 1)) / ($tf + $k1 * (1 - $b + $b * $dl / $avgdl));
                if ($weight >= 1.0) {
                    $covered += $idf[$term];
                }
            }
            if ($score <= 0) {
                continue;
            }

            $haystack = mb_strtolower($chunk->content);
            foreach ($bigrams as $bigram) {
                if (str_contains($haystack, $bigram)) {
                    $score *= 1.12;
                }
            }
            $sectionTerms = $chunk->section ? Text::terms($chunk->section) : [];
            if ($sectionTerms && array_intersect($sectionTerms, array_keys($original))) {
                $score *= 1.15;
            }

            $coverage = min(1.0, $covered / $totalIdf);
            $results[] = [
                'chunk' => $chunk,
                'score' => round($score, 4),
                'coverage' => round($coverage, 4),
                'relevance' => round(0.65 * $coverage + 0.35 * ($score / ($score + 4)), 4),
            ];
        }

        usort($results, fn ($a, $b) => $b['relevance'] <=> $a['relevance'] ?: $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Query term weights scaled by how distinctive each term is in the
     * knowledge base (IDF from the last search), so "proposal" outweighs
     * "final" when composing an answer.
     *
     * @return array<string, float>
     */
    public function distinctiveWeights(string $query): array
    {
        $weights = $this->queryWeights($query);
        foreach ($weights as $term => $weight) {
            $weights[$term] = $weight * max(0.2, $this->lastIdf[$term] ?? 1.0);
        }

        return $weights;
    }

    /** @return list<string> stems of the words the student actually typed */
    public function originalTerms(string $query): array
    {
        return array_keys(array_filter($this->queryWeights($query), fn ($w) => $w >= 1.0));
    }

    /** @return array<string, float> stemmed term => weight */
    public function queryWeights(string $query): array
    {
        $weights = [];
        foreach (Text::words($query) as $word) {
            if (mb_strlen($word) < 2 || \App\Services\Nlp\StopWords::isCommon($word)) {
                continue;
            }
            $weights[Text::stem($word)] = 1.0;
            $base = Text::lightStem($word);
            $synonyms = self::SYNONYMS[$word] ?? self::SYNONYMS[$base]
                ?? self::SYNONYMS[preg_replace('/(ing|ed)$/u', '', $base) ?? $base] ?? [];
            foreach ($synonyms as $synonym) {
                $weights[Text::stem($synonym)] ??= 0.4;
            }
        }

        return $weights;
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, KnowledgeChunk>
     */
    private function candidates(int $userId, array $scope, array $terms): Collection
    {
        $query = KnowledgeChunk::query()
            ->select(['id', 'knowledge_document_id', 'user_id', 'chunk_index', 'section', 'page_number', 'content', 'token_count', 'terms'])
            ->where('user_id', $userId)
            ->whereHas('document', function ($q) use ($scope) {
                $q->whereNull('deleted_at')->where('status', KnowledgeStatus::Indexed);
                if (! empty($scope['categories'])) {
                    $q->whereIn('category', $scope['categories']);
                }
                if (! empty($scope['document_ids'])) {
                    $q->whereIn('id', $scope['document_ids']);
                }
            });

        // Large knowledge bases: pre-filter in SQL Server on raw words first.
        if ((clone $query)->count() > 1500) {
            $query->where(function ($q) use ($terms) {
                foreach (array_slice($terms, 0, 8) as $term) {
                    $q->orWhere('content', 'like', '%'.$term.'%');
                }
            })->limit(1500);
        }

        return $query->with('document:id,title,category,pages,module_id')->get();
    }

    /** @return list<string> lower-cased adjacent content-word pairs */
    private function bigrams(string $query): array
    {
        $words = array_values(array_filter(Text::words($query), fn ($w) => ! \App\Services\Nlp\StopWords::isCommon($w)));
        $pairs = [];
        for ($i = 0; $i < count($words) - 1; $i++) {
            $pairs[] = $words[$i].' '.$words[$i + 1];
        }

        return $pairs;
    }
}
