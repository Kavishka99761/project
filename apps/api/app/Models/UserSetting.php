<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-student preferences: appearance, goals, study rhythm, weekly
 * availability (used by the risk engine) and notification switches.
 */
class UserSetting extends Model
{
    use Auditable;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'glass_effects' => 'boolean',
            'reduce_motion' => 'boolean',
            'compact_mode' => 'boolean',
            'auto_summarize' => 'boolean',
            'notify_in_app' => 'boolean',
            'notify_browser' => 'boolean',
            'notify_study_reminders' => 'boolean',
            'notify_deadlines' => 'boolean',
            'notify_engagement' => 'boolean',
            'availability' => 'array',
            'daily_goal_minutes' => 'integer',
            'weekly_goal_minutes' => 'integer',
            'focus_minutes' => 'integer',
            'short_break_minutes' => 'integer',
            'long_break_minutes' => 'integer',
            'sessions_before_long_break' => 'integer',
            'default_reminder_minutes' => 'integer',
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'theme' => 'system',
            'accent_color' => '#6366f1',
            'availability' => config('edusmart.risk.default_availability'),
        ];
    }

    /** Study hours available on a weekday key ("mon" … "sun"). */
    public function hoursOn(string $weekday): float
    {
        $availability = $this->availability ?: config('edusmart.risk.default_availability');

        return (float) ($availability[$weekday] ?? 0);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
