<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Rules\SafeUrl;
use App\Services\Backup\BackupNotifier;
use App\Support\CheckboxFlags;
use App\Support\KeepBlankSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * JSON mirror of the web Settings/NotificationsController (admin): outgoing mail
 * (SMTP), NTFY and webhook config on the single AppSettings row, plus a test
 * send. Secret fields (password/token/webhook_secret) are NEVER returned — the
 * GET exposes has_* booleans instead, and a blank submission preserves the
 * stored secret (KeepBlankSecrets). Admin-gated at the route level.
 */
class NotificationsController extends Controller
{
    /** Current notification config with secrets masked. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload());
    }

    /** Update; mirrors the web controller's validation + blank-preserve semantics. */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
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

        // Persist only the fields that were actually submitted (mirrors the web
        // controller); the boolean flags are applied below.
        $fields = [
            'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
            'smtp_password', 'smtp_from_address', 'smtp_from_name',
            'ntfy_url', 'ntfy_topic', 'ntfy_token', 'webhook_url', 'webhook_secret',
        ];
        $data = [];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        $settings = AppSettings::current();

        // Checkboxes: absent means off.
        $data = CheckboxFlags::apply($data, $request, ['mail_enabled', 'ntfy_enabled', 'webhook_enabled']);

        // Secret fields: a blank submission keeps the stored value (never wiped
        // by accident — the API never returns the secret to be re-sent).
        $data = KeepBlankSecrets::preserve($data, ['smtp_password', 'ntfy_token', 'webhook_secret']);

        $settings->update($data);

        AuditLog::record('settings.updated', null, ['group' => 'notifications']);

        return response()->json($this->payload());
    }

    /** Send a test message over one channel using the saved settings. */
    public function test(Request $request, BackupNotifier $notifier): JsonResponse
    {
        $request->validate([
            'channel' => ['required', Rule::in(['mail', 'ntfy', 'webhook'])],
        ]);
        $channel = $request->string('channel')->value();

        // Unified "test" convention: a connection FAILURE is a functional result,
        // not an HTTP error — return 200 {ok:false, detail} (matches
        // backup/destinations/test). Only request VALIDATION (bad channel) is 422.
        try {
            $notifier->test($channel);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'detail' => Str::limit($e->getMessage(), 200),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Non-secret notification config. Secret values (smtp_password / ntfy_token /
     * webhook_secret) are represented only by has_* booleans.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $s = AppSettings::current();

        return [
            'mail_enabled' => (bool) $s->mail_enabled,
            'smtp_host' => $s->smtp_host,
            'smtp_port' => $s->smtp_port,
            'smtp_encryption' => $s->smtp_encryption,
            'smtp_username' => $s->smtp_username,
            'smtp_from_address' => $s->smtp_from_address,
            'smtp_from_name' => $s->smtp_from_name,
            'has_smtp_password' => filled($s->smtp_password),
            'ntfy_enabled' => (bool) $s->ntfy_enabled,
            'ntfy_url' => $s->ntfy_url,
            'ntfy_topic' => $s->ntfy_topic,
            'has_ntfy_token' => filled($s->ntfy_token),
            'webhook_enabled' => (bool) $s->webhook_enabled,
            'webhook_url' => $s->webhook_url,
            'has_webhook_secret' => filled($s->webhook_secret),
        ];
    }
}
