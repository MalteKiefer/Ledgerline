<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Workspace device policy (admin): the maximum number of paired mobile/CLI
 * devices per user. (The old zero-knowledge vault-lock settings were removed
 * with the ZK pivot — the app has no client vault to keep unlocked anymore.)
 */
class SecurityController extends Controller
{
    use RedirectsToSettings;

    public function edit(): View
    {
        $s = AppSettings::current();
        $defaultMax = config('devices.max', 3);

        return view('settings.security.edit', [
            'maxDevices' => $s->max_connected_devices ?: (is_numeric($defaultMax) ? (int) $defaultMax : 3),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'max_connected_devices' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $settings = AppSettings::current();
        $before = ['max_connected_devices' => $settings->max_connected_devices];
        $after = ['max_connected_devices' => $request->integer('max_connected_devices')];
        $settings->update($after);

        // Audit the exact security-policy diff (values, never secrets) so a change
        // to the device cap / vault-lock policy is attributable and reversible.
        $changes = [];
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }
        if ($changes !== []) {
            AuditLog::record('settings.security_changed', null, ['changes' => $changes]);
        }

        return $this->savedSettings('security', 'settings.security.edit', 'settings.security_saved');
    }
}
