<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\ApiRequest;
/**
 * BETHMI — upload lecture notes / PDF / Word / slides.
 */
class UploadDocumentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('edusmart.uploads.max_kb'), 'extensions:'.implode(',', config('edusmart.uploads.learning_extensions'))],
            'title' => ['nullable', 'string', 'max:200'],
            'topic' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['nullable', 'integer', $this->owned('modules')],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload a PDF, Word (.docx/.doc), PowerPoint (.pptx), text or Markdown file.',
            'file.max' => 'The file is too large (max '.(int) (config('edusmart.uploads.max_kb') / 1024).' MB).',
        ];
    }
}
