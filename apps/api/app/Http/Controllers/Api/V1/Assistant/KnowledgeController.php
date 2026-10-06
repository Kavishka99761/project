<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Enums\KnowledgeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\UpdateKnowledgeRequest;
use App\Http\Requests\Assistant\UploadKnowledgeRequest;
use App\Http\Resources\Assistant\KnowledgeDocumentResource;
use App\Models\KnowledgeDocument;
use App\Services\Assistant\Bm25Retriever;
use App\Services\Assistant\KnowledgeService;
use App\Services\Assistant\SemanticReranker;
use App\Services\Nlp\Text;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KAVISHKA — Academic Document Knowledge Base: upload university / module
 * documents, module handbooks, project guidelines and regulations; process
 * and index them; search them (all, or one category at a time).
 */
class KnowledgeController extends Controller
{
    public function __construct(private readonly KnowledgeService $knowledge) {}

    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::enum(KnowledgeCategory::class)],
            'q' => ['nullable', 'string', 'max:120'],
            'module_id' => ['nullable', 'integer'],
        ]);

        return KnowledgeDocumentResource::collection($request->user()->knowledgeDocuments()->with('module')->withCount('academicDates')
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['module_id'] ?? null, fn ($q, $id) => $q->where('module_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$search->escapeLike($text).'%')
                ->orWhere('description', 'like', '%'.$search->escapeLike($text).'%')))
            ->latest()->get());
    }

    public function store(UploadKnowledgeRequest $request): JsonResponse
    {
        $document = $this->knowledge->upload($request->user(), $request->file('file'), $request->safe()->except('file'));
        activity()->action('knowledge.uploaded')->describe(sprintf('Added %s “%s” to the knowledge base (%s, %d passages indexed)',
            mb_strtolower($document->category->label()), $document->title, $document->status->label(), $document->chunk_count))->on($document);

        return KnowledgeDocumentResource::make($document->load('module')->loadCount('academicDates'))->response()->setStatusCode(201);
    }

    public function show(Request $request, KnowledgeDocument $knowledge): JsonResponse
    {
        $page = $request->integer('page');
        activity()->describe("Opened “{$knowledge->title}”")->on($knowledge);

        return response()->json([
            'document' => KnowledgeDocumentResource::make($knowledge->load('module')->loadCount('academicDates')),
            'chunks' => $knowledge->chunks()->orderBy('chunk_index')->get(['id', 'chunk_index', 'section', 'page_number', 'content', 'keywords', 'token_count'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'index' => $c->chunk_index, 'section' => $c->section, 'page' => $c->page_number,
                    'content' => $c->content, 'keywords' => $c->keywords, 'tokens' => $c->token_count, 'focus' => $page && $c->page_number === $page,
                ]),
            'sections' => $knowledge->chunks()->whereNotNull('section')->select('section')->selectRaw('MIN(page_number) AS page')
                ->groupBy('section')->orderByRaw('MIN(chunk_index)')->get(),
        ]);
    }

    public function update(UpdateKnowledgeRequest $request, KnowledgeDocument $knowledge): KnowledgeDocumentResource
    {
        $knowledge->update($request->validated());
        activity()->action('knowledge.updated')->describe("Updated knowledge document “{$knowledge->title}”")->on($knowledge);

        return KnowledgeDocumentResource::make($knowledge->load('module'));
    }

    public function destroy(KnowledgeDocument $knowledge): JsonResponse
    {
        $knowledge->delete();
        activity()->action('knowledge.deleted')->describe("Removed “{$knowledge->title}” from the knowledge base")->on($knowledge);

        return response()->json(['message' => 'Document moved to trash. It is no longer used for answers.']);
    }

    /** Re-process: extract, re-index and re-scan for dates. */
    public function process(KnowledgeDocument $knowledge): KnowledgeDocumentResource
    {
        $document = $this->knowledge->process($knowledge);
        activity()->action('knowledge.processed')->describe("Re-processed “{$document->title}” ({$document->chunk_count} passages)")->on($document);

        return KnowledgeDocumentResource::make($document->load('module')->loadCount('academicDates'));
    }

    public function file(Request $request, KnowledgeDocument $knowledge): StreamedResponse
    {
        abort_unless($knowledge->hasFile(), 404, 'The original file is not available.');

        return Storage::disk(config('edusmart.uploads.disk'))->response($knowledge->file_path, $knowledge->original_name ?? basename($knowledge->file_path), [
            'Content-Type' => $knowledge->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=600',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors *",
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    /**
     * Search academic documents: all, or only handbooks / project guidelines
     * / regulations, ranked by BM25 (+ semantic re-ranking when available).
     */
    public function search(Request $request, Bm25Retriever $retriever, SemanticReranker $reranker): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'category' => ['nullable', Rule::enum(KnowledgeCategory::class)],
            'document_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'between:1,30'],
        ]);
        $scope = array_filter([
            'categories' => isset($data['category']) ? [$data['category']] : null,
            'document_ids' => isset($data['document_id']) ? [(int) $data['document_id']] : null,
        ]);

        $hits = $retriever->search($request->user()->id, $data['q'], $scope, $data['limit'] ?? 10);
        $reranked = $reranker->rerank($data['q'], $hits);
        $terms = array_unique(array_filter(Text::words($data['q']), fn ($w) => mb_strlen($w) > 2));

        activity()->describe(sprintf('Searched academic documents for “%s” (%d results)', $data['q'], count($hits)))
            ->with(['query' => $data['q'], 'category' => $data['category'] ?? 'all']);

        return response()->json([
            'query' => $data['q'],
            'retrieval' => $reranked['semantic'] ? 'bm25+semantic' : 'bm25',
            'terms' => array_values($terms),
            'results' => array_map(fn ($hit) => [
                'chunk_id' => $hit['chunk']->id,
                'knowledge_document_id' => $hit['chunk']->knowledge_document_id,
                'document' => $hit['chunk']->document?->title,
                'category' => $hit['chunk']->document?->category?->value,
                'category_label' => $hit['chunk']->document?->category?->label(),
                'section' => $hit['chunk']->section,
                'page' => $hit['chunk']->page_number,
                'content' => $hit['chunk']->content,
                'relevance' => $hit['relevance'],
                'score' => $hit['score'],
            ], $reranked['hits']),
        ]);
    }
}
