<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KAVISHKA — a chatbot conversation (thread) belonging to a student.
 */
class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function messages(): HasMany   { return $this->hasMany(ChatMessage::class, 'conversation_id'); }
}
