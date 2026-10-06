<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JITHMI — a submission record created when an assignment is completed.
 */
class AssignmentSubmission extends Model
{
    use BelongsToUser;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'deadline_at' => 'datetime',
            'is_late' => 'boolean',
            'minutes_late' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }
}
