<?php

namespace App\Services\Assistant;

use App\Enums\KnowledgeStatus;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\TextExtractor;
use App\Services\Nlp\Text;
use App\Services\Platform\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * KAVISHKA — academic document knowledge base: upload → extract → index →
 * date extraction, plus re-processing on demand.
 */
class KnowledgeService
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly TextExtractor $extractor,
        private readonly KnowledgeIndexer $indexer,
        private readonly AcademicDateService $dates,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{category: string, title?: ?string, description?: ?string, module_id?: ?int}  $attributes
     */
    public function upload(User $user, UploadedFile $file, array $attributes): KnowledgeDocument
    {
        $stored = $this->storage->store($file, 'knowledge', $user->id);

        $document = $user->knowledgeDocuments()->create([
            'module_id' => $attributes['module_id'] ?? null,
            'category' => $attributes['category'],
            'title' => $attributes['title'] ?: mb_substr(ucfirst(trim(preg_replace('/[_\-]+/u', ' ', pathinfo($stored['original_name'], PATHINFO_FILENAME)) ?? '')), 0, 200) ?: 'Academic document',
            'description' => $attributes['description'] ?? null,
            'original_name' => $stored['original_name'],
            'file_path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'extension' => $stored['extension'],
            'size_bytes' => $stored['size_bytes'],
            'checksum' => $stored['checksum'],
            'status' => KnowledgeStatus::Pending,
        ]);

        return $this->process($document);
    }

    /** Extract, chunk + index, and find academic dates. */
    public function process(KnowledgeDocument $document): KnowledgeDocument
    {
        $document->update(['status' => KnowledgeStatus::Processing, 'error' => null]);

        try {
            $extracted = $this->extractor->extract($this->storage->absolutePath((string) $document->file_path), (string) $document->extension);
            if ($extracted->isEmpty()) {
                throw new \RuntimeException($extracted->warnings[0] ?? 'No text could be extracted from this document.');
            }

            $document->forceFill([
                'content' => $extracted->text,
                'pages' => $extracted->pageCount,
                'word_count' => Text::wordCount($extracted->text),
                'processed_at' => now(),
            ])->save();

            $chunks = $this->indexer->index($document, $extracted);
            $document->forceFill([
                'chunk_count' => $chunks,
                'status' => KnowledgeStatus::Indexed,
                'indexed_at' => now(),
                'error' => $extracted->warnings ? mb_substr(implode(' ', $extracted->warnings), 0, 495) : null,
            ])->save();

            $found = $this->dates->extractFromDocument($document, $extracted->pages);

            $this->notifications->send(
                $document->user,
                ModuleKey::Assistant,
                'Indexed: '.$document->title,
                $found > 0
                    ? "{$chunks} passages are searchable and {$found} important dates were found — review them to add to your calendar."
                    : "{$chunks} passages are now searchable by the academic assistant.",
                NotificationType::Success,
                $found > 0 ? '/assistant/dates' : '/assistant/knowledge',
                'database-check',
            );
        } catch (\Throwable $e) {
            Log::warning('Knowledge processing failed', ['document' => $document->id, 'error' => $e->getMessage()]);
            $document->forceFill([
                'status' => KnowledgeStatus::Failed,
                'error' => mb_substr($e->getMessage(), 0, 495),
            ])->save();
        }

        return $document->refresh();
    }
}
