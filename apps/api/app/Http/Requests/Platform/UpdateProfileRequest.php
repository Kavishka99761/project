<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Common Platform — basic profile information.
 */
class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:160', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'student_id' => ['nullable', 'string', 'max:40'],
            'university' => ['nullable', 'string', 'max:160'],
            'program' => ['nullable', 'string', 'max:160'],
            'academic_year' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]{6,30}$/'],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }
}
