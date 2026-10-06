<?php

namespace App\Http\Resources\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatMessage */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->chat_conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'sources' => $this->sources ?? [],
            'confidence' => $this->confidence,
            'suggestions' => $this->suggestions ?? [],
            'feedback' => $this->feedback,
            'meta' => $this->meta,
            'conversation_title' => $this->whenLoaded('conversation', fn () => $this->conversation?->title),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
