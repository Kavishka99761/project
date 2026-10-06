<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KAVISHKA — an indexed passage of a knowledge document. `terms` is the
 * stemmed term-frequency vector used by the BM25 retriever.
 */
class KnowledgeChunk extends Model
{
    use BelongsToUser;

    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['terms'];

    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'keywords' => 'array',
            'chunk_index' => 'integer',
            'page_number' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id')->withTrashed();
    }
}
