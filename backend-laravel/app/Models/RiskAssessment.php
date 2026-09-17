<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JITHMI — a snapshot of the computed deadline-miss risk for an assignment.
 */
class RiskAssessment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['assignment_id', 'score', 'level', 'hours_per_day', 'reasons', 'calculated_at'];

    protected $casts = ['reasons' => 'array', 'calculated_at' => 'datetime', 'score' => 'integer'];

    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }
}
