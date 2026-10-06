<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\ApiRequest;
/**
 * Common Platform — sign in.
 */
class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
