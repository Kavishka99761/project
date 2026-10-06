<?php

namespace App\Http\Requests\Study;

use App\Http\Requests\ApiRequest;
/**
 * PASINDU — planned study time for a day.
 */
class StudyPlanRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'plan_date' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'planned_minutes' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer', 'between:5,960'],
            'title' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
            'assignment_id' => ['nullable', 'integer', $this->owned('assignments')],
            'is_done' => ['sometimes', 'boolean'],
        ];
    }
}
