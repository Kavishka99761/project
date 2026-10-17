<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Common Platform — reset a forgotten password with the 6-digit code that was
 * emailed by /forgot-password.
 */
class ResetPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /** Codes are typed by hand: forgive spaces and dashes before validating. */
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
