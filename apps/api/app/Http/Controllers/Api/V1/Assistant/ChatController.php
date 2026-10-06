<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\ChatRequest;
use App\Http\Resources\Assistant\ChatMessageResource;
use App\Http\Resources\Assistant\ConversationResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Assistant\ChatService;
use App\Services\Exports\DocxWriter;
use App\Services\Exports\ExportFile;
use App\Services\Exports\ExportService;
use App\Services\Platform\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * KAVISHKA — Academic Chatbot: ask in natural language, get answers grounded
 * in retrieved documents with source document / section / page; search
 * previous questions; view and clear conversation history.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function ask(ChatRequest $request): JsonResponse
    {
        $conversation = $request->validated('conversation_id')
            ? ChatConversation::query()->ownedBy($request->user())->findOrFail($request->validated('conversation_id'))
            : null;

        $result = $this->chat->ask($request->user(), $request->validated('message'), $conversation, $request->validated('scope'));
        $answer = $result['answer'];

        activity()->action('assistant.asked')
            ->describe(sprintf('Asked “%s” — %s (%d%% confidence)', Str::limit($request->validated('message'), 120),
                ($answer->meta['found'] ?? false) ? 'answered from '.count(array_filter($answer->sources ?? [], fn ($s) => $s['cited'])).' source(s)' : 'no source found',
                (int) round(($answer->confidence ?? 0) * 100)))
            ->on($result['conversation']);

        return response()->json([
            'conversation' => ConversationResource::make($result['conversation']),
            'question' => ChatMessageResource::make($result['question']),
            'answer' => ChatMessageResource::make($answer),
        ], 201);
    }

    public function conversations(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $q = $request->input('q');

        return ConversationResource::collection($request->user()->conversations()
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('title', 'like', '%'.$search->escapeLike($q).'%')
                ->orWhereHas('messages', fn ($m) => $m->where('content', 'like', '%'.$search->escapeLike($q).'%'))))
            ->orderByDesc('is_pinned')->orderByDesc('last_message_at')
            ->paginate(min(100, $request->integer('per_page', 30))));
    }

    public function show(ChatConversation $conversation): ConversationResource
    {
        activity()->describe("Opened conversation “{$conversation->title}”")->on($conversation);

        return ConversationResource::make($conversation->load(['messages' => fn ($q) => $q->orderBy('created_at')->orderBy('id')]));
    }

    public function update(Request $request, ChatConversation $conversation): ConversationResource
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'is_pinned' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'nullable', 'array'],
        ]);
        $conversation->update($data);
        activity()->action('assistant.conversation_updated')->describe("Updated conversation “{$conversation->title}”")->on($conversation);

        return ConversationResource::make($conversation);
    }

    public function destroy(ChatConversation $conversation): JsonResponse
    {
        $conversation->delete();
        activity()->action('assistant.conversation_deleted')->describe("Deleted conversation “{$conversation->title}”");

        return response()->json(['message' => 'Conversation deleted.']);
    }

    /** Clear the entire conversation history. */
    public function clear(Request $request): JsonResponse
    {
        $count = $request->user()->conversations()->count();
        $request->user()->conversations()->delete();
        activity()->action('assistant.history_cleared')->describe("Cleared conversation history ({$count} conversations)");

        return response()->json(['message' => 'Conversation history cleared.', 'deleted' => $count]);
    }

    /** Search previous questions (and answers). */
    public function searchMessages(Request $request, SearchService $search): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
            'role' => ['nullable', Rule::in(['user', 'assistant'])],
        ]);
        activity()->describe("Searched previous questions for “{$data['q']}”");

        return ChatMessageResource::collection($request->user()->chatMessages()->with('conversation:id,title')
            ->where('content', 'like', '%'.$search->escapeLike($data['q']).'%')
            ->where('role', $data['role'] ?? 'user')
            ->latest()->limit(50)->get());
    }

    public function feedback(Request $request, ChatMessage $message): ChatMessageResource
    {
        abort_unless($message->role === 'assistant', 422, 'Only answers can be rated.');
        $data = $request->validate(['feedback' => ['nullable', Rule::in(['helpful', 'not_helpful'])]]);
        $message->update(['feedback' => $data['feedback'] ?? null]);
        activity()->action('assistant.feedback')->describe('Rated an answer as '.str_replace('_', ' ', $data['feedback'] ?? 'unrated'));

        return ChatMessageResource::make($message);
    }

    /** Export one conversation as pdf | docx | md | json. */
    public function export(Request $request, ChatConversation $conversation, ExportService $exports): Response
    {
        $format = $request->validate(['format' => ['required', Rule::in(['pdf', 'docx', 'md', 'json'])]])['format'];
        $messages = $conversation->messages()->orderBy('created_at')->orderBy('id')->get();
        $base = Str::slug(Str::limit($conversation->title, 50, '')) ?: 'conversation';

        $markdown = '# '.$conversation->title."\n\n".$messages->map(function ($m) {
            $who = $m->role === 'user' ? '**You**' : '**Assistant**';
            $sources = collect($m->sources ?? [])->where('cited', true)
                ->map(fn ($s) => "> [{$s['n']}] {$s['document']}".($s['section'] ? " — {$s['section']}" : '').($s['page'] ? ", p. {$s['page']}" : ''))->implode("\n");

            return "{$who} · ".$m->created_at->format('d M Y H:i')."\n\n{$m->content}".($sources ? "\n\n{$sources}" : '');
        })->implode("\n\n---\n\n")."\n";

        $file = match ($format) {
            'pdf' => new ExportFile("{$base}.pdf", 'application/pdf', $exports->pdf('exports.conversation', ['conversation' => $conversation, 'messages' => $messages, 'user' => $request->user()]), $messages->count()),
            'md' => new ExportFile("{$base}.md", 'text/markdown; charset=UTF-8', $markdown, $messages->count()),
            'json' => new ExportFile("{$base}.json", 'application/json', json_encode(ConversationResource::make($conversation->setRelation('messages', $messages))->resolve($request), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $messages->count()),
            'docx' => new ExportFile("{$base}.docx", 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $this->docx($conversation, $messages), $messages->count()),
        };
        $exports->log($request->user(), 'conversation', $format, $file, ['conversation_id' => $conversation->id]);

        return $file->toResponse();
    }

    private function docx(ChatConversation $conversation, $messages): string
    {
        $writer = (new DocxWriter)->heading($conversation->title)
            ->paragraph($messages->count().' messages · '.$conversation->created_at->format('d M Y'), italic: true, color: '64748B');
        foreach ($messages as $message) {
            $writer->labelled($message->role === 'user' ? 'You:' : 'Assistant:', $message->content);
            foreach (collect($message->sources ?? [])->where('cited', true) as $source) {
                $writer->paragraph("[{$source['n']}] {$source['document']}".($source['section'] ? " — {$source['section']}" : '').($source['page'] ? ", p. {$source['page']}" : ''), italic: true, color: '7C3AED');
            }
        }

        return $writer->toBinary($conversation->title);
    }
}
