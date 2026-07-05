<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailMessage;
use App\Support\BlobStore;
use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mail accounts as plain rows (the login password is encrypted at rest). Served
 * as a JSON API for the client; the password is never returned to the browser.
 */
class MailAccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'accounts' => MailAccount::orderBy('name')->get()->map(fn (MailAccount $a) => $this->toArray($a)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, requirePassword: true);
        $account = MailAccount::create($data);

        return response()->json($this->toArray($account), 201);
    }

    public function update(Request $request, MailAccount $account): JsonResponse
    {
        $data = $this->validated($request, requirePassword: false);
        // Empty password keeps the stored one (so it need not be retyped).
        if (empty($data['password'])) {
            unset($data['password']);
        }
        // Same for the SMTP password: an empty value keeps the stored one.
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }
        $account->update($data);

        return response()->json($this->toArray($account->refresh()));
    }

    /**
     * Delete a mail account. By default its local archive (stored .eml blobs +
     * rows) is deleted too; pass keep_archive=1 to retain the archive.
     */
    public function destroy(Request $request, MailAccount $account): JsonResponse
    {
        if (! $request->boolean('keep_archive')) {
            $disk = BlobStore::disk();
            MailMessage::withoutGlobalScopes()->where('mail_account_id', $account->id)
                ->whereNotNull('blob')->orderBy('id')->chunkById(500, function ($chunk) use ($disk): void {
                    foreach ($chunk as $m) {
                        if (is_string($m->blob) && $m->blob !== '') {
                            $disk->delete('mail/'.$m->blob);
                        }
                    }
                });
            MailMessage::withoutGlobalScopes()->where('mail_account_id', $account->id)->delete();
            MailFolder::withoutGlobalScopes()->where('mail_account_id', $account->id)->delete();
        }

        $account->delete();

        return response()->json(['ok' => true]);
    }

    /** Reject IMAP/SMTP hosts resolving to the cloud-metadata/link-local range. */
    private function hostRule(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            if (filled($value) && ! OutboundUrl::hostAllowed((string) $value)) {
                $fail(__('mail.host_not_allowed'));
            }
        };
    }

    private function validated(Request $request, bool $requirePassword): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255', $this->hostRule()],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(['ssl', 'starttls'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$requirePassword ? 'required' : 'nullable', 'string', 'max:1024'],
            'validate_cert' => ['sometimes', 'boolean'],
            'smtp_host' => ['sometimes', 'nullable', 'string', 'max:255', $this->hostRule()],
            'smtp_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['sometimes', 'nullable', Rule::in(['ssl', 'starttls', 'none'])],
            'smtp_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_password' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reply_to' => ['sometimes', 'nullable', 'email'],
            'signature' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
    }

    /** Public shape — never includes the password. */
    private function toArray(MailAccount $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'host' => $a->host,
            'port' => $a->port,
            'encryption' => $a->encryption,
            'username' => $a->username,
            'validateCert' => $a->validate_cert,
            'lastSyncedAt' => $a->last_synced_at?->toIso8601String(),
            'smtpHost' => $a->smtp_host,
            'smtpPort' => $a->smtp_port,
            'smtpEncryption' => $a->smtp_encryption,
            'smtpUsername' => $a->smtp_username,
            'fromName' => $a->from_name,
            'replyTo' => $a->reply_to,
            'signature' => $a->signature,
            'hasSmtpPassword' => $a->smtp_password !== null && $a->smtp_password !== '',
        ];
    }
}
