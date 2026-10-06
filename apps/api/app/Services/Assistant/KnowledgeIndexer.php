<?php

namespace App\Services\Assistant;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Documents\ExtractedText;
use App\Services\Nlp\KeywordExtractor;
use App\Services\Nlp\Text;
use Illuminate\Support\Facades\DB;

/**
 * KAVISHKA — turns an extracted academic document into indexed passages.
 *
 * Passages never cross a page boundary (so every answer can cite an exact
 * page) and remember the most recent heading (so it can cite a section).
 * Each passage stores a stemmed term-frequency vector for BM25 retrieval;
 * the section heading is indexed twice, as headings are strong signals.
 */
class KnowledgeIndexer
{
    public function __construct(private readonly KeywordExtractor $keywords) {}

    public function index(KnowledgeDocument $document, ExtractedText $extracted): int
    {
        $chunks = $this->chunk($extracted->pages);

        DB::transaction(function () use ($document, $chunks) {
            $document->chunks()->delete();
            $now = now();
            $rows = [];
            foreach ($chunks as $i => $chunk) {
                $terms = Text::termFrequencies(trim(($chunk['section'] ?? '').' '.($chunk['section'] ?? '').' '.$chunk['content']));
                $rows[] = [
                    'knowledge_document_id' => $document->id,
                    'user_id' => $document->user_id,
                    'chunk_index' => $i,
                    'section' => $chunk['section'] ? mb_substr($chunk['section'], 0, 250) : null,
                    'page_number' => $chunk['page'],
                    'content' => $chunk['content'],
                    'token_count' => array_sum($terms),
                    'terms' => json_encode($terms),
                    'keywords' => json_encode(array_column(
                        $this->keywords->extract(Text::sentences($chunk['content']), 6),
                        'term',
                    )),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            // SQL Server allows max 2100 bound parameters per statement.
            foreach (array_chunk($rows, 100) as $batch) {
                KnowledgeChunk::insert($batch);
            }
        });

        return count($chunks);
    }

    /**
     * @param  list<string>  $pages
     * @return list<array{section: ?string, page: int, content: string}>
     */
    public function chunk(array $pages): array
    {
        $min = (int) config('edusmart.assistant.chunk_min_words', 60);
        $max = (int) config('edusmart.assistant.chunk_max_words', 200);

        $chunks = [];
        $section = null;

        foreach ($pages as $pageIndex => $pageText) {
            $page = $pageIndex + 1;
            $buffer = [];
            $words = 0;
            $bufferSection = $section;

            $flush = function () use (&$buffer, &$words, &$chunks, &$bufferSection, $page) {
                $content = trim(implode("\n", $buffer));
                if ($content !== '' && Text::wordCount($content) >= 6) {
                    $chunks[] = ['section' => $bufferSection, 'page' => $page, 'content' => $content];
                }
                $buffer = [];
                $words = 0;
            };

            foreach (Text::blocks(Text::normalize($pageText)) as $block) {
                if ($block['type'] === 'heading') {
                    if ($words >= $min / 2) {
                        $flush();
                    }
                    $section = $block['text'];
                    if ($words === 0) {
                        $bufferSection = $section;
                    }

                    continue;
                }

                $text = $block['type'] === 'bullet' ? '• '.$block['text'] : $block['text'];
                $count = Text::wordCount($text);

                if ($count > $max) {
                    // Long paragraph: split on sentence boundaries.
                    $flush();
                    $bufferSection = $section;
                    foreach (Text::sentences($text) as $sentence) {
                        $sentenceWords = Text::wordCount($sentence);
                        if ($words + $sentenceWords > $max && $words >= $min) {
                            $flush();
                            $bufferSection = $section;
                        }
                        $buffer[] = $sentence;
                        $words += $sentenceWords;
                    }

                    continue;
                }

                if ($words + $count > $max && $words >= $min) {
                    $flush();
                }
                if ($words === 0) {
                    $bufferSection = $section;
                }
                $buffer[] = $text;
                $words += $count;
            }
            $flush();
        }

        return $chunks;
    }
}
