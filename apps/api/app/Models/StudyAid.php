<?php

namespace App\Models;

use App\Enums\StudyAidType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BETHMI — generated flashcards, quiz or mind map for a document.
 */
class StudyAid extends Model
{
    use Auditable, BelongsToUser;

    protected $guarded = ['id', 'user_id'];

    /** @var list<string> */
    public array $auditExclude = ['content'];

    protected function casts(): array
    {
        return [
            'type' => StudyAidType::class,
            'content' => 'array',
            'item_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }
}
