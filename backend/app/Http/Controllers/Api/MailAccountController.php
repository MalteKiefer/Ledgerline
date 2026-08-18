<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use App\Rules\SafeHost;
use App\Support\KeepBlankSecrets;
use App\Support\Mail\ImapProbe;
use App\Support\Mail\MailAutoconfig;
use App\Support\Mail\SmtpProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Owner-scoped CRUD + on-demand sync/status/test for the mail archive's IMAP
 * account configuration. The account password is the one plaintext secret the
 * server holds to run the IMAP connection (`encrypted` cast at rest, see
 * MailAccount); it is NEVER present in any response from this controller (see
 * `present()` — the password key is simply never added, and the model hides it
 * from array/JSON serialization as defence in depth).
 *
 * Ownership uses implicit route-model binding + an explicit `authorizeOwner`
 * 404 (never a 403 — no existence leak).
 */
class MailAccountController extends Controller
{
    /**
     * Discover non-secret IMAP/SMTP settings from an email domain. This is
     * deliberately separate from account creation: discovery never receives a
     * password and the user always reviews the returned settings first.
     */
    public function autoconfig(Request $request, MailAutoconfig $autoconfig): JsonResponse
    {
        $email = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ])->validate()['email'];

        return response()->json(['configuration' => $autoconfig->discover($email)])
            ->header('Cache-Control', 'no-store');
    }

    /** List the caller's mail accounts, each with its archived-message count. */
    public function index(Request $request): JsonResponse
    {
        $accounts = MailAccount::query()
            ->ownedBy($this->requireUser($request)->id)
            ->withCount('messages')
            ->orderBy('name')
            ->get();

        return response()->json([
            'accounts' => $accounts->map(fn (MailAccount $a): array => $this->present($a))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, isUpdate: false);

        // $data is built explicitly in validated() and never includes 'user_id'
        // — AssignsOwner stamps it from the authenticated caller on create.
        $account = new MailAccount($data);
        $account->save();

        return response()->json(['account' => $this->present($account)], 201);
    }

    public function update(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        $data = KeepBlankSecrets::preserve($this->validated($request, isUpdate: true), ['password', 'smtp_password']);
        $account->update($data);

        return response()->json(['account' => $this->present($account->fresh() ?? $account)]);
    }

    public function destroy(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        // FK cascade removes the account's sync cursors; the archived messages
        // KEEP (account_id nullOnDelete — deleting the mailbox never destroys the
        // archive). Their blobs stay referenced by message id; a full USER delete
        // (GDPR MailData) reclaims everything.
        $account->delete();

        return response()->json([], 204);
    }

    /** Trigger an on-demand "sync now" for the account. */
    public function sync(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        SyncMailAccount::dispatch($account->id);

        return response()->json(['dispatched' => true]);
    }

    /**
     * Probe the account's IMAP (and, when configured, SMTP) login with its
     * stored credentials, without changing anything. Both probes are SSRF-guarded
     * (OutboundUrl before any socket). The top-level {ok, detail} is the IMAP
     * result (backward-compatible); an SMTP verdict is added under `smtp` only
     * when SMTP is configured. Both details are redacted (no credential leak).
     */
    public function test(Request $request, MailAccount $account, ImapProbe $probe, SmtpProbe $smtpProbe): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        $result = $probe->probe($account);
        if ($account->hasSmtp()) {
            $result['smtp'] = $smtpProbe->probe($account);
        }

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    /**
     * Cancel an in-flight sync: cancel the running ingest batch (every
     * IngestMailChunk checks $this->batch()?->cancelled()) and settle the account
     * back to idle. Idempotent: a no-op when nothing is running.
     */
    public function cancelSync(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        if ($account->sync_batch_id !== null) {
            Bus::findBatch($account->sync_batch_id)?->cancel();
        }

        $account->forceFill([
            'status' => 'idle',
            'sync_batch_id' => null,
        ])->save();

        return response()->json(['cancelled' => true]);
    }

    /** Current sync status + archived-message count for the account. */
    public function status(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        return response()->json([
            'status' => $account->status,
            'last_error' => $account->last_error,
            'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            'message_count' => $account->messages()->count(),
        ]);
    }

    /**
     * Validates the request and returns an EXPLICITLY built array (never the raw
     * Validator array) — every key is a string literal, so only these known,
     * allow-listed fields reach the model. Optional fields are included only when
     * present, so an omitted key on PUT never nulls out an existing column.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $isUpdate): array
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:200'],
            // SafeHost refuses link-local / cloud-metadata targets — the same
            // SSRF guard used for Paperless/NTFY hosts.
            'host' => ['required', 'string', 'max:255', new SafeHost],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            // Required on create; nullable on update (KeepBlankSecrets preserves
            // the stored value when sent blank).
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:2000'],
            'encryption' => ['required', 'string', Rule::in(MailAccount::ENCRYPTIONS)],
            // SMTP (compose/reply/forward) — all optional; smtp_host SSRF-guarded,
            // smtp_password blank-preserved on update (KeepBlankSecrets).
            'smtp_host' => ['nullable', 'string', 'max:255', new SafeHost],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:2000'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(MailAccount::ENCRYPTIONS)],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'email:rfc', 'max:255'],
            'folders' => ['nullable', 'array'],
            'folders.*' => ['string', 'max:255'],
            'backfill_since' => ['nullable', 'date'],
            'delete_after_import' => ['nullable', 'boolean'],
            'skip_spam' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'sync_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ])->validate();

        $data = [
            'name' => $request->string('name')->value(),
            'host' => $request->string('host')->value(),
            'port' => $request->integer('port'),
            'username' => $request->string('username')->value(),
            'password' => $request->string('password')->value(),
            'encryption' => $request->string('encryption')->value(),
        ];
        // SMTP fields: present → set (empty string → null so the user can clear
        // an address/host); smtp_password is never emptied here — a blank submit
        // is preserved by KeepBlankSecrets on update (like the IMAP password).
        foreach (['smtp_host', 'smtp_username', 'smtp_encryption', 'from_name', 'from_email'] as $key) {
            if ($request->has($key)) {
                $value = $request->string($key)->value();
                $data[$key] = $value === '' ? null : $value;
            }
        }
        if ($request->has('smtp_port')) {
            $port = $request->input('smtp_port');
            $data['smtp_port'] = ($port === null || $port === '') ? null : $request->integer('smtp_port');
        }
        if ($request->has('smtp_password')) {
            $data['smtp_password'] = $request->string('smtp_password')->value();
        }
        if ($request->has('folders')) {
            $data['folders'] = $request->input('folders');
        }
        if ($request->has('backfill_since')) {
            $data['backfill_since'] = $request->input('backfill_since');
        }
        if ($request->has('delete_after_import')) {
            $data['delete_after_import'] = $request->boolean('delete_after_import');
        }
        if ($request->has('skip_spam')) {
            $data['skip_spam'] = $request->boolean('skip_spam');
        }
        if ($request->has('enabled')) {
            $data['enabled'] = $request->boolean('enabled');
        }
        if ($request->has('sync_interval_minutes')) {
            $value = $request->input('sync_interval_minutes');
            $data['sync_interval_minutes'] = ($value === null || $value === '') ? null : $request->integer('sync_interval_minutes');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(MailAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'host' => $account->host,
            'port' => $account->port,
            'username' => $account->username,
            'encryption' => $account->encryption,
            // SMTP config for compose/reply/forward — the password is NEVER
            // serialised; `has_smtp_password` tells the client whether one is set.
            'smtp_host' => $account->smtp_host,
            'smtp_port' => $account->smtp_port,
            'smtp_username' => $account->smtp_username,
            'smtp_encryption' => $account->smtp_encryption,
            'from_name' => $account->from_name,
            'from_email' => $account->from_email,
            'has_smtp_password' => is_string($account->smtp_password) && $account->smtp_password !== '',
            'folders' => $account->folders,
            'backfill_since' => $account->backfill_since?->toDateString(),
            'delete_after_import' => $account->delete_after_import,
            'skip_spam' => $account->skip_spam,
            'enabled' => $account->enabled,
            'sync_interval_minutes' => $account->sync_interval_minutes,
            'status' => $account->status,
            'last_error' => $account->last_error,
            'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            'message_count' => $account->messages_count ?? $account->messages()->count(),
        ];
    }

    /** An account belongs to exactly one user; anyone else gets a 404 (no existence leak). */
    private function authorizeOwner(Request $request, MailAccount $account): void
    {
        abort_if((int) $account->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
