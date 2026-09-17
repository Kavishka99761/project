<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BETHMI — an uploaded learning material (lecture note / PDF / Word file).
 */
class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'module_id', 'title', 'topic', 'type',
        'pages', 'size_bytes', 'file_path', 'extracted_text',
    ];

    protected $casts = ['pages' => 'integer', 'size_bytes' => 'integer'];

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function module(): BelongsTo    { return $this->belongsTo(Module::class); }
    public function summaries(): HasMany   { return $this->hasMany(Summary::class); }
    public function studySessions(): HasMany { return $this->hasMany(StudySession::class); }
}
