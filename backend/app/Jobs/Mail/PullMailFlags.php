<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\MailSyncState;
use App\Support\Mail\ImapFlagReader;
use App\Support\Mail\MailLogger;
use App\Support\Redactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brings read marks and stars set elsewhere back into the archive.
 *
 * The other half of the flag write-back, and the half without which the two
 * still disagree: read something on the phone and it stayed bold here.
 *
 * It cannot ride on the sync. mbsync would carry flag changes down onto the
 * local Maildir copy, but the ingestor shreds that copy as soon as the message
 * is archived — that shredding is what makes the archive loss-safe — so there
 * is nothing left for it to update. The flags are asked for directly instead.
 *
 * Conflict rule: the server wins. It is the mailbox; our own write-backs reach
 * it within seconds of the click, so the case where this overwrites a local
 * change that has not landed yet is a narrow one, and resolving it the other
 * way would mean the archive quietly ignoring what the mailbox says.
 */
class PullMailFlags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $accountId,
        public readonly string $folder,
    ) {}

    public function handle(ImapFlagReader $reader): void
    {
        $account = MailAccount::query()->find($this->accountId);
        if ($account === null || ! $account->enabled) {
            return;
        }

        $password = $account->password;
        if (! is_string($password) || $password === '') {
            return;
        }

        /** @var ?MailSyncState $state */
        $state = MailSyncState::query()
            ->where('account_id', $this->accountId)
            ->where('folder', $this->folder)
            ->first();

        try {
            $result = $reader->read($account, $this->folder, $state?->highmodseq, $password);
        } catch (Throwable $e) {
            MailLogger::record($account, 'warn', 'flags.pull', $this->folder, Redactor::redact($e->getMessage()));
            Log::warning('mail.flags.pull_failed', [
                'account_id' => $this->accountId,
                'folder' => $this->folder,
                'error' => Redactor::redact($e->getMessage()),
            ]);

            return;
        }

        $uidvalidity = $result['uidvalidity'];
        if ($uidvalidity === null) {
            // A server that does not report UIDVALIDITY on SELECT is out of
            // spec, and applying flags by UID without it would be guessing.
            return;
        }

        // The folder was renumbered: our stored window is meaningless, so the
        // cursor is dropped and the next run reads the whole folder afresh.
        if ($state !== null && $state->uidvalidity !== null && $state->uidvalidity !== $uidvalidity) {
            $state->forceFill(['highmodseq' => null, 'uidvalidity' => $uidvalidity, 'updated_at' => now()])->save();

            return;
        }

        $this->apply($account, $uidvalidity, $result['flags']);

        MailSyncState::query()->updateOrInsert(
            ['account_id' => $this->accountId, 'folder' => $this->folder],
            ['uidvalidity' => $uidvalidity, 'highmodseq' => $result['modseq'], 'updated_at' => now()],
        );
    }

    /**
     * Write the server's answer onto the archived rows.
     *
     * Only rows that actually differ are touched: an archived message carries
     * its full search text, so every write rebuilds all of its indexes, and a
     * folder answers mostly with flags we already agree about.
     *
     * @param  array<int, array{seen:bool, flagged:bool}>  $flags
     */
    private function apply(MailAccount $account, int $uidvalidity, array $flags): void
    {
        if ($flags === []) {
            return;
        }

        foreach (array_chunk($flags, 200, true) as $chunk) {
            $rows = MailMessage::query()
                ->withoutGlobalScopes()
                ->where('account_id', $account->id)
                ->where('folder', $this->folder)
                ->where('uidvalidity', $uidvalidity)
                ->whereIn('uid', array_keys($chunk))
                ->get(['id', 'uid', 'seen', 'flagged']);

            foreach ($rows as $row) {
                $want = $chunk[(int) $row->uid] ?? null;
                if ($want === null) {
                    continue;
                }
                $patch = [];
                if ((bool) $row->seen !== $want['seen']) {
                    $patch['seen'] = $want['seen'];
                    $patch['seen_at'] = $want['seen'] ? now() : null;
                }
                if ((bool) $row->flagged !== $want['flagged']) {
                    $patch['flagged'] = $want['flagged'];
                }
                if ($patch !== []) {
                    $row->forceFill($patch)->save();
                }
            }
        }
    }

    /**
     * Queue a flag pull for every folder this account has archived mail in.
     *
     * Folders we hold nothing from have nothing to reconcile — a flag on a
     * message we never fetched is not ours to record.
     */
    public static function queueForAccount(MailAccount $account): void
    {
        MailMessage::query()
            ->withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereNotNull('uid')
            ->select('folder')
            ->distinct()
            ->pluck('folder')
            ->each(function (mixed $folder) use ($account): void {
                if (is_string($folder) && $folder !== '') {
                    self::dispatch((int) $account->id, $folder);
                }
            });
    }
}
