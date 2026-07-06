<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailIdentity;
use App\Models\MailMessage;
use App\Models\MailSignature;
use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapReader;
use App\Services\Mail\SmtpSender;
use App\Support\BlobStore;
use App\Support\KeepBlankSecrets;
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
            'accounts' => MailAccount::with('identities')->orderBy('name')->get()->map(fn (MailAccount $a) => $this->toArray($a)),
            'signatures' => MailSignature::orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (MailSignature $x) => ['id' => $x->id, 'name' => $x->name, 'html' => $x->html, 'isDefault' => $x->is_default])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, requirePassword: true);
        $account = MailAccount::create($data);

        // Seed the default identity from the account's sending fields, matching
        // the data migration so every account always has at least one identity.
        $account->identities()->create([
            'from_name' => $account->from_name,
            'from_email' => $account->username,
            'reply_to' => $account->reply_to,
            'signature' => $account->signature,
            'is_default' => true,
        ]);

        return response()->json($this->toArray($account->load('identities')), 201);
    }

    public function update(Request $request, MailAccount $account): JsonResponse
    {
        $data = $this->validated($request, requirePassword: false);
        // Empty password/SMTP password keeps the stored one (so it need not be retyped).
        $data = KeepBlankSecrets::preserve($data, ['password', 'smtp_password']);
        $account->update($data);

        return response()->json($this->toArray($account->load('identities')));
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

    /**
     * Test the IMAP (and, when configured, SMTP) connection for the submitted
     * account details without saving. When editing an existing account, blank
     * password fields fall back to the stored ones so the user need not retype.
     */
    public function test(Request $request, ImapReader $reader, SmtpSender $smtp): JsonResponse
    {
        $data = $this->validated($request, requirePassword: false);

        // Blank password fields reuse the stored ones of the edited account.
        if (filled($request->input('account_id'))) {
            $existing = MailAccount::withoutGlobalScopes()->find($request->input('account_id'));
            if ($existing !== null && (int) $existing->user_id === (int) $request->user()->id) {
                if (empty($data['password'])) {
                    $data['password'] = (string) $existing->password;
                }
                if (empty($data['smtp_password'])) {
                    $data['smtp_password'] = (string) $existing->smtp_password;
                }
            }
        }

        // IMAP.
        $imap = ['ok' => false, 'error' => null];
        try {
            $reader->listFolders(new ImapCredentials(
                host: $data['host'], port: (int) $data['port'], encryption: $data['encryption'],
                username: $data['username'], password: (string) ($data['password'] ?? ''),
                validateCert: (bool) ($data['validate_cert'] ?? true),
            ));
            $imap['ok'] = true;
        } catch (\Throwable $e) {
            $imap['error'] = $this->reason($e);
        }

        // SMTP — only when a sending host/credentials resolve (via IMAP fallback).
        $account = (new MailAccount)->forceFill($data);
        $smtpResult = ['ok' => false, 'error' => null, 'configured' => $account->smtpConfigured()];
        if ($smtpResult['configured']) {
            try {
                $smtp->verify($account->smtpConfig());
                $smtpResult['ok'] = true;
            } catch (\Throwable $e) {
                $smtpResult['error'] = $this->reason($e);
            }
        }

        return response()->json(['imap' => $imap, 'smtp' => $smtpResult]);
    }

    /** The server's own message (chained cause), trimmed for display. */
    private function reason(\Throwable $e): string
    {
        $reason = trim(($e->getPrevious() ?? $e)->getMessage());
        $reason = preg_replace('/\s+/', ' ', $reason) ?? $reason;

        return mb_substr($reason, 0, 200);
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
            'smtpConfigured' => $a->smtpConfigured(),
            'identities' => $a->identities->map(fn (MailIdentity $i) => [
                'id' => $i->id,
                'fromName' => $i->from_name,
                'fromEmail' => $i->from_email,
                'replyTo' => $i->reply_to,
                'signature' => $i->signature,
                'signatureId' => $i->signature_id,
                'isDefault' => $i->is_default,
            ])->values(),
        ];
    }
}
