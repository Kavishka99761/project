<?php

namespace App\Http\Resources\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatConversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scope' => $this->scope,
            'is_pinned' => $this->is_pinned,
            'message_count' => $this->message_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
