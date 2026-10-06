<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * JITHMI — an assignment with deadline, priority, workload and progress.
 * `academic_date_id` links a deadline extracted by Kavishka (integration).
 */
class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'module_id', 'academic_date_id', 'title', 'deadline',
        'priority', 'est_hours', 'done_hours', 'progress', 'completed',
    ];

    protected $casts = [
        'deadline'  => 'date',
        'completed' => 'boolean',
        'est_hours' => 'float',
        'done_hours' => 'float',
        'progress'  => 'integer',
    ];

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function module(): BelongsTo          { return $this->belongsTo(Module::class); }
    public function academicDate(): BelongsTo    { return $this->belongsTo(AcademicDate::class); }
    public function riskAssessments(): HasMany   { return $this->hasMany(RiskAssessment::class); }
    public function studySessions(): HasMany     { return $this->hasMany(StudySession::class); }

    /** Latest computed risk assessment. */
    public function latestRisk()
    {
        return $this->hasOne(RiskAssessment::class)->latestOfMany('calculated_at');
    }
}
