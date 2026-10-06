<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use App\Enums\EventType;
use Illuminate\Validation\Rule;

/**
 * Calendar API — create or edit an event (with reminder).
 */
class CalendarEventRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::enum(EventType::class)],
            'starts_at' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['sometimes', 'boolean'],
            'location' => ['nullable', 'string', 'max:200'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
            'reminder_minutes' => ['nullable', 'integer', 'between:0,40320'],
        ];
    }
}
