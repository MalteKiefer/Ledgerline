<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use App\Rules\SafeHost;
use App\Support\KeepBlankSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Owner-scoped CRUD + on-demand sync/status for the mail archive's IMAP
 * account configuration (metadata only — zero-knowledge preserving). The
 * account password is the one plaintext secret the server holds to run the
 * IMAP connection (`encrypted` cast at rest, see MailAccount); it is NEVER
 * present in any response from this controller (see `present()` — the
 * password key is simply never added to the array, and the model itself
 * hides it from array/JSON serialization as defence in depth).
 *
 * Ownership uses implicit route-model binding + an explicit `authorizeOwner`
 * 404 (never a 403 — no existence leak), mirroring
 * DevicePairingController::authorizeOwner.
 */
class MailAccountController extends Controller
{
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

        $account = new MailAccount($data);
        // $data is built explicitly in validated() and never includes 'user_id'
        // (even a request body carrying one is ignored) — AssignsOwner stamps
        // it from the authenticated caller on create, the only path in.
        $account->save();

        return response()->json(['account' => $this->present($account)], 201);
    }

    public function update(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        $data = KeepBlankSecrets::preserve($this->validated($request, isUpdate: true), ['password']);
        $account->update($data);

        return response()->json(['account' => $this->present($account->fresh() ?? $account)]);
    }

    public function destroy(Request $request, MailAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);

        // FK cascadeOnDelete on mail_sync_state/mail_messages removes the account's
        // sync cursors and message ledger rows. The sealed blobs those messages
        // referenced (mail/{blob}) are NOT unlinked here — mail_blobs.user_id
        // cascades with the USER, not the account, so the ledger row (and the
        // bytes it describes) survives this delete with nothing referencing it
        // anymore. That is reclaimed by the daily `mail:sweep-orphans` command
        // (SweepOrphanMailBlobs — frees a MailBlob row + its disk bytes once no
        // MailMessage references its id and it is older than the grace window)
        // and, on full account erasure, promptly by the MailData GDPR
        // contributor. This mirrors the intended reclaim model of every other
        // sealed-store module (the ledger row, not an inline unlink, is the
        // source of truth for reclaim).
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
     * Cancel an in-flight sync. Cancels the running ingest batch (every
     * IngestMailChunk checks $this->batch()?->cancelled() and stops) and
     * settles the account back to idle. Already-archived messages are kept;
     * un-ingested Maildir files stay on disk and a LATER scheduled sync would
     * resume them — disable the account to prevent that. Idempotent: a no-op
     * when nothing is running.
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
     * Validates the request and returns an EXPLICITLY built array (never the
     * raw Validator::validate() array, which Laravel does not generically
     * type as array<string, mixed>) — every key here is a string literal, so
     * only these known, allow-listed fields are ever passed on to the model.
     * Optional fields are included only when present, so an omitted key on
     * PUT never nulls out an existing column (Eloquent's fill() only touches
     * keys that are actually present in the given array).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $isUpdate): array
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:200'],
            // SafeHost refuses link-local / cloud-metadata targets (169.254.0.0/16,
            // fe80::/10) — the same SSRF-guard rule used for Paperless/NTFY hosts.
            'host' => ['required', 'string', 'max:255', new SafeHost],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            // Required on create; nullable on update (KeepBlankSecrets preserves the
            // stored value when the field is sent blank, so an edit form never has
            // to round-trip the current password to know it's "unchanged").
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:2000'],
            'encryption' => ['required', 'string', Rule::in(MailAccount::ENCRYPTIONS)],
            'folders' => ['nullable', 'array'],
            'folders.*' => ['string', 'max:255'],
            'backfill_since' => ['nullable', 'date'],
            'delete_after_import' => ['nullable', 'boolean'],
            'skip_spam' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            // Per-account fetch interval in minutes; null/absent = workspace default.
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

    /** An account belongs to exactly one user; anyone else gets a 404 (not a 403 — no existence leak). */
    private function authorizeOwner(Request $request, MailAccount $account): void
    {
        abort_if((int) $account->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
