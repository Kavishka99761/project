<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Summary;
use App\Services\SummaryGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BETHMI — summary & revision-note generation.
 * Produces Short / Medium / Detailed summaries plus keywords from a document's
 * extracted text using the SummaryGenerator service.
 */
class SummaryController extends Controller
{
    public function __construct(private readonly SummaryGenerator $generator) {}

    /** GET /api/summaries — all summaries for the student (with their document). */
    public function index(Request $request): JsonResponse
    {
        $summaries = $request->user()->summaries()->with('document')->latest()->get();

        return response()->json($summaries);
    }

    /** GET /api/summaries/{summary} */
    public function show(Request $request, Summary $summary): JsonResponse
    {
        $this->authorizeOwner($request, $summary);

        return response()->json($summary->load('document'));
    }

    /**
     * POST /api/documents/{document}/summaries
     * Generate (and persist) a summary of the given document's extracted text.
     */
    public function store(Request $request, Document $document): JsonResponse
    {
        abort_unless($document->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'length_type' => ['nullable', 'in:Short,Medium,Detailed'],
            'title'       => ['nullable', 'string', 'max:200'],
        ]);

        $text = trim((string) $document->extracted_text);
        if ($text === '') {
            return response()->json([
                'message' => 'This document has no extracted text to summarise yet.',
            ], 422);
        }

        $result = $this->generator->generate($text, $data['length_type'] ?? 'Medium', $data['title'] ?? null);

        $summary = $request->user()->summaries()->create([
            'document_id' => $document->id,
            'title'       => $result['title'],
            'length_type' => $result['length_type'],
            'body'        => $result['body'],
            'keywords'    => $result['keywords'],
        ]);

        return response()->json($summary, 201);
    }

    /** DELETE /api/summaries/{summary} */
    public function destroy(Request $request, Summary $summary): JsonResponse
    {
        $this->authorizeOwner($request, $summary);
        $summary->delete();

        return response()->json(['message' => 'Summary deleted']);
    }

    private function authorizeOwner(Request $request, Summary $summary): void
    {
        abort_unless($summary->user_id === $request->user()->id, 403, 'This summary belongs to another user.');
    }
}
