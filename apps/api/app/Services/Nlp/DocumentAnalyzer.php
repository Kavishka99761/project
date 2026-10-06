<?php

namespace App\Services\Nlp;

/**
 * Builds an Analysis: splits a document into blocks and sentences, extracts
 * key phrases and ranks every sentence with TextRank.
 *
 * TextRank: sentences are nodes; edges are cosine similarities between their
 * TF-IDF vectors; PageRank over that graph finds the sentences most central
 * to the document. Scores are then adjusted for key-phrase coverage,
 * position (opening sentences, sentences right after a heading) and length.
 */
final class DocumentAnalyzer
{
    private const MAX_GRAPH_SENTENCES = 650;

    private const DAMPING = 0.85;

    private const MIN_EDGE = 0.05;

    public function __construct(
        private readonly KeywordExtractor $keywords = new KeywordExtractor,
        private readonly ConceptExtractor $concepts = new ConceptExtractor,
    ) {}

    /**
     * @param  array<string, int>  $documentFrequencies  corpus DF for keyword IDF
     */
    public function analyze(string $text, ?string $title = null, array $documentFrequencies = [], int $corpusSize = 0): Analysis
    {
        $text = Text::normalize($text);
        $blocks = Text::blocks($text);

        $sentences = [];
        $headings = [];
        $section = null;
        $afterHeading = false;

        foreach ($blocks as $blockIndex => $block) {
            if ($block['type'] === 'heading') {
                $section = $block['text'];
                $headings[] = $block['text'];
                $afterHeading = true;

                continue;
            }
            $isBullet = $block['type'] === 'bullet';
            $parts = $isBullet ? [$block['text']] : Text::sentences($block['text']);
            foreach ($parts as $position => $sentence) {
                $words = Text::wordCount($sentence);
                $sentences[] = [
                    'index' => count($sentences),
                    'text' => $sentence,
                    'section' => $section,
                    'block' => $blockIndex,
                    'position' => $position,
                    'after_heading' => $afterHeading && $position === 0,
                    'words' => $words,
                    'bullet' => $isBullet,
                    'terms' => Text::terms($sentence),
                    'kterms' => Text::keyTerms($sentence),
                    'score' => 0.0,
                    'candidate' => $this->isSummaryCandidate($sentence, $words),
                ];
            }
            $afterHeading = false;
        }

        $keywords = $this->keywords->extract(
            array_column($sentences, 'text'),
            (int) config('edusmart.nlp.keywords', 12),
            $headings,
            $title,
            $documentFrequencies,
            $corpusSize,
        );

        $sentences = $this->score($sentences, $keywords, $title);

        return new Analysis(
            title: $title,
            blocks: $blocks,
            sentences: array_map(function ($s) {
                unset($s['position'], $s['after_heading']);

                return $s;
            }, $sentences),
            keywords: $keywords,
            headings: array_values(array_unique($headings)),
            wordCount: Text::wordCount($text),
        );
    }

    private function isSummaryCandidate(string $sentence, int $words): bool
    {
        if ($words < 5 || $words > 75 || str_ends_with($sentence, '?')) {
            return false;
        }
        if (preg_match('/^(figure|fig\.|table|source|copyright|page \d)/iu', $sentence)) {
            return false;
        }
        $letters = preg_match_all('/\p{L}/u', $sentence);

        return $letters >= mb_strlen($sentence) * 0.55;
    }

