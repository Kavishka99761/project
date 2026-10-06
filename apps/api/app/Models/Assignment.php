<?php

namespace App\Models;

use App\Enums\AssignmentPriority;
use App\Enums\AssignmentStatus;
use App\Enums\RiskLevel;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * JITHMI — an assignment with deadline, priority, estimated workload and
 * progress. The latest risk assessment is denormalised onto the row
 * (risk_score / risk_level / priority_rank) for fast ranking and filtering.
 */
class Assignment extends Model
{
    use Auditable, BelongsToUser, HasFactory, SoftDeletes;

    protected $guarded = ['id', 'user_id'];

    /** @var list<string> */
    public array $auditExclude = ['risk_score', 'risk_level', 'risk_updated_at', 'priority_rank'];

    protected function casts(): array
    {
        return [
            'priority' => AssignmentPriority::class,
            'status' => AssignmentStatus::class,
            'risk_level' => RiskLevel::class,
            'deadline' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'risk_updated_at' => 'datetime',
            'estimated_hours' => 'float',
            'completed_hours' => 'float',
            'weight_percent' => 'float',
            'progress' => 'integer',
            'risk_score' => 'integer',
            'priority_rank' => 'integer',
            'was_overdue' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (Assignment $assignment) {
            foreach (['study_sessions', 'study_plans'] as $table) {
                DB::table($table)->where('assignment_id', $assignment->id)->update(['assignment_id' => null]);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', AssignmentStatus::Completed);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()->where('deadline', '<', now());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()->where('deadline', '>=', now());
    }

    public function isCompleted(): bool
    {
        return $this->status === AssignmentStatus::Completed;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->deadline->isPast();
    }

    /** Estimated hours of work still to do, from the progress percentage. */
    public function remainingHours(): float
    {
        if ($this->isCompleted()) {
            return 0.0;
        }

        return round(max((float) $this->estimated_hours * (100 - $this->progress) / 100, 0), 2);
    }

    /** upcoming | overdue | completed */
    public function bucket(): string
    {
        return match (true) {
            $this->isCompleted() => 'completed',
            $this->deadline->isPast() => 'overdue',
            default => 'upcoming',
        };
    }

    public function module(): BelongsTo { return $this->belongsTo(Module::class); }
    public function academicDate(): BelongsTo { return $this->belongsTo(AcademicDate::class); }
    public function progressLogs(): HasMany { return $this->hasMany(AssignmentProgressLog::class); }
    public function riskAssessments(): HasMany { return $this->hasMany(RiskAssessment::class); }
    public function submissions(): HasMany { return $this->hasMany(AssignmentSubmission::class); }
    public function studySessions(): HasMany { return $this->hasMany(StudySession::class); }
}
