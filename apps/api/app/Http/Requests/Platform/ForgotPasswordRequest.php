<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
/**
 * Common Platform — request a password reset link.
 */
class ForgotPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
