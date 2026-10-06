<?php

namespace App\Http\Requests\Study;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * PASINDU — stop a session with an optional reflection.
 */
class StopSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'mood' => ['nullable', Rule::in(['great', 'good', 'okay', 'tired', 'stressed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
