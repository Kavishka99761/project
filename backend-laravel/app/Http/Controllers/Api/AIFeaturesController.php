<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\NotebookLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Features Controller
 * 
 * Exposes NotebookLM-powered features:
 * - Audio study guides
 * - Interactive quizzes
 * - Flashcard decks
 * - Personalized study plans
 * - Comprehension analysis
 * - Concept extraction
 */
class AIFeaturesController extends Controller
{
    public function __construct(private readonly NotebookLMService $notebookLM) {}

    /** POST /api/ai/audio-guide — Generate audio study guide from document */
    public function generateAudioGuide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'title'       => ['nullable', 'string', 'max:200'],
        ]);

        $doc = Document::findOrFail($data['document_id']);
        $this->authorizeOwner($request, $doc);

        if (!$doc->extracted_text) {
            return response()->json(['error' => 'Document has no extracted text'], 422);
        }

        $result = $this->notebookLM->generateAudioGuide(
            $doc->extracted_text,
            $data['title'] ?? $doc->title
        );

        return response()->json($result, 201);
    }

    /** POST /api/ai/quiz — Generate interactive quiz from document */
    public function generateQuiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id'    => ['required', 'integer', 'exists:documents,id'],
            'question_count' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $doc = Document::findOrFail($data['document_id']);
        $this->authorizeOwner($request, $doc);

        if (!$doc->extracted_text) {
            return response()->json(['error' => 'Document has no extracted text'], 422);
        }

        $result = $this->notebookLM->generateQuiz(
            $doc->extracted_text,
            $data['question_count'] ?? 10
        );

        return response()->json($result, 201);
    }

    /** POST /api/ai/flashcards — Generate flashcard deck from document */
    public function generateFlashcards(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'card_count'  => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $doc = Document::findOrFail($data['document_id']);
        $this->authorizeOwner($request, $doc);

        if (!$doc->extracted_text) {
            return response()->json(['error' => 'Document has no extracted text'], 422);
        }

        $result = $this->notebookLM->generateFlashcards(
            $doc->extracted_text,
            $data['card_count'] ?? 20
        );

        return response()->json($result, 201);
    }

    /** POST /api/ai/study-plan — Generate personalized study plan */
    public function generateStudyPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id'    => ['required', 'integer', 'exists:documents,id'],
            'available_hours' => ['nullable', 'integer', 'min:1', 'max:100'],
            'learning_style' => ['nullable', 'in:visual,auditory,reading,kinesthetic,mixed'],
        ]);

        $doc = Document::findOrFail($data['document_id']);
        $this->authorizeOwner($request, $doc);

        if (!$doc->extracted_text) {
            return response()->json(['error' => 'Document has no extracted text'], 422);
        }

        $result = $this->notebookLM->generateStudyPlan(
            $doc->extracted_text,
            $data['available_hours'] ?? 5,
            $data['learning_style'] ?? 'mixed'
        );

        return response()->json($result, 201);
    }

    /** POST /api/ai/concepts — Extract key concepts and learning objectives */
    public function extractConcepts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $doc = Document::findOrFail($data['document_id']);
        $this->authorizeOwner($request, $doc);

        if (!$doc->extracted_text) {
            return response()->json(['error' => 'Document has no extracted text'], 422);
        }

        $result = $this->notebookLM->extractConcepts($doc->extracted_text);

        return response()->json($result);
    }

    /** POST /api/ai/analyze — Analyze document for comprehension gaps */
    public function analyzeComprehension(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $doc = Document::findOrFail($data['document_id']);
        $this->authorizeOwner($request, $doc);

        if (!$doc->extracted_text) {
            return response()->json(['error' => 'Document has no extracted text'], 422);
        }

        $result = $this->notebookLM->analyzeComprehension($doc->extracted_text);

        return response()->json($result);
    }

    /** GET /api/ai/features — List available AI features */
    public function listFeatures(Request $request): JsonResponse
    {
        return response()->json([
            'features' => [
                [
                    'id'          => 'audio_guide',
                    'name'        => 'Audio Study Guide',
                    'description' => 'Generate AI-narrated study guides from your documents',
                    'icon'        => 'volume_up',
                    'color'       => '#3b82f6',
                ],
                [
                    'id'          => 'quiz',
                    'name'        => 'Interactive Quiz',
                    'description' => 'Auto-generate quizzes to test your understanding',
                    'icon'        => 'quiz',
                    'color'       => '#8b5cf6',
                ],
                [
                    'id'          => 'flashcards',
                    'name'        => 'Flashcard Deck',
                    'description' => 'Create spaced-repetition flashcards automatically',
                    'icon'        => 'layers',
                    'color'       => '#14b8a6',
                ],
                [
                    'id'          => 'study_plan',
                    'name'        => 'Study Plan',
                    'description' => 'Get a personalized study schedule based on your time',
                    'icon'        => 'calendar_today',
                    'color'       => '#f97316',
                ],
                [
                    'id'          => 'concepts',
                    'name'        => 'Key Concepts',
                    'description' => 'Extract and organize key concepts and objectives',
                    'icon'        => 'lightbulb',
                    'color'       => '#fbbf24',
                ],
                [
                    'id'          => 'analysis',
                    'name'        => 'Comprehension Analysis',
                    'description' => 'Identify gaps and get improvement recommendations',
                    'icon'        => 'analytics',
                    'color'       => '#ef4444',
                ],
            ],
        ]);
    }

    private function authorizeOwner(Request $request, Document $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 403, 'Unauthorized');
    }
}
