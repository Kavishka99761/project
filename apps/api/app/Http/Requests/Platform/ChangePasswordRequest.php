<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Common Platform — change password while signed in.
 */
class ChangePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }
}
