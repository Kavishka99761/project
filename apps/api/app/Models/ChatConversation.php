<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KAVISHKA — a chatbot conversation, optionally scoped to selected
 * knowledge-base categories or documents (NotebookLM-style source picking).
 */
class ChatConversation extends Model
{
    use Auditable, BelongsToUser;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'is_pinned' => 'boolean',
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
