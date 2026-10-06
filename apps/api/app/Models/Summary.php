<?php

namespace App\Models;

use App\Enums\SummaryLength;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BETHMI — a saved summary with its revision notes, keywords, key concepts
 * and highlighted sentences.
 */
class Summary extends Model
{
    use Auditable, BelongsToUser, SoftDeletes;

    protected $guarded = ['id', 'user_id'];

    /** @var list<string> */
    public array $auditExclude = ['content', 'bullet_points', 'keywords', 'key_concepts', 'highlights'];

    protected function casts(): array
    {
        return [
            'length' => SummaryLength::class,
            'bullet_points' => 'array',
            'keywords' => 'array',
            'key_concepts' => 'array',
            'highlights' => 'array',
            'is_favorite' => 'boolean',
            'word_count' => 'integer',
            'source_word_count' => 'integer',
            'sentence_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
