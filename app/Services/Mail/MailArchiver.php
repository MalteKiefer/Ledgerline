<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
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

    /** @return array{new:int, archived:int, folders:int} */
    public function syncAccount(MailAccount $account, ?int $perRunCap = null): array
    {
        $cap = $perRunCap ?? (int) config('mail_archive.per_run_cap', 1000);
        $creds = $account->credentials();
        $disk = Storage::disk(config('files.disk'));

        $newCount = 0;
        $archivedCount = 0;
        $folderCount = 0;

        foreach ($this->source->folders($creds) as $f) {
            $folderCount++;
            $folder = MailFolder::firstOrNew(['mail_account_id' => $account->id, 'path' => $f['path']]);
            $validityChanged = $folder->exists && (int) $folder->uidvalidity !== (int) $f['uidvalidity'] && $folder->uidvalidity !== null;
            $folder->fill(['name' => $f['name'], 'delimiter' => $f['delimiter'], 'role' => $f['role'], 'uidvalidity' => $f['uidvalidity']])->save();

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

            // New UIDs (never stored, at the current validity): fetch newest-first,
            // capped, so the backlog drains over runs.
            $stored = MailMessage::where('mail_folder_id', $folder->id)
                ->where('uidvalidity', (int) $f['uidvalidity'])->pluck('uid')->all();
            $newUids = array_values(array_diff($serverSet, array_map('intval', $stored)));
            rsort($newUids);
            foreach (array_slice($newUids, 0, $cap) as $uid) {
                $m = $this->source->fetch($creds, $f['path'], $uid);
                $blob = (string) Str::uuid();
                $disk->put('mail/'.$blob, $m['raw']);
                $flags = $serverUids[$uid] ?? [];
                MailMessage::create([
                    'mail_account_id' => $account->id,
                    'mail_folder_id' => $folder->id,
                    'uid' => $uid,
                    'uidvalidity' => (int) $f['uidvalidity'],
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
                $newCount++;
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

        $account->update(['last_synced_at' => Carbon::now()]);

        return ['new' => $newCount, 'archived' => $archivedCount, 'folders' => $folderCount];
    }
}
