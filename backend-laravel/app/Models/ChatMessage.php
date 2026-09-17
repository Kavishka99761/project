<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KAVISHKA — a single message in a conversation. Bot messages may reference the
 * academic chunk that grounded the answer (source document / section / page).
 */
class ChatMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'role', 'content', 'source_chunk_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function conversation(): BelongsTo { return $this->belongsTo(ChatConversation::class, 'conversation_id'); }
    public function sourceChunk(): BelongsTo  { return $this->belongsTo(AcademicChunk::class, 'source_chunk_id'); }
}
