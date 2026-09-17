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

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
}
