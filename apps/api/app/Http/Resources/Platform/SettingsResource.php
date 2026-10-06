<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserSetting */
class SettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'theme' => $this->theme,
            'accent_color' => $this->accent_color,
            'glass_effects' => $this->glass_effects,
            'reduce_motion' => $this->reduce_motion,
            'compact_mode' => $this->compact_mode,
            'daily_goal_minutes' => $this->daily_goal_minutes,
            'weekly_goal_minutes' => $this->weekly_goal_minutes,
            'focus_minutes' => $this->focus_minutes,
            'short_break_minutes' => $this->short_break_minutes,
            'long_break_minutes' => $this->long_break_minutes,
            'sessions_before_long_break' => $this->sessions_before_long_break,
            'availability' => $this->availability ?: config('edusmart.risk.default_availability'),
            'auto_summarize' => $this->auto_summarize,
            'default_summary_length' => $this->default_summary_length,
            'notify_in_app' => $this->notify_in_app,
            'notify_browser' => $this->notify_browser,
            'notify_study_reminders' => $this->notify_study_reminders,
            'notify_deadlines' => $this->notify_deadlines,
            'notify_engagement' => $this->notify_engagement,
            'default_reminder_minutes' => $this->default_reminder_minutes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
