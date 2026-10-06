<?php

namespace App\Http\Requests\Assignments;

use App\Http\Requests\ApiRequest;
/**
 * JITHMI — hypothetical scenario for the risk simulator.
 */
class WhatIfRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', $this->owned('assignments')],
            'deadline' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'between:0.5,500'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'extra_daily_hours' => ['nullable', 'numeric', 'between:-8,12'],
        ];
    }
}
