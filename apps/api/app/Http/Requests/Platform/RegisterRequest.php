<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Common Platform — create a student account.
 */
class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'program' => ['nullable', 'string', 'max:160'],
            'university' => ['nullable', 'string', 'max:160'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
