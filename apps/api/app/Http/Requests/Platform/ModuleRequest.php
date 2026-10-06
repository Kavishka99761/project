<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Common Platform — module registration.
 */
class ModuleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('modules', 'code')->where('user_id', $this->user()->id)->ignore($this->route('module'))],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:48', 'regex:/^[a-z0-9-]+$/'],
            'credits' => ['nullable', 'integer', 'between:0,60'],
            'semester' => ['nullable', 'string', 'max:40'],
            'lecturer' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
