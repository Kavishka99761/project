<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\ApiRequest;
/**
 * BETHMI — rename / edit a saved summary.
 */
class UpdateSummaryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'content' => ['sometimes', 'required', 'string', 'max:200000'],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
