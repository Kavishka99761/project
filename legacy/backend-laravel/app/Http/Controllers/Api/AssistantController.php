<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicChunk;
use App\Models\AcademicDate;
use App\Models\AcademicDocument;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\RetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KAVISHKA — AI Academic Assistant.
 * RAG chatbot grounded in the student's indexed academic documents, plus the
 * academic-date calendar that feeds JITHMI's deadlines (integration).
 */
class AssistantController extends Controller
{
    public function __construct(private readonly RetrievalService $retrieval) {}

    /* ------------------------------------------------------------------ *
     *  Knowledge base
     * ------------------------------------------------------------------ */

    /** GET /api/assistant/knowledge — academic documents + chunk counts. */
    public function knowledge(Request $request): JsonResponse
    {
        $docs = $request->user()->academicDocuments()->withCount('chunks')->get();

        return response()->json($docs);
    }

    /** POST /api/assistant/knowledge — add an academic document and index chunks. */
    public function storeKnowledge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'in:Handbook,Project,Regulation'],
            'title'    => ['required', 'string', 'max:200'],
            'pages'    => ['nullable', 'integer', 'min:0'],
            'chunks'   => ['nullable', 'array'],
            'chunks.*.section' => ['nullable', 'string', 'max:200'],
            'chunks.*.page'    => ['nullable', 'integer', 'min:0'],
            'chunks.*.content' => ['required', 'string'],
            'chunks.*.keywords' => ['nullable', 'array'],
            'chunks.*.keywords.*' => ['string'],
        ]);

        $doc = $request->user()->academicDocuments()->create([
            'category'   => $data['category'] ?? 'Handbook',
            'title'      => $data['title'],
            'pages'      => $data['pages'] ?? 0,
            'is_indexed' => true,
        ]);

        foreach ($data['chunks'] ?? [] as $chunk) {
            AcademicChunk::create([
                'academic_document_id' => $doc->id,
                'section'              => $chunk['section'] ?? null,
                'page'                 => $chunk['page'] ?? 0,
                'content'              => $chunk['content'],
                'keywords'             => $chunk['keywords'] ?? $this->autoKeywords($chunk['content']),
            ]);
        }

        return response()->json($doc->loadCount('chunks'), 201);
    }

    /** DELETE /api/assistant/knowledge/{doc} */
    public function destroyKnowledge(Request $request, AcademicDocument $doc): JsonResponse
    {
        abort_unless($doc->user_id === $request->user()->id, 403);
        $doc->delete(); // chunks cascade

        return response()->json(['message' => 'Document removed from knowledge base']);
    }

    /* ------------------------------------------------------------------ *
     *  Chat
     * ------------------------------------------------------------------ */

    /** GET /api/assistant/conversations */
    public function conversations(Request $request): JsonResponse
    {
        $convs = $request->user()->conversations()->withCount('messages')->latest()->get();

        return response()->json($convs);
    }

    /** GET /api/assistant/conversations/{conversation}/messages */
    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        return response()->json(
            $conversation->messages()->with('sourceChunk.document:id,title,category')->orderBy('created_at')->get()
        );
    }

    /**
     * POST /api/assistant/chat
     * Body: { message, conversation_id? }. Retrieves the best chunk, stores both
     * the user message and the grounded bot reply, and returns the answer+source.
     */
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message'         => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:chat_conversations,id'],
        ]);

        $user = $request->user();

        $conversation = isset($data['conversation_id'])
            ? ChatConversation::findOrFail($data['conversation_id'])
            : $user->conversations()->create(['title' => str($data['message'])->limit(48)->toString()]);

        abort_unless($conversation->user_id === $user->id, 403);

        // Store the student's question.
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $data['message'],
            'created_at'      => now(),
        ]);

        // Retrieve grounding context from this student's indexed chunks.
        $chunkIds = AcademicChunk::whereIn('academic_document_id', $user->academicDocuments()->pluck('id'))->pluck('id');
        $chunks = AcademicChunk::with('document')->whereIn('id', $chunkIds)->get();

        $result = $this->retrieval->answer($data['message'], $chunks);

        $botMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'bot',
            'content'         => $result['answer'],
            'source_chunk_id' => $result['chunk']?->id,
            'created_at'      => now(),
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'reply'           => $result['answer'],
            'source'          => $result['source'],
            'message'         => $botMessage,
        ], 201);
    }

    /* ------------------------------------------------------------------ *
     *  Academic dates (calendar) — feeds JITHMI
     * ------------------------------------------------------------------ */

    /** GET /api/assistant/dates */
    public function dates(Request $request): JsonResponse
    {
        $dates = $request->user()->academicDates()->with('document:id,title')->orderBy('event_date')->get();

        return response()->json($dates);
    }

    /** POST /api/assistant/dates */
    public function storeDate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                => ['required', 'string', 'max:200'],
            'event_date'           => ['required', 'date'],
            'type'                 => ['nullable', 'in:Deadline,Milestone,Exam,Event'],
            'reminder'             => ['nullable', 'string', 'max:48'],
            'academic_document_id' => ['nullable', 'integer', 'exists:academic_documents,id'],
        ]);

        $date = $request->user()->academicDates()->create([
            'title'                => $data['title'],
            'event_date'           => $data['event_date'],
            'type'                 => $data['type'] ?? 'Milestone',
            'reminder'             => $data['reminder'] ?? '1 day before',
            'academic_document_id' => $data['academic_document_id'] ?? null,
        ]);

        return response()->json($date, 201);
    }

    /** PUT /api/assistant/dates/{date} */
    public function updateDate(Request $request, AcademicDate $date): JsonResponse
    {
        abort_unless($date->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title'      => ['sometimes', 'string', 'max:200'],
            'event_date' => ['sometimes', 'date'],
            'type'       => ['nullable', 'in:Deadline,Milestone,Exam,Event'],
            'reminder'   => ['nullable', 'string', 'max:48'],
        ]);

        $date->update($data);

        return response()->json($date);
    }

    /** DELETE /api/assistant/dates/{date} */
    public function destroyDate(Request $request, AcademicDate $date): JsonResponse
    {
        abort_unless($date->user_id === $request->user()->id, 403);
        $date->delete();

        return response()->json(['message' => 'Date removed']);
    }

    /** @return array<int,string> */
    private function autoKeywords(string $content): array
    {
        preg_match_all('/[a-zA-Z]{4,}/', strtolower($content), $m);
        $counts = array_count_values($m[0] ?? []);
        arsort($counts);

        return array_slice(array_keys($counts), 0, 6);
    }
}
