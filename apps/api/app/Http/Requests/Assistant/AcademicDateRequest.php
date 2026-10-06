<?php

namespace App\Http\Requests\Assistant;

use App\Http\Requests\ApiRequest;
use App\Enums\AcademicDateType;
use Illuminate\Validation\Rule;

/**
 * KAVISHKA — review / correct an extracted academic date.
 */
class AcademicDateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'date' => ['sometimes', 'required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'type' => ['sometimes', Rule::enum(AcademicDateType::class)],
        ];
    }
}
