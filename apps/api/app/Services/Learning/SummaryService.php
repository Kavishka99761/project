<?php

namespace App\Services\Learning;

use App\Enums\SummaryLength;
use App\Models\Document;
use App\Models\Summary;
use App\Services\Ai\LlmClient;
use App\Services\Nlp\ConceptExtractor;
use App\Services\Nlp\RevisionNotesBuilder;
use App\Services\Nlp\Summarizer;
use App\Services\Nlp\Text;

/**
 * BETHMI — summary generation (short / medium / detailed) with keywords, key
 * concepts, highlighted sentences and bullet-point revision notes, plus
 * persistence of the summaries a student chooses to save.
 */
class SummaryService
{
    public function __construct(
        private readonly DocumentAnalysisService $analysis,
        private readonly Summarizer $summarizer,
        private readonly ConceptExtractor $concepts,
        private readonly RevisionNotesBuilder $notes,
        private readonly LlmClient $llm,
    ) {}

    /**
     * Generate (without saving) a complete summary package for a document.
     *
     * @return array<string, mixed>
     */
    public function generate(Document $document, SummaryLength $length): array
    {
        $analysis = $this->analysis->analyze($document);
        if ($analysis->isEmpty()) {
            throw new \DomainException('This document has no extracted text to summarise yet.');
        }

        $summary = $this->summarizer->summarize($analysis, $length);
        $method = 'textrank';

        if ($this->llm->enabled() && ($rewritten = $this->rewriteWithLlm($document->title, $summary['content'], $length))) {
            $summary['content'] = $rewritten;
            $summary['word_count'] = Text::wordCount($rewritten);
            $method = 'llm';
        }

        return [
            'title' => $this->defaultTitle($document, $length),
            'length' => $length->value,
            'method' => $method,
            'content' => $summary['content'],
            'bullet_points' => $this->notes->build($analysis),
            'keywords' => array_map(fn ($k) => ['term' => $k['term'], 'score' => $k['score'], 'count' => $k['count']], $analysis->keywords),
            'key_concepts' => array_map(fn ($c) => [
                'term' => $c['term'],
                'definition' => $c['definition'],
                'importance' => $c['importance'],
                'source' => $c['source'],
            ], $this->concepts->extract($analysis)),
            'highlights' => $this->analysis->importantSentences($analysis),
            'sentence_count' => $summary['sentence_count'],
            'word_count' => $summary['word_count'],
            'source_word_count' => $analysis->wordCount,
            'compression' => $analysis->wordCount > 0 ? round($summary['word_count'] / $analysis->wordCount * 100) : 0,
            'reading_minutes' => Text::readingMinutes($summary['word_count']),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function save(Document $document, array $payload, ?string $title = null): Summary
    {
        return Summary::forceCreate([
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'module_id' => $document->module_id,
            'title' => $title ?: $payload['title'],
            'length' => $payload['length'],
            'method' => $payload['method'],
            'content' => $payload['content'],
            'bullet_points' => $payload['bullet_points'],
            'keywords' => $payload['keywords'],
            'key_concepts' => $payload['key_concepts'],
            'highlights' => $payload['highlights'],
            'word_count' => $payload['word_count'],
            'source_word_count' => $payload['source_word_count'],
            'sentence_count' => $payload['sentence_count'],
        ]);
    }

    public function generateAndSave(Document $document, SummaryLength $length, ?string $title = null): Summary
    {
        return $this->save($document, $this->generate($document, $length), $title);
    }

    /** Plain-text / Markdown rendering used by downloads. */
    public function toMarkdown(Summary $summary): string
    {
        $lines = ['# '.$summary->title, ''];
        $meta = array_filter([
            $summary->document?->title ? 'Source: '.$summary->document->title : null,
            'Length: '.$summary->length->label(),
            'Created: '.$summary->created_at?->format('d M Y, H:i'),
        ]);
        $lines[] = '_'.implode(' · ', $meta).'_';
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = $summary->content;
        $lines[] = '';

        if ($summary->key_concepts) {
            $lines[] = '## Key concepts';
            $lines[] = '';
            foreach ($summary->key_concepts as $concept) {
                $lines[] = '- **'.$concept['term'].'** — '.$concept['definition'];
            }
            $lines[] = '';
        }
        if ($summary->bullet_points) {
            $lines[] = '## Revision notes';
            foreach ($summary->bullet_points as $group) {
                $lines[] = '';
                $lines[] = '### '.$group['heading'];
                foreach ($group['bullets'] as $bullet) {
                    $lines[] = '- '.$bullet;
                }
            }
            $lines[] = '';
        }
        if ($summary->keywords) {
            $lines[] = '## Keywords';
            $lines[] = '';
            $lines[] = implode(', ', array_column($summary->keywords, 'term'));
        }

        return implode("\n", $lines)."\n";
    }

    private function defaultTitle(Document $document, SummaryLength $length): string
    {
        return mb_substr($document->title, 0, 170).' — '.$length->label().' summary';
    }

    private function rewriteWithLlm(string $title, string $extractive, SummaryLength $length): ?string
    {
        $words = match ($length) {
            SummaryLength::Short => 'about 60 words',
            SummaryLength::Medium => 'about 130 words',
            SummaryLength::Detailed => 'about 260 words, keeping the "### Section" headings',
        };

        return $this->llm->complete([
            ['role' => 'system', 'content' => 'You rewrite extractive lecture summaries into clear, accurate study prose for university students. Use only facts present in the given text. Do not add new information.'],
            ['role' => 'user', 'content' => "Lecture: {$title}\nRewrite this extractive summary in {$words}:\n\n{$extractive}"],
        ]);
    }
}
