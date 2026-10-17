<?php

namespace App\Services\Assistant;

use App\Services\Ai\LlmClient;
use App\Services\Nlp\Text;

/**
 * KAVISHKA — composes a grounded answer from retrieved passages.
 *
 * Two answer paths:
 *
 *  1. **Grounded** — the knowledge base has relevant passages. The answer is
 *     built from their most relevant sentences, chosen with awareness of what
 *     the question asks for (a date for "when", a number for "how many", a
 *     rule for "can I…"), and every sentence carries a [n] citation mapping
 *     to a document, section and page. When an LLM is configured it may only
 *     *rephrase* those cited passages; if its output drops the citations the
 *     extractive answer wins, so a source is never invented.
 *
 *  2. **General** — nothing in the student's documents covers the question.
 *     With LLM_GENERAL_ANSWERS=true the configured model (Qwen3 8B by
 *     default) answers from its own knowledge, like any AI assistant, and the
 *     answer is labelled as general knowledge rather than as something found
 *     in the uploaded documents. With no model reachable, the older "not in
 *     your documents" reply is returned instead.
 */
class AnswerComposer
{
    private const MONTHS = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

    /** @var list<string> stems of the words in the question (not synonyms) */
    private array $originalTerms = [];

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  list<array{chunk: \App\Models\KnowledgeChunk, score: float, relevance: float, coverage: float}>  $hits
     * @param  list<array{role: string, content: string}>  $history  earlier turns, for follow-up questions
     * @return array{answer: string, sources: list<array<string, mixed>>, confidence: float, suggestions: list<string>, found: bool, method: string, question_type: string}
     */
    public function compose(string $question, array $hits, array $queryWeights, array $originalTerms = [], array $history = []): array
    {
        $this->originalTerms = $originalTerms;
        $type = $this->questionType($question);
        $threshold = (float) config('acadealert.assistant.min_relevance', 0.18);
        $hits = array_values(array_filter($hits, fn ($h) => $h['relevance'] >= $threshold * 0.6));

        if ($hits === [] || $hits[0]['relevance'] < $threshold) {
            $nearby = array_slice($hits, 0, 2);
            $sources = array_map(fn ($h, $i) => $this->source($h, $i + 1, null), $nearby, array_keys($nearby));

            // Nothing in the student's own documents. Answer like a normal AI
            // assistant rather than refusing, and label where it came from.
            if ($general = $this->general($question, $history)) {
                return [
                    'answer' => $general,
                    'sources' => $sources,
                    'confidence' => round($hits[0]['relevance'] ?? 0.0, 3),
                    'suggestions' => $this->genericSuggestions(),
                    'found' => false,
                    'method' => 'llm-general',
                    'question_type' => $type,
                ];
            }

            return [
                'answer' => "I couldn't find this in your academic documents. Try rephrasing the question, "
                    .'or add the relevant handbook, project guideline or regulation to the knowledge base so I can answer it with a source.',
                'sources' => $sources,
                'confidence' => round($hits[0]['relevance'] ?? 0.0, 3),
                'suggestions' => $this->genericSuggestions(),
                'found' => false,
                'method' => 'none',
                'question_type' => $type,
            ];
        }

        $top = array_slice($hits, 0, 4);
        $candidates = $this->scoreSentences($top, $queryWeights, $type);
        $selected = $this->select($candidates);

        // Number sources in order of first use, then append supporting passages.
        $sourceIndex = [];
        $parts = [];
        foreach ($selected as $sentence) {
            $hitKey = $sentence['hit'];
            $sourceIndex[$hitKey] ??= count($sourceIndex) + 1;
            $parts[] = rtrim($sentence['text']).' ['.$sourceIndex[$hitKey].']';
        }
        $answer = $this->tidy(implode(' ', $parts));

        $sources = [];
        foreach ($sourceIndex as $hitKey => $n) {
            $used = array_values(array_filter($selected, fn ($s) => $s['hit'] === $hitKey));
            $sources[] = $this->source($top[$hitKey], $n, $used[0]['text'] ?? null, cited: true);
        }
        foreach ($top as $hitKey => $hit) {
            if (! isset($sourceIndex[$hitKey]) && count($sources) < 4) {
                $sources[] = $this->source($hit, count($sources) + 1, null, cited: false);
            }
        }

        // How much do the retrieved passages really look like the answer?
        // A strong match must be answered from the documents, with citations.
        // A weak one (an off-topic question that still brushed a document)
        // lets the model answer from its own knowledge instead of forcing an
        // irrelevant quote, which is what makes the assistant usable for
        // anything the student asks.
        $method = 'extractive';
        $strong = $hits[0]['relevance'] >= (float) config('acadealert.assistant.grounded_relevance', 0.45);

        if ($this->llm->enabled()) {
            if ($strong) {
                if ($rewritten = $this->rewrite($question, $sources, $history)) {
                    $answer = $rewritten;
                    $method = 'llm';
                }
            } elseif ($mixed = $this->groundedOrGeneral($question, $sources, $history)) {
                $answer = $mixed['answer'];
                $method = $mixed['method'];
            }
        }

        $bestSentence = $selected[0]['score'] ?? 0.0;
        $confidence = round(min(0.99, 0.6 * $hits[0]['relevance'] + 0.4 * min(1.0, $bestSentence)), 3);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'confidence' => $confidence,
            'suggestions' => $this->suggestions($question, $top),
            'found' => true,
            'method' => $method,
            'question_type' => $type,
        ];
    }

    public function questionType(string $question): string
    {
        $q = mb_strtolower(trim($question));

        return match (true) {
            (bool) preg_match('/^(when|what date|what time|by when|which date|what day)\b|\b(deadline|due date)\b/u', $q) => 'when',
            (bool) preg_match('/^(how (many|much|long|often)|what (percentage|percent|is the (minimum|maximum)))\b/u', $q) => 'quantity',
            (bool) preg_match('/^(can|may|am i|is it|are we|do i|must i|should i|will i|could i)\b/u', $q) => 'policy',
            (bool) preg_match('/^(who|whom)\b/u', $q) => 'who',
            (bool) preg_match('/^(where)\b/u', $q) => 'where',
            (bool) preg_match('/^(how (do|to|can|should)|what (are the )?(steps|process|procedure))\b/u', $q) => 'procedure',
            (bool) preg_match('/^why\b/u', $q) => 'reason',
            (bool) preg_match('/^(what (is|are|does)|define|meaning of|explain)\b/u', $q) => 'definition',
            default => 'general',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<array{hit: int, position: int, text: string, score: float}>
     */
    private function scoreSentences(array $hits, array $queryWeights, string $type): array
    {
        $total = ($this->originalTerms
            ? array_sum(array_intersect_key($queryWeights, array_flip($this->originalTerms)))
            : array_sum(array_filter($queryWeights, fn ($w) => $w >= 1.0))) ?: 1.0;
        $out = [];
        foreach ($hits as $hitKey => $hit) {
            $sentences = Text::sentences(str_replace(["\n• ", "\n"], ['. ', ' '], $hit['chunk']->content));
            foreach ($sentences as $position => $sentence) {
                $words = Text::wordCount($sentence);
                if ($words < 4) {
                    continue;
                }
                $terms = array_unique(Text::terms($sentence));
                $overlap = 0.0;
                foreach ($terms as $term) {
                    $overlap += $queryWeights[$term] ?? 0.0;
                }
                $coverage = min(1.0, $overlap / $total);
                $score = $coverage * 0.7 + 0.3 * $hit['relevance'];
                $score += $this->typeBonus($type, $sentence);
                if ($words < 7) {
                    $score -= 0.1;
                }
                if ($words > 55) {
                    $score -= 0.1;
                }
                $out[] = ['hit' => $hitKey, 'position' => $position, 'text' => $sentence, 'score' => round($score, 4), 'coverage' => $coverage];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    private function typeBonus(string $type, string $sentence): float
    {
        $s = mb_strtolower($sentence);
        $date = '/\b\d{1,2}(st|nd|rd|th)?\s+('.self::MONTHS.')\b|\b('.self::MONTHS.')\s+\d{1,2}\b|\b\d{4}-\d{2}-\d{2}\b|\bweek\s+\d+\b|\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b/u';

        return match ($type) {
            'when' => preg_match($date, $s) ? 0.3 : (preg_match('/\b(before|after|within|by|until|days?|weeks?)\b/u', $s) ? 0.1 : 0.0),
            'quantity' => preg_match('/\d|\b(one|two|three|four|five|six|seven|eight|nine|ten|twenty|fifty|hundred)\b/u', $s) ? 0.25 : 0.0,
            'policy' => preg_match('/\b(must|may|can|cannot|not permitted|allowed|required|eligible|only if|will be|shall)\b/u', $s) ? 0.2 : 0.0,
            'definition' => preg_match('/\b(is|are|refers to|means|defined as)\b/u', $s) ? 0.12 : 0.0,
            'procedure' => preg_match('/\b(submit|complete|apply|contact|fill|request|first|then|must|steps?|form)\b/u', $s) ? 0.15 : 0.0,
            'reason' => preg_match('/\b(because|due to|in order to|so that|to ensure|therefore)\b/u', $s) ? 0.2 : 0.0,
            'who' => preg_match('/\b(supervisor|lecturer|coordinator|office|committee|board|tutor|registrar|head)\b/u', $s) ? 0.2 : 0.0,
            default => 0.0,
        };
    }

    /**
     * Best sentence, plus up to two more that add information — preferring
     * the next sentence of the same passage for continuity.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function select(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }
        $best = $candidates[0];
        $selected = [$best];

        foreach ($candidates as $candidate) {
            if (count($selected) >= 3) {
                break;
            }
            if ($candidate === $best || $candidate['score'] < max(0.32, $best['score'] * 0.62)) {
                continue;
            }
            // Extra sentences must be about the question itself, not merely
            // the right "shape" (another date, another number…).
            if ($candidate['coverage'] < max(0.25, $best['coverage'] * 0.5)) {
                continue;
            }
            $redundant = false;
            foreach ($selected as $chosen) {
                if (Text::cosine(array_count_values(Text::terms($candidate['text'])), array_count_values(Text::terms($chosen['text']))) > 0.55) {
                    $redundant = true;
                    break;
                }
            }
            $adjacent = $candidate['hit'] === $best['hit'] && abs($candidate['position'] - $best['position']) === 1;
            if (! $redundant && ($adjacent || $candidate['score'] >= $best['score'] * 0.75)) {
                $selected[] = $candidate;
            }
        }
        // Lead with the direct answer, then supporting sentences in reading order.
        $rest = array_slice($selected, 1);
        usort($rest, fn ($a, $b) => [$a['hit'], $a['position']] <=> [$b['hit'], $b['position']]);

        return [$best, ...$rest];
    }

    /** @param  array<string, mixed>  $hit */
    private function source(array $hit, int $n, ?string $quote, bool $cited = false): array
    {
        $chunk = $hit['chunk'];
        $document = $chunk->document;

        return [
            'n' => $n,
            'cited' => $cited,
            'knowledge_document_id' => $chunk->knowledge_document_id,
            'chunk_id' => $chunk->id,
            'document' => $document?->title,
            'category' => $document?->category?->value,
            'category_label' => $document?->category?->label(),
            'section' => $chunk->section,
            'page' => $chunk->page_number,
            'quote' => $quote,
            'excerpt' => Text::excerpt($chunk->content, 420),
            'relevance' => $hit['relevance'],
            'semantic' => $hit['semantic'] ?? null,
        ];
    }

    /** @param  list<array<string, mixed>>  $hits */
    private function suggestions(string $question, array $hits): array
    {
        $asked = mb_strtolower($question);
        $suggestions = [];
        foreach ($hits as $hit) {
            $section = $hit['chunk']->section;
            if ($section && ! str_contains($asked, mb_strtolower($section))) {
                $suggestions[] = 'Tell me more about “'.$section.'”';
            }
            foreach ((array) ($hit['chunk']->keywords ?? []) as $keyword) {
                if (count($suggestions) >= 4) {
                    break 2;
                }
                if (! str_contains($asked, mb_strtolower($keyword)) && mb_strlen($keyword) > 4) {
                    $suggestions[] = 'What do the documents say about '.$keyword.'?';
                    break;
                }
            }
        }

        return array_slice(array_values(array_unique($suggestions)), 0, 3);
    }

    /** @return list<string> */
    private function genericSuggestions(): array
    {
        return [
            'What is the penalty for late submission?',
            'When is the project proposal due?',
            'What is the minimum attendance requirement?',
        ];
    }

    private function tidy(string $answer): string
    {
        $answer = preg_replace('/^•\s*/u', '', $answer) ?? $answer;
        $answer = preg_replace('/\s+/u', ' ', $answer) ?? $answer;

        return mb_strtoupper(mb_substr($answer, 0, 1)).mb_substr($answer, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @param  list<array{role: string, content: string}>  $history
     */
    private function rewrite(string $question, array $sources, array $history = []): ?string
    {
        $context = $this->context($sources);

        $answer = $this->llm->complete([
            ['role' => 'system', 'content' => 'You are an academic assistant for university students. Answer ONLY from the numbered sources. Cite every claim with its source number in square brackets, e.g. [1]. If the sources do not contain the answer, say so. Be concise (max 120 words).'],
            ...$this->historyMessages($history),
            ['role' => 'user', 'content' => "Sources:\n{$context}\n\nQuestion: {$question}"],
        ], 350);

        return $answer && preg_match('/\[\d+\]/', $answer) ? $answer : null;
    }

    /**
     * Weak retrieval: hand the model the passages but let it decide. If they
     * answer the question it cites them; if they do not, it answers from its
     * own knowledge and the answer is labelled as such.
     *
     * @param  list<array<string, mixed>>  $sources
     * @param  list<array{role: string, content: string}>  $history
     * @return array{answer: string, method: string}|null
     */
    private function groundedOrGeneral(string $question, array $sources, array $history = []): ?array
    {
        if (! $this->llm->generalAnswersEnabled()) {
            // General answers switched off: stay strict about citations.
            return ($cited = $this->rewrite($question, $sources, $history))
                ? ['answer' => $cited, 'method' => 'llm']
                : null;
        }

        $context = $this->context($sources);
        $answer = $this->llm->complete([
            ['role' => 'system', 'content' => implode(' ', [
                "You are AcadeAlert's Academic Assistant, helping a university student.",
                'Numbered extracts from the student\'s own academic documents are provided, but they may be irrelevant.',
                'If an extract answers the question, use it and cite it as [1], [2] and so on.',
                'If the extracts are not relevant, ignore them completely and answer from your own knowledge, with no citations at all.',
                'Never invent a citation, page number, deadline, mark or university regulation.',
                'Answer in clear British English, under 220 words unless more detail is needed.',
            ])],
            ...$this->historyMessages($history),
            ['role' => 'user', 'content' => "Possibly related extracts:\n{$context}\n\nQuestion: {$question}"],
        ], 700, 0.35);

        if (! $answer) {
            return null;
        }

        return preg_match('/\[\d+\]/', $answer)
            ? ['answer' => $answer, 'method' => 'llm']
            : ['answer' => $answer."\n\n_Answered from general knowledge — not from your uploaded documents._", 'method' => 'llm-general'];
    }

    /**
     * Answer a question the knowledge base does not cover, from the model's
     * own knowledge — the "normal AI assistant" path. Returns null when no
     * model is configured or the call fails, so the caller falls back to the
     * local engine's wording.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    private function general(string $question, array $history = []): ?string
    {
        if (! $this->llm->generalAnswersEnabled()) {
            return null;
        }

        $system = implode(' ', [
            "You are AcadeAlert's Academic Assistant, helping a university student.",
            'Answer the question directly and helpfully in clear British English.',
            'You may answer anything: study topics, programming, maths, academic writing, exam technique or everyday questions.',
            'Explain step by step when that helps, and give short worked examples or code where it is useful.',
            "This answer comes from your own knowledge, not from the student's uploaded documents, so never invent citations,",
            'page numbers, deadlines, marks or university regulations. If the question is about their own course rules or dates,',
            'answer in general terms and tell them to check their handbook or add it to the knowledge base.',
            'Keep it under 220 words unless more detail is genuinely needed.',
        ]);

        $answer = $this->llm->complete([
            ['role' => 'system', 'content' => $system],
            ...$this->historyMessages($history),
            ['role' => 'user', 'content' => $question],
        ], 700, 0.4);

        if (! $answer) {
            return null;
        }

        return $answer."\n\n_Answered from general knowledge — not from your uploaded documents._";
    }

    /**
     * The numbered extracts given to the model.
     *
     * @param  list<array<string, mixed>>  $sources
     */
    private function context(array $sources): string
    {
        return implode("\n\n", array_map(
            fn ($s) => "[{$s['n']}] {$s['document']} — ".($s['section'] ?? 'General').($s['page'] ? " (p. {$s['page']})" : '')."\n{$s['excerpt']}",
            $sources,
        ));
    }

    /**
     * The last few turns, so follow-ups like "explain that more simply" work.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @return list<array{role: string, content: string}>
     */
    private function historyMessages(array $history): array
    {
        $out = [];

        foreach (array_slice($history, -6) as $turn) {
            $content = preg_replace('/_Answered from general knowledge[^_]*_/u', '', (string) ($turn['content'] ?? '')) ?? '';
            $content = trim($content);

            if ($content === '') {
                continue;
            }

            $out[] = [
                'role' => ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => Text::excerpt($content, 1200),
            ];
        }

        return $out;
    }
}
