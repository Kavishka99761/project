<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;

/**
 * Common Platform — confirm an email address with the 6-digit code sent at
 * registration. Public: the code itself is the proof, so the student can
 * confirm from the email on any device.
 */
class VerifyEmailRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => preg_replace('/[\s-]/', '', (string) $this->input('code'))]);
        }
    }

    public function messages(): array
    {
        return [
            'code.size' => 'The code is the 6 digits from the email.',
            'code.regex' => 'The code is the 6 digits from the email.',
        ];
    }
}
