<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\ApiRequest;
use App\Enums\KnowledgeCategory;
use Illuminate\Validation\Rule;

/**
 * KAVISHKA — add a module handbook, project guideline, regulation or module document.
 */
class UploadKnowledgeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('edusmart.uploads.max_kb'), 'extensions:'.implode(',', config('edusmart.uploads.knowledge_extensions'))],
            'category' => ['required', Rule::enum(KnowledgeCategory::class)],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
        ];
    }

    public function messages(): array
    {
        return ['file.extensions' => 'Upload a PDF, Word, PowerPoint, text or Markdown document.'];
    }
}
