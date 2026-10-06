<?php

namespace App\Http\Controllers\Api\V1\Learning;

use App\Enums\SummaryLength;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\GenerateSummaryRequest;
use App\Http\Requests\Learning\UpdateSummaryRequest;
use App\Http\Resources\Learning\SummaryResource;
use App\Models\Document;
use App\Models\Summary;
use App\Services\Exports\DocxWriter;
use App\Services\Exports\ExportFile;
use App\Services\Exports\ExportService;
use App\Services\Learning\SummaryService;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * BETHMI — Summary Management: generate (short / medium / detailed) with
 * keywords, key concepts, highlighted sentences and revision notes; save,
 * view, search, download (PDF, Word, Markdown, text, JSON) and delete.
 */
class SummaryController extends Controller
{
    public function __construct(private readonly SummaryService $summaries) {}

    /** Generate a summary; saved immediately unless save=false (preview). */
    public function generate(GenerateSummaryRequest $request, Document $document): JsonResponse
    {
        $length = SummaryLength::from($request->validated('length'));
        $payload = $this->summaries->generate($document, $length);

        if (! $request->boolean('save', true)) {
            activity()->action('summaries.previewed')->describe("Generated a {$length->label()} summary preview of “{$document->title}”")->on($document);

            return response()->json(['saved' => false, 'summary' => $payload]);
        }

        $summary = $this->summaries->save($document, $payload, $request->validated('title'));
        activity()->action('summaries.generated')
            ->describe(sprintf('Generated and saved a %s summary of “%s” (%d words, %d%% of the original)', $length->label(), $document->title, $payload['word_count'], $payload['compression']))
            ->on($summary);

        return response()->json(['saved' => true, 'summary' => SummaryResource::make($summary->load(['document', 'module']))], 201);
    }

    /** Save a previewed summary. */
    public function store(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'length' => ['required', Rule::enum(SummaryLength::class)],
            'title' => ['nullable', 'string', 'max:200'],
        ]);
        $summary = $this->summaries->generateAndSave($document, SummaryLength::from($data['length']), $data['title'] ?? null);
        activity()->action('summaries.saved')->describe("Saved summary “{$summary->title}”")->on($summary);

        return response()->json(SummaryResource::make($summary->load(['document', 'module'])), 201);
    }

    public function index(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'length' => ['nullable', Rule::enum(SummaryLength::class)],
            'module_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'favorite' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = $request->user()->summaries()->with(['document', 'module'])->latest();
        if ($q = $filters['q'] ?? null) {
            $like = '%'.$search->escapeLike($q).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like)->orWhere('keywords', 'like', $like));
        }
        $query->when($filters['length'] ?? null, fn ($q, $length) => $q->where('length', $length))
            ->when($filters['module_id'] ?? null, fn ($q, $id) => $q->where('module_id', $id))
            ->when($filters['document_id'] ?? null, fn ($q, $id) => $q->where('document_id', $id))
            ->when($request->boolean('favorite'), fn ($q) => $q->where('is_favorite', true));

        return SummaryResource::collection($query->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function show(Summary $summary): SummaryResource
    {
        activity()->describe("Viewed summary “{$summary->title}”")->on($summary);

        return SummaryResource::make($summary->load(['document', 'module']));
    }

    public function update(UpdateSummaryRequest $request, Summary $summary): SummaryResource
    {
        $summary->update($request->validated());
        activity()->action('summaries.updated')->describe("Updated summary “{$summary->title}”")->on($summary);

        return SummaryResource::make($summary->load(['document', 'module']));
    }

    public function destroy(Summary $summary): JsonResponse
    {
        $summary->delete();
        activity()->action('summaries.deleted')->describe("Moved summary “{$summary->title}” to trash")->on($summary);

        return response()->json(['message' => 'Summary moved to trash.']);
    }

    /** Download as pdf | docx | md | txt | json. */
    public function download(Request $request, Summary $summary, ExportService $exports): Response
    {
        $format = $request->validate(['format' => ['required', Rule::in(['pdf', 'docx', 'md', 'txt', 'json'])]])['format'];
        $summary->load('document');
        $base = Str::slug(Str::limit($summary->title, 60, '')) ?: 'summary';
        $markdown = $this->summaries->toMarkdown($summary);

        $file = match ($format) {
            'pdf' => new ExportFile("{$base}.pdf", 'application/pdf', $exports->pdf('exports.summary', ['summary' => $summary, 'user' => $request->user()]), 1),
            'docx' => new ExportFile("{$base}.docx", 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $this->docx($summary), 1),
            'md' => new ExportFile("{$base}.md", 'text/markdown; charset=UTF-8', $markdown, 1),
            'txt' => new ExportFile("{$base}.txt", 'text/plain; charset=UTF-8', preg_replace(['/^#+\s*/m', '/\*\*(.*?)\*\*/', '/^_(.*)_$/m'], ['', '$1', '$1'], $markdown) ?? $markdown, 1),
            'json' => new ExportFile("{$base}.json", 'application/json', json_encode(SummaryResource::make($summary)->resolve($request), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1),
        };

        $exports->log($request->user(), 'summary', $format, $file, ['summary_id' => $summary->id]);
        activity()->describe("Downloaded summary “{$summary->title}” as ".strtoupper($format))->on($summary);

        return $file->toResponse();
    }

    private function docx(Summary $summary): string
    {
        $writer = (new DocxWriter)->heading($summary->title)
            ->paragraph(($summary->document ? 'Source: '.$summary->document->title.' · ' : '').$summary->length->label().' summary · '.$summary->created_at->format('d M Y'), italic: true, color: '64748B')
            ->heading('Summary', 2);

        foreach (preg_split('/\n{2,}/', $summary->content) ?: [] as $block) {
            if (preg_match('/^### (.+?)(?:\n+(.*))?$/s', $block, $m)) {
                $writer->heading($m[1], 3);
                if (! empty($m[2])) {
                    $writer->paragraph($m[2]);
                }
            } else {
                $writer->paragraph($block);
            }
        }
        if ($summary->key_concepts) {
            $writer->heading('Key concepts', 2);
            foreach ($summary->key_concepts as $concept) {
                $writer->labelled($concept['term'].' —', $concept['definition']);
            }
        }
        if ($summary->bullet_points) {
            $writer->heading('Revision notes', 2);
            foreach ($summary->bullet_points as $group) {
                $writer->heading($group['heading'], 3);
                foreach ($group['bullets'] as $bullet) {
                    $writer->bullet($bullet);
                }
            }
        }
        if ($summary->keywords) {
            $writer->heading('Keywords', 2)->paragraph(implode(', ', array_column($summary->keywords, 'term')));
        }

        return $writer->toBinary($summary->title);
    }
}
