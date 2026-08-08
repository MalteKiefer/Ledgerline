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
        ]);

        $settings = AppSettings::current();
        $before = ['max_connected_devices' => $settings->max_connected_devices];
        $after = ['max_connected_devices' => $request->integer('max_connected_devices')];
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

    /** @return array{max_connected_devices: int} */
    private function payload(): array
    {
        $s = AppSettings::current();
        $default = config('devices.max', 3);

        return [
            'max_connected_devices' => $s->max_connected_devices ?: (is_numeric($default) ? (int) $default : 3),
        ];
    }
}
