<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateSettingsRequest;
use App\Http\Resources\Platform\SettingsResource;
use App\Services\Assignments\RiskService;
use Illuminate\Http\Request;

/**
 * Common UI — preferences: theme (dark/light/system), accent colour, glass
 * effects, motion, goals, focus rhythm, weekly availability and
 * notification switches. Availability changes re-run the risk engine.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly RiskService $risk) {}

    public function show(Request $request): SettingsResource
    {
        return SettingsResource::make($request->user()->settingsOrDefault());
    }

    public function update(UpdateSettingsRequest $request): SettingsResource
    {
        $user = $request->user();
        $settings = $user->settingsOrDefault();
        $settings->update($request->validated());

        $changed = array_keys($settings->getChanges());
        activity()->action('settings.updated')->describe('Updated settings: '.implode(', ', array_diff($changed, ['updated_at'])))->on($settings);

        if (in_array('availability', $changed, true)) {
            $this->risk->recalculate($user, 'availability_changed');
        }

        return SettingsResource::make($settings->refresh());
    }
}
