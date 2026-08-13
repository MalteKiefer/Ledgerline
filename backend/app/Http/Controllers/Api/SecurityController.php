<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON mirror of the web Settings/SecurityController (admin): the workspace
 * paired-device cap (max_connected_devices) on the single AppSettings row.
 * Admin-gated at the route level. Audits the exact diff, like the web controller.
 */
class SecurityController extends Controller
{
    /** Current device policy. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'max_connected_devices' => ['required', 'integer', 'min:1', 'max:100'],
            'pw_min_length' => ['sometimes', 'nullable', 'integer', 'min:8', 'max:128'],
            'pw_require_mixed_case' => ['sometimes', 'boolean'],
            'pw_require_numbers' => ['sometimes', 'boolean'],
            'pw_require_symbols' => ['sometimes', 'boolean'],
            'pw_check_breaches' => ['sometimes', 'boolean'],
            'force_2fa' => ['sometimes', 'boolean'],
        ]);

        $settings = AppSettings::current();
        $before = $this->payload();
        $after = ['max_connected_devices' => $request->integer('max_connected_devices')];
        foreach (['pw_require_mixed_case', 'pw_require_numbers', 'pw_require_symbols', 'pw_check_breaches', 'force_2fa'] as $b) {
            if ($request->has($b)) {
                $after[$b] = $request->boolean($b);
            }
        }
        if ($request->has('pw_min_length')) {
            $after['pw_min_length'] = $request->input('pw_min_length') === null ? null : $request->integer('pw_min_length');
        }
        $settings->update($after);

        // Audit the exact security-policy diff (values, never secrets) so a change
        // to the device cap is attributable and reversible.
        $changes = [];
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }
        if ($changes !== []) {
            AuditLog::record('settings.security_changed', null, ['changes' => $changes]);
        }

        return response()->json($this->payload());
    }

    /** @return array{max_connected_devices:int, pw_min_length:?int, pw_require_mixed_case:bool, pw_require_numbers:bool, pw_require_symbols:bool, pw_check_breaches:bool, force_2fa:bool} */
    private function payload(): array
    {
        $s = AppSettings::current();
        $default = config('devices.max', 3);

        return [
            'max_connected_devices' => $s->max_connected_devices ?: (is_numeric($default) ? (int) $default : 3),
            'pw_min_length' => $s->pw_min_length,
            'pw_require_mixed_case' => (bool) $s->pw_require_mixed_case,
            'pw_require_numbers' => (bool) $s->pw_require_numbers,
            'pw_require_symbols' => (bool) $s->pw_require_symbols,
            'pw_check_breaches' => (bool) $s->pw_check_breaches,
            'force_2fa' => (bool) $s->force_2fa,
        ];
    }
}
