<?php

namespace App\Http\Requests\Study;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * PASINDU — recurring study-session reminder.
 */
class StudyReminderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:500'],
            'remind_time' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date_format:H:i'],
            'days' => [$this->isMethod('post') ? 'required' : 'sometimes', 'array', 'min:1'],
            'days.*' => [Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
