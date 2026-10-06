<?php

namespace App\Http\Requests\Assignments;

use App\Http\Requests\ApiRequest;
/**
 * JITHMI — mark an assignment as completed / submitted.
 */
class CompleteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'submitted_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
