<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Common Platform — reset a forgotten password with the emailed token.
 */
class ResetPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
