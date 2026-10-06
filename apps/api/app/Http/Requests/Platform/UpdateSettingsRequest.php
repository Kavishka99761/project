<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use App\Enums\SummaryLength;
use Illuminate\Validation\Rule;

/**
 * Common UI & services — preferences, goals, availability, notifications.
 */
class UpdateSettingsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'accent_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'glass_effects' => ['sometimes', 'boolean'],
            'reduce_motion' => ['sometimes', 'boolean'],
            'compact_mode' => ['sometimes', 'boolean'],
            'daily_goal_minutes' => ['sometimes', 'integer', 'between:15,960'],
            'weekly_goal_minutes' => ['sometimes', 'integer', 'between:60,5000'],
            'focus_minutes' => ['sometimes', 'integer', 'between:10,120'],
            'short_break_minutes' => ['sometimes', 'integer', 'between:1,30'],
            'long_break_minutes' => ['sometimes', 'integer', 'between:5,60'],
            'sessions_before_long_break' => ['sometimes', 'integer', 'between:2,8'],
            'availability' => ['sometimes', 'array'],
            'availability.mon' => ['required_with:availability', 'numeric', 'between:0,16'],
            'availability.tue' => ['required_with:availability', 'numeric', 'between:0,16'],
            'availability.wed' => ['required_with:availability', 'numeric', 'between:0,16'],
            'availability.thu' => ['required_with:availability', 'numeric', 'between:0,16'],
            'availability.fri' => ['required_with:availability', 'numeric', 'between:0,16'],
            'availability.sat' => ['required_with:availability', 'numeric', 'between:0,16'],
            'availability.sun' => ['required_with:availability', 'numeric', 'between:0,16'],
            'auto_summarize' => ['sometimes', 'boolean'],
            'default_summary_length' => ['sometimes', Rule::enum(SummaryLength::class)],
            'notify_in_app' => ['sometimes', 'boolean'],
            'notify_browser' => ['sometimes', 'boolean'],
            'notify_study_reminders' => ['sometimes', 'boolean'],
            'notify_deadlines' => ['sometimes', 'boolean'],
            'notify_engagement' => ['sometimes', 'boolean'],
            'default_reminder_minutes' => ['sometimes', 'integer', 'between:0,20160'],
        ];
    }
}
