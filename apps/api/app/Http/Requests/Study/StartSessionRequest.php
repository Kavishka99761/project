<?php

namespace App\Http\Requests\Study;

use App\Http\Requests\ApiRequest;
use App\Enums\StudyActivity;
use Illuminate\Validation\Rule;

/**
 * PASINDU — start a study session (module, activity, planned time).
 */
class StartSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
            'activity' => ['required', Rule::enum(StudyActivity::class)],
            'planned_minutes' => ['required', 'integer', 'between:5,'.config('edusmart.study.max_session_minutes')],
            'goal' => ['nullable', 'string', 'max:255'],
            'assignment_id' => ['nullable', 'integer', $this->owned('assignments')],
            'document_id' => ['nullable', 'integer', $this->owned('documents')],
        ];
    }
}
