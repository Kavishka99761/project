<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\SummaryGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * BETHMI — Smart Notes & Document Management.
 * Upload, list, search/filter, rename, delete learning materials, and expose the
 * extracted text that feeds summary generation.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly SummaryGenerator $summariser) {}

    /** GET /api/documents?q=&module_id=&type= */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->documents()->with('module')->latest();

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->integer('module_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('topic', 'like', $term)
                    ->orWhere('extracted_text', 'like', $term);
            });
        }

        return response()->json($query->get());
    }

    /** GET /api/documents/{document} */
    public function show(Request $request, Document $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        return response()->json($document->load(['module', 'summaries']));
    }

    /**
     * POST /api/documents
     * Accepts either an uploaded file (stored on disk, text extracted if plain)
     * or raw text pasted by the user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:200'],
            'topic'     => ['nullable', 'string', 'max:120'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'type'      => ['nullable', 'in:PDF,Word,Text'],
            'pages'     => ['nullable', 'integer', 'min:0'],
            'extracted_text' => ['nullable', 'string'],
            'file'      => ['nullable', 'file', 'max:20480'], // 20 MB
        ]);

        $filePath = null;
        $sizeBytes = 0;
        $type = $data['type'] ?? 'Text';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('documents/'.$request->user()->id, 'local');
            $sizeBytes = $file->getSize();
            $type = $data['type'] ?? $this->guessType($file->getClientOriginalExtension());

            // Best-effort plain-text extraction for .txt/.md uploads.
            if (in_array(strtolower($file->getClientOriginalExtension()), ['txt', 'md'], true)) {
                $data['extracted_text'] = $data['extracted_text']
                    ?? Storage::disk('local')->get($filePath);
            }
        }

        $document = $request->user()->documents()->create([
            'module_id'      => $data['module_id'] ?? null,
            'title'          => $data['title'],
            'topic'          => $data['topic'] ?? null,
            'type'           => $type,
            'pages'          => $data['pages'] ?? 0,
            'size_bytes'     => $sizeBytes,
            'file_path'      => $filePath,
            'extracted_text' => $data['extracted_text'] ?? null,
        ]);

        return response()->json($document->load('module'), 201);
    }

    /** PUT /api/documents/{document} — rename / re-file / edit text. */
    public function update(Request $request, Document $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:200'],
            'topic'          => ['nullable', 'string', 'max:120'],
            'module_id'      => ['nullable', 'integer', 'exists:modules,id'],
            'type'           => ['sometimes', 'in:PDF,Word,Text'],
            'pages'          => ['nullable', 'integer', 'min:0'],
            'extracted_text' => ['nullable', 'string'],
        ]);

        $document->update($data);

        return response()->json($document->fresh('module'));
    }

    /** DELETE /api/documents/{document} */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        if ($document->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        return response()->json(['message' => 'Document deleted']);
    }

    /** GET /api/documents/{document}/keywords — quick keyword extraction. */
    public function keywords(Request $request, Document $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        $text = $document->extracted_text ?: $document->title;

        return response()->json([
            'keywords' => $this->summariser->extractKeywords($text, 10),
        ]);
    }

    private function guessType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'PDF',
            'doc', 'docx' => 'Word',
            default => 'Text',
        };
    }

    private function authorizeOwner(Request $request, Document $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 403, 'This document belongs to another user.');
    }
}
