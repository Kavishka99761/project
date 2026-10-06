<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\ApiRequest;
/**
 * BETHMI — rename / organise by module and topic / edit note text.
 */
class UpdateDocumentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'topic' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
            'is_favorite' => ['sometimes', 'boolean'],
            'content' => ['sometimes', 'string', 'min:20', 'max:500000'],
        ];
    }
}
