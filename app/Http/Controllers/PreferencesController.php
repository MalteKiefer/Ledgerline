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
            'mail_remote' => ['sometimes', 'boolean'],
            'mail_scripts' => ['sometimes', 'boolean'],
            'cal_week_numbers' => ['sometimes', 'boolean'],
            'cal_week_start' => ['sometimes', 'string', 'in:mon,sun'],
            'cal_default_view' => ['sometimes', 'string', 'in:month,week,day'],
            'cal_day_start' => ['sometimes', 'integer', 'min:0', 'max:23'],
            'cal_day_end' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ]);

        $map = [
            'distance' => 'unit_distance',
            'elevation' => 'unit_elevation',
            'weight' => 'unit_weight',
            'temp' => 'unit_temp',
            'glucose' => 'unit_glucose',
            'time_format' => 'time_format',
            'cal_week_start' => 'cal_week_start',
            'cal_default_view' => 'cal_default_view',
        ];
        $update = [];
        foreach ($map as $key => $column) {
            if ($request->has($key)) {
                $update[$column] = $request->string($key)->value();
            }
        }
        foreach (['mail_remote' => 'mail_load_remote', 'mail_scripts' => 'mail_allow_scripts', 'cal_week_numbers' => 'cal_week_numbers'] as $key => $column) {
            if ($request->has($key)) {
                $update[$column] = $request->boolean($key);
            }
        }
        foreach (['cal_day_start' => 'cal_day_start', 'cal_day_end' => 'cal_day_end'] as $key => $column) {
            if ($request->has($key)) {
                $update[$column] = $request->integer($key);
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
