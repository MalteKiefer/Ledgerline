<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;

/**
 * Shared tail for a Settings controller's save action: record the audit event
 * and redirect back to the section's edit page with a localised "saved" flash.
 */
trait RedirectsToSettings
{
    protected function savedRedirect(string $route, string $flashKey): RedirectResponse
    {
        return redirect()->route($route)->with('status', __($flashKey));
    }

    /**
     * Record settings.updated for $section, then redirect to the edit page.
     * Combines the audit call + redirect so controllers can't forget the audit.
     */
    protected function savedSettings(string $section, string $route, string $flashKey): RedirectResponse
    {
        AuditLog::record('settings.updated', null, ['group' => $section]);

        return $this->savedRedirect($route, $flashKey);
    }
}
