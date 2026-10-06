<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * KAVISHKA — academic chatbot. Retrieves passages from the student's
 * knowledge base (BM25 + optional semantic re-ranking), composes a cited
 * answer and stores the full exchange as conversation history.
 */
class ChatService
{
    public function __construct(
        private readonly Bm25Retriever $retriever,
        private readonly SemanticReranker $reranker,
        private readonly AnswerComposer $composer,
    ) {}

    /**
     * @param  array{categories?: list<string>, document_ids?: list<int>}|null  $scope
     * @return array{conversation: ChatConversation, question: ChatMessage, answer: ChatMessage}
     */
    public function ask(User $user, string $question, ?ChatConversation $conversation = null, ?array $scope = null): array
    {
        $started = microtime(true);
        $question = trim($question);
        $scope ??= $conversation?->scope ?? [];

        $hits = $this->retriever->search($user->id, $question, $scope, (int) config('edusmart.assistant.top_k', 5) + 3);
        $reranked = $this->reranker->rerank($question, $hits);
        $hits = array_slice($reranked['hits'], 0, (int) config('edusmart.assistant.top_k', 5));

        $result = $this->composer->compose(
            $question,
            $hits,
            $this->retriever->distinctiveWeights($question),
            $this->retriever->originalTerms($question),
        );

        return DB::transaction(function () use ($user, $question, $conversation, $scope, $result, $reranked, $started) {
            $conversation ??= $user->conversations()->create([
                'title' => Str::limit($question, 80),
                'scope' => $scope ?: null,
            ]);

            $userMessage = $conversation->messages()->create([
                'user_id' => $user->id,
                'role' => 'user',
                'content' => $question,
            ]);

            $answer = $conversation->messages()->create([
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => $result['answer'],
                'sources' => $result['sources'],
                'confidence' => $result['confidence'],
                'suggestions' => $result['suggestions'],
                'meta' => [
                    'found' => $result['found'],
                    'method' => $result['method'],
                    'question_type' => $result['question_type'],
                    'retrieval' => $reranked['semantic'] ? 'bm25+semantic' : 'bm25',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ],
            ]);

            $conversation->update([
                'message_count' => $conversation->messages()->count(),
                'last_message_at' => now(),
            ]);

            return ['conversation' => $conversation->refresh(), 'question' => $userMessage, 'answer' => $answer];
        });
    }
}
