<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\ApiRequest;
/**
 * BETHMI — write or paste lecture notes directly.
 */
class CreateNoteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'min:20', 'max:500000'],
            'topic' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
        ];
    }
}
