<?php

namespace App\Models;

use App\Enums\EngagementLevel;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PASINDU — an engagement sample: automatic (tab focus, activity, idle time)
 * or a manual concentration report (1–5).
 */
class EngagementLog extends Model
{
    use BelongsToUser;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'level' => EngagementLevel::class,
            'signals' => 'array',
            'logged_at' => 'datetime',
            'score' => 'integer',
            'concentration' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StudySession::class, 'study_session_id');
    }
}
