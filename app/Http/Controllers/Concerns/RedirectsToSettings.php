<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * Shared tail for a Settings controller's save action: redirect back to the
 * section's edit page with a localised "saved" flash message.
 */
trait RedirectsToSettings
{
    protected function savedRedirect(string $route, string $flashKey): RedirectResponse
    {
        return redirect()->route($route)->with('status', __($flashKey));
    }
}
