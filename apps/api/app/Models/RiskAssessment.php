<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JITHMI — a stored risk snapshot (score, level, factors and reasons) taken
 * whenever an assignment's progress, deadline or workload changes.
 */
class RiskAssessment extends Model
{
    use BelongsToUser;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'level' => RiskLevel::class,
            'score' => 'integer',
            'probability' => 'float',
            'remaining_hours' => 'float',
            'available_hours' => 'float',
            'required_hours_per_day' => 'float',
            'available_hours_per_day' => 'float',
            'days_left' => 'float',
            'load_ratio' => 'float',
            'factors' => 'array',
            'reasons' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }
}
