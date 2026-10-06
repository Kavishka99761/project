<?php

namespace App\Http\Requests\Study;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * PASINDU — automatic engagement sample or manual concentration report.
 */
class EngagementRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['auto', 'manual'])],
            'concentration' => ['required_if:source,manual', 'nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:255'],
            'signals' => ['required_if:source,auto', 'nullable', 'array'],
            'signals.window_seconds' => ['nullable', 'integer', 'between:5,900'],
            'signals.focus_ratio' => ['nullable', 'numeric', 'between:0,1'],
            'signals.visible_ratio' => ['nullable', 'numeric', 'between:0,1'],
            'signals.interactions' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'signals.idle_seconds' => ['nullable', 'integer', 'min:0', 'max:900'],
            'signals.tab_switches' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
