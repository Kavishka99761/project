<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\ApiRequest;
use App\Enums\SummaryLength;
use Illuminate\Validation\Rule;

/**
 * BETHMI — generate a short / medium / detailed summary.
 */
class GenerateSummaryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'length' => ['required', Rule::enum(SummaryLength::class)],
            'save' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:200'],
        ];
    }
}
