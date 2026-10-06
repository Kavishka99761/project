<?php

namespace App\Services\Nlp;

/**
 * NotebookLM-style study aids generated from an analysed document:
 * flashcards (definitions + cloze cards), a multiple-choice quiz and a
 * concept mind map. Generation is deterministic — the same document always
 * yields the same cards, so saved aids stay meaningful.
 */
final class StudyAidGenerator
{
    public function __construct(private readonly ConceptExtractor $concepts = new ConceptExtractor) {}

    /**
     * @return list<array{front: string, back: string, kind: string, hint: ?string}>
     */
    public function flashcards(Analysis $analysis): array
    {
        $limit = (int) config('edusmart.nlp.max_flashcards', 16);
        $cards = [];
        $usedSentences = [];

        foreach ($this->concepts->extract($analysis, 10) as $concept) {
            if ($concept['source'] !== 'definition') {
                continue;
            }
            $cards[] = [
                'front' => 'What is meant by “'.$concept['term'].'”?',
                'back' => $concept['definition'],
                'kind' => 'definition',
                'hint' => null,
            ];
            $usedSentences[$concept['sentence']] = true;
        }

        foreach ($this->clozeCandidates($analysis) as $cloze) {
            if (count($cards) >= $limit) {
                break;
            }
            if (isset($usedSentences[$cloze['sentence']])) {
                continue;
            }
            $usedSentences[$cloze['sentence']] = true;
            $cards[] = [
                'front' => $cloze['question'],
                'back' => $cloze['answer'],
                'kind' => 'cloze',
                'hint' => $cloze['section'] ? 'From: '.$cloze['section'] : null,
            ];
        }

        return array_slice($cards, 0, $limit);
    }

    /**
     * @return list<array{question: string, options: list<string>, answer: int, explanation: string}>
     */
    public function quiz(Analysis $analysis): array
    {
        $limit = (int) config('edusmart.nlp.max_quiz_questions', 10);
        $pool = array_column($analysis->keywords, 'term');
        if (count($pool) < 4) {
            return [];
        }

        $questions = [];
        $usedAnswers = [];
        foreach ($this->clozeCandidates($analysis) as $cloze) {
            if (count($questions) >= $limit) {
                break;
            }
            // Vary the answers: at most two questions share the same one.
            $answerKey = mb_strtolower($cloze['answer']);
            if (($usedAnswers[$answerKey] ?? 0) >= 2) {
                continue;
            }
            $source = mb_strtolower($cloze['source']);
            $distractors = array_values(array_filter($pool, fn ($term) => mb_strtolower($term) !== $answerKey
                && ! str_contains($source, mb_strtolower($term))
                && ! str_contains($answerKey, mb_strtolower($term))
                && ! str_contains(mb_strtolower($term), $answerKey)));
            if (count($distractors) < 3) {
                continue;
            }
            // Prefer distractors of a similar shape (word count) to the answer.
            $answerWords = count(Text::words($cloze['answer']));
            usort($distractors, fn ($a, $b) => abs(count(Text::words($a)) - $answerWords) <=> abs(count(Text::words($b)) - $answerWords));
            $options = array_slice($distractors, 0, 3);
            $options[] = $cloze['answer'];

            // Deterministic shuffle seeded by the sentence.
            mt_srand(crc32($cloze['source']));
            shuffle($options);
            mt_srand();

            $usedAnswers[$answerKey] = ($usedAnswers[$answerKey] ?? 0) + 1;
            $questions[] = [
                'question' => $cloze['question'],
                'options' => array_map(fn ($o) => $this->capitalise($o), $options),
                'answer' => (int) array_search($cloze['answer'], $options, true),
                'explanation' => $cloze['source'],
            ];
        }

        return $questions;
    }

    /**
     * Concept map: the document at the centre, key concepts around it and the
     * terms each concept co-occurs with most strongly on the outer ring.
     *
     * @return array{id: string, label: string, children: list<array<string, mixed>>}
     */
    public function mindmap(Analysis $analysis, string $title): array
    {
        // Short concept names read best on a map; long definitional ones don't.
        $concepts = array_values(array_filter(
            $this->concepts->extract($analysis, 10),
            fn ($c) => count(Text::words($c['term'])) <= 4,
        ));
        $concepts = array_slice($concepts, 0, 6);

        $children = [];
        foreach ($concepts as $i => $concept) {
            $conceptKey = $this->concepts->key($concept['term']);
            $related = [];
            foreach ($analysis->sentences as $sentence) {
                $terms = ' '.implode(' ', $sentence['kterms']).' ';
                if (! str_contains($terms, ' '.$conceptKey.' ')) {
                    continue;
                }
                foreach ($analysis->keywords as $keyword) {
                    $key = $keyword['key'];
                    if (! str_contains(" $conceptKey ", " $key ") && ! str_contains(" $key ", " $conceptKey ")
                        && str_contains($terms, ' '.$key.' ')) {
                        $related[$keyword['term']] = ($related[$keyword['term']] ?? 0) + $keyword['score'];
                    }
                }
            }
            arsort($related);

            $leaves = [];
            foreach (array_slice(array_keys($related), 0, 3) as $j => $term) {
                $leaves[] = ['id' => "c{$i}-{$j}", 'label' => $this->capitalise($term), 'children' => []];
            }

            $children[] = [
                'id' => 'c'.$i,
                'label' => $concept['term'],
                'detail' => Text::excerpt($concept['definition'], 180),
                'weight' => $concept['importance'],
                'children' => $leaves,
            ];
        }

        return ['id' => 'root', 'label' => $title, 'children' => $children];
    }

    /**
     * Sentences with a key phrase blanked out — the basis for cloze cards and
     * quiz questions. A phrase that appears twice in the sentence is skipped
     * so the blank never gives its own answer away.
     *
     * @return list<array{question: string, answer: string, source: string, sentence: int, section: ?string}>
     */
    private function clozeCandidates(Analysis $analysis): array
    {
        $out = [];
        foreach ($analysis->rankedCandidates() as $sentence) {
            if ($sentence['words'] < 8 || $sentence['words'] > 40) {
                continue;
            }
            $found = $analysis->keywordsIn($sentence);
            usort($found, fn ($a, $b) => (count(Text::words($b['term'])) <=> count(Text::words($a['term']))) ?: ($b['score'] <=> $a['score']));

            foreach ($found as $keyword) {
                $pattern = '/\b'.preg_quote($keyword['term'], '/').'\b/iu';
                $occurrences = preg_match_all($pattern, $sentence['text'], $m, PREG_OFFSET_CAPTURE);
                if ($occurrences !== 1 || $m[0][0][1] === 0) {
                    continue;
                }
                $answer = $m[0][0][0];
                // The blank must not be guessable from another inflection nearby.
                $rest = mb_strtolower(preg_replace($pattern, ' ', $sentence['text'], 1) ?? '');
                if (str_contains($rest, mb_strtolower(Text::lightStem($answer)))) {
                    continue;
                }
                $out[] = [
                    'question' => rtrim((string) preg_replace($pattern, '_____', $sentence['text'], 1)),
                    'answer' => mb_strtolower($answer) === $answer ? $keyword['term'] : $answer,
                    'source' => $sentence['text'],
                    'sentence' => $sentence['index'],
                    'section' => $sentence['section'],
                ];
                break;
            }
        }

        return $out;
    }

    private function capitalise(string $term): string
    {
        return mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);
    }
}
