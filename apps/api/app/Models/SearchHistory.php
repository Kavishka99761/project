<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * A global search the student ran (also mirrored to Firestore).
 */
class SearchHistory extends Model
{
    use BelongsToUser;

    public const UPDATED_AT = null;

    protected $table = 'search_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'results_count' => 'integer'];
    }
}
