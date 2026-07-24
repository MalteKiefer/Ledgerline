<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Global per-user DISPLAY preferences: measurement units + 12/24h clock. These are
 * non-secret presentation choices (like the interface language) — the underlying
 * data stays zero-knowledge; only the unit/format it is shown in is chosen here.
 * Applied client-side across web (window.LLPrefs) and mobile (GET /me.preferences).
 */
class PreferencesController extends Controller
{
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'distance' => ['sometimes', 'string', 'in:km,mi'],
            'elevation' => ['sometimes', 'string', 'in:m,ft'],
            'weight' => ['sometimes', 'string', 'in:kg,lb'],
            'temp' => ['sometimes', 'string', 'in:c,f'],
            'glucose' => ['sometimes', 'string', 'in:mgdl,mmoll'],
            'time_format' => ['sometimes', 'string', 'in:24h,12h'],
        ]);

        $map = [
            'distance' => 'unit_distance',
            'elevation' => 'unit_elevation',
            'weight' => 'unit_weight',
            'temp' => 'unit_temp',
            'glucose' => 'unit_glucose',
            'time_format' => 'time_format',
        ];
        $update = [];
        foreach ($map as $key => $column) {
            if ($request->has($key)) {
                $update[$column] = $request->string($key)->value();
            }
        }

        $setting = UserSetting::for($this->requireUser($request)->id);
        if ($update !== []) {
            $setting->update($update);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'preferences' => $setting->displayPrefs()])
            : back();
    }
}
