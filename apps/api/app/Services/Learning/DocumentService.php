<?php

namespace App\Services\Learning;

use App\Enums\DocumentKind;
use App\Enums\ExtractionStatus;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Enums\SummaryLength;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\TextExtractor;
use App\Services\Nlp\Text;
use App\Services\Platform\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * BETHMI — learning material lifecycle: upload, typed notes, text
 * extraction, automatic summarisation and re-processing.
 */
class DocumentService
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly TextExtractor $extractor,
        private readonly DocumentAnalysisService $analysis,
        private readonly SummaryService $summaries,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{title?: ?string, topic?: ?string, description?: ?string, module_id?: ?int}  $attributes
     */
    public function upload(User $user, UploadedFile $file, array $attributes = []): Document
    {
        $stored = $this->storage->store($file, 'documents', $user->id);

        $document = $user->documents()->create([
            'module_id' => $attributes['module_id'] ?? null,
            'title' => $attributes['title'] ?: $this->titleFromFilename($stored['original_name']),
            'topic' => $attributes['topic'] ?? null,
            'description' => $attributes['description'] ?? null,
            'kind' => $this->kindFor($stored['extension']),
            'original_name' => $stored['original_name'],
            'file_path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'extension' => $stored['extension'],
            'size_bytes' => $stored['size_bytes'],
            'checksum' => $stored['checksum'],
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $this->extract($document);
        $this->autoSummarize($user, $document);

        return $document->refresh();
    }

    /**
     * A lecture note typed or pasted directly into the app.
     *
     * @param  array{title: string, content: string, topic?: ?string, description?: ?string, module_id?: ?int}  $attributes
     */
    public function createNote(User $user, array $attributes): Document
    {
        $stored = $this->storage->storeText($attributes['content'], 'documents', $user->id, 'md');

        $document = $user->documents()->create([
            'module_id' => $attributes['module_id'] ?? null,
            'title' => $attributes['title'],
            'topic' => $attributes['topic'] ?? null,
            'description' => $attributes['description'] ?? null,
            'kind' => DocumentKind::Note,
            'original_name' => str($attributes['title'])->slug()->append('.md')->toString(),
            'file_path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'extension' => 'md',
            'size_bytes' => $stored['size_bytes'],
            'checksum' => $stored['checksum'],
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $this->extract($document);
        $this->autoSummarize($user, $document);

        return $document->refresh();
    }

    /** Update a typed note's text (re-extracts and refreshes keywords). */
    public function updateNoteContent(Document $document, string $content): Document
    {
        $this->storage->delete($document->file_path);
        $stored = $this->storage->storeText($content, 'documents', $document->user_id, 'md');
        $document->update([
            'file_path' => $stored['path'],
            'size_bytes' => $stored['size_bytes'],
            'checksum' => $stored['checksum'],
        ]);

        return $this->extract($document);
    }

    /** Extract (or re-extract) text and refresh derived statistics. */
    public function extract(Document $document): Document
    {
        try {
            $result = $this->extractor->extract($this->storage->absolutePath((string) $document->file_path), (string) $document->extension);
            $words = Text::wordCount($result->text);

            $document->forceFill([
                'content' => $result->text,
                'pages' => $result->pageCount,
                'word_count' => $words,
                'reading_minutes' => Text::readingMinutes($words),
                'extraction_status' => $result->isEmpty() ? ExtractionStatus::Empty : ExtractionStatus::Completed,
                'extraction_error' => $result->warnings ? mb_substr(implode(' ', $result->warnings), 0, 495) : null,
                'extracted_at' => now(),
            ])->save();

            if (! $result->isEmpty()) {
                $this->analysis->refreshKeywords($document);
            }
        } catch (\Throwable $e) {
            Log::warning('Text extraction failed', ['document' => $document->id, 'error' => $e->getMessage()]);
            $document->forceFill([
                'extraction_status' => ExtractionStatus::Failed,
                'extraction_error' => mb_substr($e->getMessage(), 0, 495),
                'extracted_at' => now(),
            ])->save();
        }

        return $document;
    }

    /** Summarise new material automatically when the student opted in. */
    private function autoSummarize(User $user, Document $document): void
    {
        $settings = $user->settingsOrDefault();
        if (! $settings->auto_summarize || $document->extraction_status !== ExtractionStatus::Completed || $document->word_count < 40) {
            return;
        }

        try {
            $length = SummaryLength::tryFrom($settings->default_summary_length) ?? SummaryLength::Medium;
            $summary = $this->summaries->generateAndSave($document, $length);

            $this->notifications->send(
                $user,
                ModuleKey::Learning,
                'Summary ready: '.$document->title,
                "A {$length->label()} summary with key concepts and revision notes was generated automatically.",
                NotificationType::Success,
                "/learning/summaries/{$summary->id}",
                'magic',
            );
        } catch (\Throwable $e) {
            Log::info('Auto-summary skipped: '.$e->getMessage());
        }
    }

    private function kindFor(string $extension): DocumentKind
    {
        return match ($extension) {
            'pdf' => DocumentKind::Pdf,
            'doc', 'docx' => DocumentKind::Word,
            'pptx' => DocumentKind::Slides,
            'md' => DocumentKind::Markdown,
            default => DocumentKind::Text,
        };
    }

    private function titleFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $title = trim(preg_replace('/[_\-]+/u', ' ', $base) ?? $base);

        return mb_substr($title !== '' ? ucfirst($title) : 'Untitled document', 0, 200);
    }
}