    /**
     * @param  list<array<string, mixed>>  $sentences
     * @param  list<array<string, mixed>>  $keywords
     * @return list<array<string, mixed>>
     */
    private function score(array $sentences, array $keywords, ?string $title): array
    {
        $n = count($sentences);
        if ($n === 0) {
            return [];
        }
        $titleTerms = $title ? array_unique(Text::terms($title)) : [];

        // Key-phrase coverage per sentence.
        $coverage = [];
        foreach ($sentences as $i => $sentence) {
            $terms = ' '.implode(' ', $sentence['kterms']).' ';
            $sum = 0.0;
            foreach ($keywords as $keyword) {
                if (str_contains($terms, ' '.$keyword['key'].' ')) {
                    $sum += $keyword['score'];
                }
            }
            $coverage[$i] = $sum;
        }
        $maxCoverage = max($coverage) ?: 1.0;

        // Limit the graph on very long documents to the most keyword-rich sentences.
        $graphNodes = array_keys($sentences);
        if ($n > self::MAX_GRAPH_SENTENCES) {
            $ranked = $coverage;
            arsort($ranked);
            $graphNodes = array_slice(array_keys($ranked), 0, self::MAX_GRAPH_SENTENCES);
            sort($graphNodes);
        }

        $rank = $this->textRank($sentences, $graphNodes);

        // Final score = weighted blend of three signals, then a length factor:
        //   centrality (TextRank)  — how many other sentences echo this one
        //   coverage               — how many of the document's key phrases it carries
        //   structure prior        — lead position, relevance to the title, definitions
        $maxRank = max($rank ?: [0]) ?: 1.0;
        foreach ($sentences as $i => &$sentence) {
            $centrality = isset($rank[$i]) ? $rank[$i] / $maxRank : 0.1;

            $lead = match (true) {
                $i === 0 => 1.0,
                $sentence['after_heading'] => 0.55,
                $sentence['position'] === 0 => 0.3,
                default => 0.0,
            };
            $titleOverlap = $titleTerms !== []
                ? count(array_intersect($titleTerms, $sentence['terms'])) / count($titleTerms)
                : 0.0;
            $definitionScore = 0.0;
            if ($sentence['candidate'] && ($definition = $this->concepts->definition($sentence['text']))) {
                $definitionScore = $titleTerms !== [] && array_intersect($titleTerms, Text::terms($definition['term'])) !== []
                    ? 1.0
                    : 0.6;
            }
            $prior = 0.4 * $lead + 0.3 * $titleOverlap + 0.3 * $definitionScore;

            $length = match (true) {
                $sentence['words'] < 6 => 0.55,
                $sentence['words'] < 10 => 0.85,
                $sentence['words'] <= 35 => 1.0,
                $sentence['words'] <= 50 => 0.85,
                default => 0.65,
            };

            $sentence['score'] = (0.5 * $centrality + 0.25 * ($coverage[$i] / $maxCoverage) + 0.25 * $prior) * $length;
            $sentence['defines_title'] = $definitionScore === 1.0;
        }
        unset($sentence);

        $max = max(array_column($sentences, 'score')) ?: 1.0;
        foreach ($sentences as &$sentence) {
            $sentence['score'] = round($sentence['score'] / $max, 4);
        }
        unset($sentence);

        return $sentences;
    }

    /**
     * Weighted PageRank over the sentence-similarity graph.
     *
     * @param  list<array<string, mixed>>  $sentences
     * @param  list<int>  $nodes
     * @return array<int, float>
     */
    private function textRank(array $sentences, array $nodes): array
    {
        $count = count($nodes);
        if ($count === 1) {
            return [$nodes[0] => 1.0];
        }

        // TF-IDF vectors with sentence-level IDF.
        $df = [];
        foreach ($nodes as $i) {
            foreach (array_unique($sentences[$i]['terms']) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        $vectors = [];
        $postings = [];
        foreach ($nodes as $i) {
            $vector = [];
            foreach (array_count_values($sentences[$i]['terms']) as $term => $tf) {
                $vector[$term] = (1 + log($tf)) * (log($count / $df[$term]) + 1);
                $postings[$term][] = $i;
            }
            $vectors[$i] = $vector;
        }

        // Sparse similarity graph: only pairs sharing at least one term.
        $edges = [];
        foreach ($nodes as $i) {
            $neighbours = [];
            foreach (array_keys($vectors[$i]) as $term) {
                foreach ($postings[$term] as $j) {
                    if ($j > $i) {
                        $neighbours[$j] = true;
                    }
                }
            }
            foreach (array_keys($neighbours) as $j) {
                $similarity = Text::cosine($vectors[$i], $vectors[$j]);
                if ($similarity >= self::MIN_EDGE) {
                    $edges[$i][$j] = $similarity;
                    $edges[$j][$i] = $similarity;
                }
            }
        }

        $outWeight = [];
        foreach ($nodes as $i) {
            $outWeight[$i] = array_sum($edges[$i] ?? []);
        }

        $scores = array_fill_keys($nodes, 1.0 / $count);
        for ($iteration = 0; $iteration < 40; $iteration++) {
            $next = [];
            $delta = 0.0;
            foreach ($nodes as $i) {
                $sum = 0.0;
                foreach ($edges[$i] ?? [] as $j => $weight) {
                    if ($outWeight[$j] > 0) {
                        $sum += $weight / $outWeight[$j] * $scores[$j];
                    }
                }
                $next[$i] = (1 - self::DAMPING) / $count + self::DAMPING * $sum;
                $delta += abs($next[$i] - $scores[$i]);
            }
            $scores = $next;
            if ($delta < 1e-5) {
                break;
            }
        }

        return $scores;
    }
}
