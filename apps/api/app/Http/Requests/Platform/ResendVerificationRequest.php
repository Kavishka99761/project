<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;

/**
 * Common Platform — send the email verification code again.
 */
class ResendVerificationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
