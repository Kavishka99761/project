<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BETHMI — a generated summary of a document (short / medium / detailed).
 */
class Summary extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'document_id', 'title', 'length_type', 'body', 'keywords'];

    protected $casts = ['keywords' => 'array'];

    /** Append virtual fields so the frontend receives `text` and `length` aliases. */
    protected $appends = ['text', 'length'];

    public function getTextAttribute(): string  { return (string) $this->body; }
    public function getLengthAttribute(): string { return (string) $this->length_type; }

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
}
