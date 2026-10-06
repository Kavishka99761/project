<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JITHMI — one recorded progress change (manual update, study session or
 * completion), forming the assignment's progress timeline.
 */
class AssignmentProgressLog extends Model
{
    use BelongsToUser;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'progress_before' => 'integer',
            'progress_after' => 'integer',
            'hours_added' => 'float',
            'completed_hours_after' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }
}
