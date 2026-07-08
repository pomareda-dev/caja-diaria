<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use Illuminate\Http\Response;

class PreferencesController extends Controller
{
    /**
     * Update the user's settings (theme, density, etc.).
     */
    public function update(UpdateSettingsRequest $request): Response
    {
        $user = $request->user();
        $validated = $request->validated();

        $settings = array_merge($user->settings ?? [], $validated);
        $user->settings = $settings;
        $user->save();

        return response()->noContent();
    }
}
