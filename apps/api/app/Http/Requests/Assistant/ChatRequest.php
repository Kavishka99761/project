<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\ApiRequest;
use App\Enums\KnowledgeCategory;
use Illuminate\Validation\Rule;

/**
 * KAVISHKA — ask the academic chatbot a question.
 */
class ChatRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', $this->owned('chat_conversations')],
            'scope' => ['nullable', 'array'],
            'scope.categories' => ['nullable', 'array'],
            'scope.categories.*' => [Rule::enum(KnowledgeCategory::class)],
            'scope.document_ids' => ['nullable', 'array'],
            'scope.document_ids.*' => ['integer', $this->owned('knowledge_documents')],
        ];
    }
}
