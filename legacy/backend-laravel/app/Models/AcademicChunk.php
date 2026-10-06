<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KAVISHKA — a chunked, indexed passage from an academic document. The chatbot
 * retrieves the best-matching chunk to ground its answer (RAG source reference).
 */
class AcademicChunk extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['academic_document_id', 'section', 'page', 'content', 'keywords'];

    protected $casts = ['keywords' => 'array', 'page' => 'integer'];

    public function document(): BelongsTo { return $this->belongsTo(AcademicDocument::class, 'academic_document_id'); }
}
