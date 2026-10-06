<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KAVISHKA — a question or a grounded answer. Assistant messages carry the
 * cited sources (document, section, page, supporting excerpt, relevance).
 */
class ChatMessage extends Model
{
    use BelongsToUser;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'suggestions' => 'array',
            'meta' => 'array',
            'confidence' => 'float',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
