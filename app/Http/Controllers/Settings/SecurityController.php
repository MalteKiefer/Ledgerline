<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
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

        return view('settings.security.edit', [
            'rememberDays' => $s->vault_remember_days ?: 7,
            'idleMinutes' => $s->vault_public_idle_minutes ?: 10,
            'maxDevices' => $s->max_connected_devices ?: (int) config('devices.max', 3),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vault_remember_days' => ['required', 'integer', 'min:1', 'max:365'],
            'vault_public_idle_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_connected_devices' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        AppSettings::current()->update($data);

        return $this->savedSettings('security', 'settings.security.edit', 'settings.security_saved');
    }
}
