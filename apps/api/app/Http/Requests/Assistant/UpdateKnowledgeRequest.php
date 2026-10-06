<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\ApiRequest;
use App\Enums\KnowledgeCategory;
use Illuminate\Validation\Rule;

/**
 * KAVISHKA — rename / recategorise a knowledge document.
 */
class UpdateKnowledgeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', Rule::enum(KnowledgeCategory::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
        ];
    }
}
