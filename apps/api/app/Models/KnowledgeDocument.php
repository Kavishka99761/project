<?php

namespace App\Models;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * KAVISHKA — an institutional document in the assistant's knowledge base
 * (module handbook, project guideline, regulation, module document).
 */
class KnowledgeDocument extends Model
{
    use Auditable, BelongsToUser, SoftDeletes;

    protected $guarded = ['id', 'user_id'];

    /** @var list<string> */
    protected $hidden = ['content', 'file_path', 'checksum'];

    /** @var list<string> */
    public array $auditExclude = ['content'];

    protected function casts(): array
    {
        return [
            'category' => KnowledgeCategory::class,
            'status' => KnowledgeStatus::class,
            'processed_at' => 'datetime',
            'indexed_at' => 'datetime',
            'size_bytes' => 'integer',
            'pages' => 'integer',
            'word_count' => 'integer',
            'chunk_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (KnowledgeDocument $document) {
            DB::table('academic_dates')->where('knowledge_document_id', $document->id)
                ->update(['knowledge_document_id' => null]);
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

    public function module(): BelongsTo { return $this->belongsTo(Module::class); }
    public function chunks(): HasMany { return $this->hasMany(KnowledgeChunk::class); }
    public function academicDates(): HasMany { return $this->hasMany(AcademicDate::class); }
}
