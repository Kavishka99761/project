<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Enums\ExtractionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * BETHMI — an uploaded learning material (lecture notes, PDF, Word, slides)
 * or a note typed directly into the app, with its extracted text.
 */
class Document extends Model
{
    use Auditable, BelongsToUser, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'user_id'];

    /** @var list<string> */
    protected $hidden = ['content', 'file_path', 'checksum'];

    /** @var list<string> */
    public array $auditExclude = ['content', 'keywords', 'last_opened_at'];

    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'extraction_status' => ExtractionStatus::class,
            'keywords' => 'array',
            'is_favorite' => 'boolean',
            'extracted_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'size_bytes' => 'integer',
            'pages' => 'integer',
            'word_count' => 'integer',
            'reading_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (Document $document) {
            foreach (['summaries', 'study_aids', 'study_sessions'] as $table) {
                DB::table($table)->where('document_id', $document->id)->update(['document_id' => null]);
            }
            if ($document->file_path) {
                Storage::disk(config('edusmart.uploads.disk'))->delete($document->file_path);
            }
        });
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null
            && Storage::disk(config('edusmart.uploads.disk'))->exists($this->file_path);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function studyAids(): HasMany
    {
        return $this->hasMany(StudyAid::class);
    }

    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }
}
