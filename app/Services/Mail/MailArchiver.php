<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailMessage;
use App\Support\BlobStore;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds and maintains the local mail archive from a MailSource.
 *
 * Per folder it diffs the server's current UID set against the archive:
 *  - new UIDs are fetched (capped per run — newest first — so a large mailbox
 *    fills in over successive hourly runs) and stored (raw .eml on the files
 *    disk, metadata in the database);
 *  - UIDs that vanished from the server are KEPT and marked deleted_on_server_at
 *    so an accidental deletion never loses the mail; and
 *  - flags (seen/flagged/answered) of still-present messages are updated.
 * A UIDVALIDITY change means the server renumbered the folder: the old
 * messages are archived (marked server-deleted) and the folder refills.
 */
class MailArchiver
{
    public function __construct(private readonly MailSource $source) {}

    /**
     * @param  int|null  $perRunCap  max NEW messages to fetch this call (total across folders)
     * @param  float|null  $deadline  microtime(true) after which fetching stops
     * @return array{new:int, archived:int, folders:int}
     */
    public function syncAccount(MailAccount $account, ?int $perRunCap = null, ?float $deadline = null): array
    {
        $cap = $perRunCap ?? (int) config('mail_archive.per_run_cap', 100);
        $deadline ??= microtime(true) + (int) config('mail_archive.max_run_seconds', 300);
        $creds = $account->credentials();
        $disk = BlobStore::disk();

        $newCount = 0;
        $archivedCount = 0;
        $folderCount = 0;

        foreach ($this->source->folders($creds) as $f) {
            // Time budget is global (across all folders and accounts); stop
            // entering more folders once it is reached. The per-folder message
            // cap is applied inside syncFolder, so EVERY folder makes progress
            // each run rather than one folder eating the whole budget.
            if (microtime(true) >= $deadline) {
                break;
            }
            $folderCount++;
            try {
                $this->syncFolder($account, $creds, $disk, $f, $cap, $deadline, $newCount, $archivedCount);
            } catch (\Throwable $e) {
                // One folder failing (e.g. an iCloud special folder that answers
                // a SELECT/SEARCH with an empty response) must not abort the whole
                // account — skip it and keep archiving the rest.
                Log::warning('Mail folder sync skipped', [
                    'account' => $account->id,
                    'folder' => $f['path'] ?? '?',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $account->update(['last_synced_at' => Carbon::now()]);

        return ['new' => $newCount, 'archived' => $archivedCount, 'folders' => $folderCount];
    }

    /**
     * Sync a single folder into the archive. Extracted so a failure in one
     * folder can be caught and skipped by syncAccount without losing the rest.
     *
     * @param  Filesystem  $disk
     * @param  array{path:string, name:string, delimiter:string, role:?string, uidvalidity:int}  $f
     * @param  int  $cap  max new messages to fetch from THIS folder this run
     * @param  float  $deadline  microtime(true) after which fetching stops (global)
     */
    private function syncFolder(MailAccount $account, ImapCredentials $creds, $disk, array $f, int $cap, float $deadline, int &$newCount, int &$archivedCount): void
    {
        $folder = MailFolder::withoutGlobalScopes()->firstOrNew(['mail_account_id' => $account->id, 'path' => $f['path']]);
        $validityChanged = $folder->exists && (int) $folder->uidvalidity !== (int) $f['uidvalidity'] && $folder->uidvalidity !== null;
        // Sync runs without an auth context, so carry the account's owner
        // explicitly onto folders/messages for per-user isolation.
        $folder->fill(['user_id' => $account->user_id, 'name' => $f['name'], 'delimiter' => $f['delimiter'], 'role' => $f['role'], 'uidvalidity' => $f['uidvalidity']])->save();

        // Server reset the folder's UIDs → archive everything we had here.
        if ($validityChanged) {
            $archivedCount += MailMessage::where('mail_folder_id', $folder->id)
                ->whereNull('deleted_on_server_at')
                ->update(['deleted_on_server_at' => Carbon::now()]);
        }

        $serverUids = $this->source->uids($creds, $f['path']); // [uid => flags]
        $serverSet = array_map('intval', array_keys($serverUids));

        // Deletion detection: present locally (not yet archived) but gone on
        // the server → keep, mark archived. An empty $serverSet here means a
        // genuinely empty folder (uids() throws on enumeration failure rather
        // than returning [], so it can never be a transient false positive),
        // in which case every local message is correctly marked deleted.
        $archivedCount += MailMessage::where('mail_folder_id', $folder->id)
            ->whereNull('deleted_on_server_at')
            ->when($serverSet !== [], fn ($q) => $q->whereNotIn('uid', $serverSet))
            ->update(['deleted_on_server_at' => Carbon::now()]);

        // New UIDs (never stored, at the current validity): fetch newest-first
        // until the shared run cap or the time budget is reached, so the backlog
        // drains over runs.
        $stored = MailMessage::where('mail_folder_id', $folder->id)
            ->where('uidvalidity', (int) $f['uidvalidity'])->pluck('uid')->all();
        $newUids = array_values(array_diff($serverSet, array_map('intval', $stored)));
        rsort($newUids);
        $fetched = 0;
        foreach ($newUids as $uid) {
            if ($fetched >= $cap || microtime(true) >= $deadline) {
                break;
            }
            $m = $this->source->fetch($creds, $f['path'], $uid);
            $this->persist($account, $folder, $uid, (int) $f['uidvalidity'], $m, $serverUids[$uid] ?? [], $disk);
            $newCount++;
            $fetched++;
        }

        // Update flags of still-present, non-archived messages.
        foreach ($serverUids as $uid => $flags) {
            MailMessage::where('mail_folder_id', $folder->id)
                ->where('uid', (int) $uid)->whereNull('deleted_on_server_at')
                ->update([
                    'seen' => (bool) ($flags['seen'] ?? false),
                    'flagged' => (bool) ($flags['flagged'] ?? false),
                    'answered' => (bool) ($flags['answered'] ?? false),
                ]);
        }
    }

    /**
     * Archive one specific message on demand (used when the reader opens a
     * not-yet-archived message live — it is captured at the same time). Returns
     * true if it was newly stored, false if already present or unavailable.
     */
    public function archiveMessage(MailAccount $account, string $folderPath, int $uid, int $uidValidity): bool
    {
        $folder = MailFolder::withoutGlobalScopes()->firstOrCreate(
            ['mail_account_id' => $account->id, 'path' => $folderPath],
            ['user_id' => $account->user_id, 'name' => $folderPath, 'delimiter' => '/', 'uidvalidity' => $uidValidity],
        );
        // Fill in a validity we did not know yet, but never clobber a real one.
        if ($folder->uidvalidity === null) {
            $folder->update(['uidvalidity' => $uidValidity]);
        }

        $exists = MailMessage::where('mail_folder_id', $folder->id)
            ->where('uid', $uid)
            ->where('uidvalidity', $uidValidity)
            ->exists();
        if ($exists) {
            return false;
        }

        $m = $this->source->fetch($account->credentials(), $folderPath, $uid);
        $this->persist($account, $folder, $uid, $uidValidity, $m, [], BlobStore::disk());

        return true;
    }

    /**
     * Store a fetched message's raw .eml on disk + its metadata row.
     *
     * @param  Filesystem  $disk
     * @param  array<string,mixed>  $m  the MailSource::fetch result
     * @param  array{seen?:bool,flagged?:bool,answered?:bool}  $flags
     */
    private function persist(MailAccount $account, MailFolder $folder, int $uid, int $uidValidity, array $m, array $flags, $disk): void
    {
        $blob = (string) Str::uuid();
        $disk->put('mail/'.$blob, $m['raw']);
        MailMessage::create([
            'user_id' => $account->user_id,
            'mail_account_id' => $account->id,
            'mail_folder_id' => $folder->id,
            'uid' => $uid,
            'uidvalidity' => $uidValidity,
            'message_id' => $m['message_id'] ?? null,
            'subject' => $m['subject'] ?? null,
            'from_name' => $m['from_name'] ?? null,
            'from_email' => $m['from_email'] ?? null,
            'to' => $m['to'] ?? [],
            'cc' => $m['cc'] ?? [],
            'date_at' => ! empty($m['date']) ? Carbon::parse($m['date']) : null,
            'seen' => (bool) ($flags['seen'] ?? false),
            'flagged' => (bool) ($flags['flagged'] ?? false),
            'answered' => (bool) ($flags['answered'] ?? false),
            'has_attachments' => (bool) ($m['has_attachments'] ?? false),
            'attachment_names' => $m['attachment_names'] ?? [],
            'size' => (int) ($m['size'] ?? strlen($m['raw'])),
            'blob' => $blob,
            'preview' => $m['preview'] ?? null,
            'body_text' => $m['body_text'] ?? null,
            'synced_at' => Carbon::now(),
        ]);
    }
}
