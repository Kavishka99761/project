<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KAVISHKA — an academic date (deadline / exam / milestone) extracted from a
 * document and added to the shared calendar. Feeds Jithmi's assignment deadlines.
 */
class AcademicDate extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'academic_document_id', 'title', 'event_date', 'type', 'reminder'];

    protected $casts = ['event_date' => 'date'];

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function document(): BelongsTo   { return $this->belongsTo(AcademicDocument::class, 'academic_document_id'); }
    public function assignments(): HasMany  { return $this->hasMany(Assignment::class); }
}
