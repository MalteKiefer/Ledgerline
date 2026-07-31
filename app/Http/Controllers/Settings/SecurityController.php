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
 * Workspace vault lock policy (admin): how long a trusted device stays unlocked
 * across browser restarts, and the idle timeout for a public-computer unlock.
 */
class SecurityController extends Controller
{
    use RedirectsToSettings;

    public function edit(): View
    {
        $s = AppSettings::current();
        $defaultMax = config('devices.max', 3);

        return view('settings.security.edit', [
            'rememberDays' => $s->vault_remember_days ?: 7,
            'idleMinutes' => $s->vault_public_idle_minutes ?: 10,
            'maxDevices' => $s->max_connected_devices ?: (is_numeric($defaultMax) ? (int) $defaultMax : 3),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'vault_remember_days' => ['required', 'integer', 'min:1', 'max:365'],
            'vault_public_idle_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_connected_devices' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $settings = AppSettings::current();
        $before = [
            'vault_remember_days' => $settings->vault_remember_days,
            'vault_public_idle_minutes' => $settings->vault_public_idle_minutes,
            'max_connected_devices' => $settings->max_connected_devices,
        ];
        $after = [
            'vault_remember_days' => $request->integer('vault_remember_days'),
            'vault_public_idle_minutes' => $request->integer('vault_public_idle_minutes'),
            'max_connected_devices' => $request->integer('max_connected_devices'),
        ];
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
