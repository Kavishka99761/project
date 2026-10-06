<?php

namespace App\Http\Controllers\Api\V1\Learning;

use App\Enums\DocumentKind;
use App\Enums\ModuleKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\CreateNoteRequest;
use App\Http\Requests\Learning\UpdateDocumentRequest;
use App\Http\Requests\Learning\UploadDocumentRequest;
use App\Http\Resources\Learning\DocumentResource;
use App\Models\Document;
use App\Services\Learning\DocumentAnalysisService;
use App\Services\Learning\DocumentService;
use App\Services\Nlp\ConceptExtractor;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BETHMI — Learning Material Management: upload lecture notes, PDF and Word
 * documents; extract text; view, rename, organise by module and topic,
 * search, filter, view details and delete.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentAnalysisService $analysis,
    ) {}

    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'module_id' => ['nullable', 'integer'],
            'topic' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', Rule::enum(DocumentKind::class)],
            'favorite' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['recent', 'oldest', 'title', 'size', 'opened'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = $request->user()->documents()->with('module')->withCount('summaries');

        if ($q = $filters['q'] ?? null) {
            $like = '%'.$search->escapeLike($q).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $like)
                ->orWhere('topic', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('original_name', 'like', $like)
                ->orWhere('content', 'like', $like));
        }
        if (array_key_exists('module_id', $filters) && $filters['module_id'] !== null) {
            $filters['module_id'] === 0 ? $query->whereNull('module_id') : $query->where('module_id', $filters['module_id']);
        }
        $query->when($filters['topic'] ?? null, fn ($q, $topic) => $q->where('topic', $topic))
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($request->boolean('favorite'), fn ($q) => $q->where('is_favorite', true));

        match ($filters['sort'] ?? 'recent') {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'size' => $query->orderByDesc('size_bytes'),
            'opened' => $query->orderByRaw('CASE WHEN last_opened_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('last_opened_at'),
            default => $query->latest(),
        };

        return DocumentResource::collection($query->paginate($filters['per_page'] ?? 24)->withQueryString());
    }

    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->upload($request->user(), $request->file('file'), $request->safe()->except('file'));
        activity()->module(ModuleKey::Learning)->action('documents.uploaded')
            ->describe(sprintf('Uploaded “%s” (%s, %d page%s, %s words)', $document->title, $document->kind->label(),
                $document->pages, $document->pages === 1 ? '' : 's', number_format($document->word_count)))
            ->on($document)->with(['extraction' => $document->extraction_status->value]);

        return DocumentResource::make($document->load('module')->loadCount('summaries'))->response()->setStatusCode(201);
    }

    public function storeNote(CreateNoteRequest $request): JsonResponse
    {
        $document = $this->documents->createNote($request->user(), $request->validated());
        activity()->module(ModuleKey::Learning)->action('documents.note_created')
            ->describe("Wrote lecture note “{$document->title}” ({$document->word_count} words)")->on($document);

        return DocumentResource::make($document->load('module')->loadCount('summaries'))->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        $document->forceFill(['last_opened_at' => now()])->saveQuietly();
        activity()->describe("Opened “{$document->title}”")->on($document);

        return DocumentResource::make($document->load('module')->loadCount(['summaries', 'studyAids']));
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        $data = $request->validated();
        if (isset($data['content'])) {
            abort_unless($document->kind === DocumentKind::Note, 422, 'Only lecture notes written in EDU-SMART can be edited.');
            $this->documents->updateNoteContent($document, $data['content']);
            unset($data['content']);
        }
        $renamed = isset($data['title']) && $data['title'] !== $document->title ? $document->title : null;
        $document->update($data);

        $what = collect([
            $renamed ? "renamed from “{$renamed}”" : null,
            $document->wasChanged('module_id') ? 'moved to module '.($document->module?->code ?? 'none') : null,
            $document->wasChanged('topic') ? 'topic set to '.($document->topic ?? 'none') : null,
            $document->wasChanged('is_favorite') ? ($document->is_favorite ? 'pinned' : 'unpinned') : null,
        ])->filter()->implode(', ');
        activity()->action('documents.updated')->describe("Updated “{$document->title}”".($what ? " — {$what}" : ''))->on($document);

        return DocumentResource::make($document->load('module')->loadCount('summaries'));
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();
        activity()->action('documents.deleted')->describe("Moved “{$document->title}” to trash")->on($document);

        return response()->json(['message' => 'Document moved to trash.']);
    }

    /** Stream the original file to the authenticated owner (inline viewer). */
    public function file(Request $request, Document $document): StreamedResponse
    {
        abort_unless($document->hasFile(), 404, 'The original file is not available.');
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return Storage::disk(config('edusmart.uploads.disk'))->response($document->file_path, $document->original_name ?? basename($document->file_path), [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=600',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors *",
        ], $disposition);
    }

    /** Re-run text extraction. */
    public function extract(Document $document): DocumentResource
    {
        $this->documents->extract($document);
        activity()->action('documents.extracted')->describe("Re-extracted text from “{$document->title}” ({$document->extraction_status->label()})")->on($document);

        return DocumentResource::make($document->load('module')->loadCount('summaries'));
    }

    /**
     * Document intelligence: keywords, key concepts, highlighted reading view
     * and statistics.
     */
    public function analysis(Document $document, ConceptExtractor $concepts): JsonResponse
    {
        abort_if(trim((string) $document->content) === '', 422, 'This document has no extracted text yet.');
        $analysis = $this->analysis->analyze($document);

        return response()->json([
            'document_id' => $document->id,
            'stats' => [
                'words' => $analysis->wordCount,
                'sentences' => count($analysis->sentences),
                'headings' => count($analysis->headings),
                'reading_minutes' => $analysis->readingMinutes(),
            ],
            'headings' => $analysis->headings,
            'keywords' => $analysis->keywords,
            'concepts' => $concepts->extract($analysis, 10),
            'blocks' => $this->analysis->highlightedBlocks($analysis),
        ]);
    }

    /** Distinct topics (for the organise-by-topic view and autocomplete). */
    public function topics(Request $request): JsonResponse
    {
        return response()->json($request->user()->documents()->whereNotNull('topic')
            ->selectRaw('topic, COUNT(*) AS documents')->groupBy('topic')->orderBy('topic')->get());
    }
}
