<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Rules\SafeUrl;
use App\Services\Backup\BackupNotifier;
use App\Support\CheckboxFlags;
use App\Support\KeepBlankSecrets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Outgoing mail (SMTP), NTFY and webhook settings — the channels backups notify
 * through. Credentials are stored encrypted at rest but used in the clear.
 */
class NotificationsController extends Controller
{
    use RedirectsToSettings;

    public function edit(): View
    {
        return view('settings.notifications.edit', ['settings' => AppSettings::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_enabled' => ['sometimes', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'ntfy_enabled' => ['sometimes', 'boolean'],
            'ntfy_url' => ['nullable', 'url', 'max:255', new SafeUrl],
            'ntfy_topic' => ['nullable', 'string', 'max:255'],
            'ntfy_token' => ['nullable', 'string', 'max:255'],
            'webhook_enabled' => ['sometimes', 'boolean'],
            'webhook_url' => ['nullable', 'url', 'max:255', new SafeUrl],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = AppSettings::current();

        // Checkboxes: absent means off.
        $data = CheckboxFlags::apply($data, $request, ['mail_enabled', 'ntfy_enabled', 'webhook_enabled']);

        // Secret fields: an empty submission keeps the stored value (the form
        // never renders the current secret back), so it isn't wiped by accident.
        $data = KeepBlankSecrets::preserve($data, ['smtp_password', 'ntfy_token', 'webhook_secret']);

        $settings->update($data);

        AuditLog::record('settings.updated', null, ['group' => 'notifications']);

        return $this->savedRedirect('settings.notifications.edit', 'flash.notifications_saved');
    }

    /**
     * Send a test message over one channel using the saved settings, so the
     * operator can confirm delivery before relying on it for backups.
     */
    public function test(Request $request, BackupNotifier $notifier): RedirectResponse
    {
        $channel = $request->validate([
            'channel' => ['required', Rule::in(['mail', 'ntfy', 'webhook'])],
        ])['channel'];

        try {
            $notifier->test($channel);
        } catch (\Throwable $e) {
            return back()->with('error', __('flash.notify_test_failed', ['error' => Str::limit($e->getMessage(), 200)]));
        }

        return back()->with('status', __('flash.notify_test_sent'));
    }
}
