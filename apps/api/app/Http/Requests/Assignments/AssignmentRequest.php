<?php

namespace App\Http\Requests\Assignments;

use App\Http\Requests\ApiRequest;
use App\Enums\AssignmentPriority;
use Illuminate\Validation\Rule;

/**
 * JITHMI — add / edit an assignment (deadline, priority, workload).
 */
class AssignmentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['sometimes', Rule::in(['coursework', 'project', 'lab', 'report', 'presentation', 'exam_prep', 'quiz', 'other'])],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
            'deadline' => [$this->isMethod('post') ? 'required' : 'sometimes', 'date'],
            'priority' => ['sometimes', Rule::enum(AssignmentPriority::class)],
            'weight_percent' => ['nullable', 'numeric', 'between:0,100'],
            'estimated_hours' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'between:0.5,500'],
            'progress' => ['sometimes', 'integer', 'between:0,99'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'academic_date_id' => ['nullable', 'integer', $this->owned('academic_dates')],
        ];
    }
}
