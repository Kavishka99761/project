<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KAVISHKA — an official academic/institutional document (handbook, project
 * guidelines, regulations) indexed for retrieval by the chatbot.
 */
class AcademicDocument extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category', 'title', 'pages', 'file_path', 'is_indexed'];

    protected $casts = ['is_indexed' => 'boolean', 'pages' => 'integer'];

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function chunks(): HasMany       { return $this->hasMany(AcademicChunk::class); }
    public function academicDates(): HasMany { return $this->hasMany(AcademicDate::class); }
}
