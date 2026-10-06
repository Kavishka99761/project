<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\StudyActivity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PASINDU — a timed study session. The server is the source of truth for
 * the timer: focus_seconds accumulates every active stretch, so the elapsed
 * time survives page reloads, other devices and network hiccups.
 */
class StudySession extends Model
{
    use Auditable, BelongsToUser;

    protected $guarded = ['id', 'user_id'];

    /** @var list<string> */
    public array $auditExclude = ['feedback', 'last_resumed_at', 'paused_at'];

    protected function casts(): array
    {
        return [
            'activity' => StudyActivity::class,
            'status' => SessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_resumed_at' => 'datetime',
            'paused_at' => 'datetime',
            'feedback' => 'array',
            'planned_minutes' => 'integer',
            'focus_seconds' => 'integer',
            'break_seconds' => 'integer',
            'pause_count' => 'integer',
            'break_count' => 'integer',
            'actual_minutes' => 'integer',
            'avg_engagement' => 'integer',
            'focus_score' => 'integer',
            'rating' => 'integer',
        ];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [SessionStatus::Active, SessionStatus::Paused, SessionStatus::OnBreak]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::Completed);
    }

    public function isLive(): bool
    {
        return in_array($this->status, [SessionStatus::Active, SessionStatus::Paused, SessionStatus::OnBreak], true);
    }

    /** Focused seconds so far, including the currently running stretch. */
    public function elapsedFocusSeconds(): int
    {
        $running = $this->status === SessionStatus::Active && $this->last_resumed_at
            ? max(0, (int) $this->last_resumed_at->diffInSeconds(now()))
            : 0;

        return $this->focus_seconds + $running;
    }

    /** Seconds spent in the current break, if on one. */
    public function currentBreakSeconds(): int
    {
        return $this->status === SessionStatus::OnBreak && $this->paused_at
            ? max(0, (int) $this->paused_at->diffInSeconds(now()))
            : 0;
    }

    public function module(): BelongsTo { return $this->belongsTo(Module::class); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class)->withTrashed(); }
    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class)->withTrashed(); }
    public function events(): HasMany { return $this->hasMany(StudySessionEvent::class); }
    public function engagementLogs(): HasMany { return $this->hasMany(EngagementLog::class); }
}
