<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PASINDU — planned study time for a day (compared against actual time).
 */
class StudyPlan extends Model
{
    use Auditable, BelongsToUser;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date:Y-m-d',
            'planned_minutes' => 'integer',
            'is_done' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }
}
