<?php

namespace App\Http\Requests\Assignments;

use App\Http\Requests\ApiRequest;
/**
 * JITHMI — record assignment progress (percentage and/or hours).
 */
class ProgressRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'progress' => ['nullable', 'integer', 'between:0,100', 'required_without:hours_added'],
            'hours_added' => ['nullable', 'numeric', 'between:0,24', 'required_without:progress'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
