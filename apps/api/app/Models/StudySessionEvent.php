<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PASINDU — one timer transition (started, paused, resumed, break_started,
 * break_ended, engagement_changed, stopped, cancelled).
 */
class StudySessionEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StudySession::class, 'study_session_id');
    }
}
