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
 * Downloads center settings: the maximum size of a single export zip part
 * (larger exports split into parts) for files and gallery, and which channels
 * announce a finished export. The channels reuse the global NTFY/mail/webhook
 * credentials configured under Notifications.
 */
class DownloadsController extends Controller
{
    use RedirectsToSettings;

    public function edit(): View
    {
        return view('settings.downloads.edit', ['settings' => AppSettings::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'export_files_max_zip_mb' => ['required', 'integer', 'min:0', 'max:1048576'],
            'export_gallery_max_zip_mb' => ['required', 'integer', 'min:0', 'max:1048576'],
            'export_notify_desktop' => ['sometimes', 'boolean'],
            'export_notify_ntfy' => ['sometimes', 'boolean'],
            'export_notify_mail' => ['sometimes', 'boolean'],
            'export_notify_webhook' => ['sometimes', 'boolean'],
        ]);

        foreach (['export_notify_desktop', 'export_notify_ntfy', 'export_notify_mail', 'export_notify_webhook'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        AppSettings::current()->update($data);

        return $this->savedRedirect('settings.downloads.edit', 'flash.downloads_saved');
    }
}
