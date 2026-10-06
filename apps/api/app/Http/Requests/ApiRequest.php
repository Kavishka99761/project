<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Base form request: authorisation is handled by authentication + scoped
 * route-model binding, and IDs referenced in a payload must belong to the
 * signed-in student (owned()).
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** `exists` rule restricted to the student's own rows. */
    protected function owned(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('user_id', $this->user()?->id ?? 0);
    }
}
