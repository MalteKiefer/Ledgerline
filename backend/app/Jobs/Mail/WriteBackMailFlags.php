<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\ImapFlagWriter;
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
 * Carries a read mark or a star from the archive back to the mailbox.
 *
 * On the queue, never inline: an IMAP round trip is seconds, and marking a
 * page of mail read must not wait for it. The local change is already
 * committed by the time this runs, so a mailbox that is down delays the write
 * rather than losing the click.
 *
 * One job is one (account, folder, generation) — UIDs are only comparable
 * within those, and the writer refuses a batch whose generation has moved on.
 *
 * $tries = 2: a connection that drops is worth one retry, but a rejected batch
 * is rejected for a reason that will not change on a second attempt.
 */
class WriteBackMailFlags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @param  list<int>  $uids
     * @param  'seen'|'flagged'  $flag
     */
    public function __construct(
        private readonly int $accountId,
        private readonly string $folder,
        private readonly int $uidvalidity,
        private readonly array $uids,
        private readonly string $flag,
        private readonly bool $add,
    ) {}

    public function handle(ImapFlagWriter $writer): void
    {
        $account = MailAccount::query()->find($this->accountId);
        if ($account === null || ! $account->enabled || ! $account->write_back_flags) {
            return;
        }

        $password = $account->password;
        if (! is_string($password) || $password === '') {
            return;
        }

        try {
            $writer->store($account, $this->folder, $this->uids, $this->flag, $this->add, $this->uidvalidity, $password);
        } catch (Throwable $e) {
            // Best effort by design: the archive already holds the change, and
            // an origin that refuses it is worth a log line, not a lost click.
            // Recorded on the account's own log so it is visible where the
            // mailbox is configured, not only in the application log.
            MailLogger::record($account, 'warn', 'flags.write_back', $this->folder, Redactor::redact($e->getMessage()));
            Log::warning('mail.flags.write_back_failed', [
                'account_id' => $this->accountId,
                'folder' => $this->folder,
                'count' => count($this->uids),
                'error' => Redactor::redact($e->getMessage()),
            ]);

            throw $e;
        }
    }

    /**
     * Queue write-backs for whatever of these messages can be identified on the
     * origin server.
     *
     * Messages without an origin reference — appended here, or imported before
     * the reference was recorded — are silently skipped: there is no message to
     * aim at, and guessing one would set a flag on a stranger's mail.
     *
     * @param  list<string>  $messageIds  archive ids, not Message-Id headers
     * @param  'seen'|'flagged'  $flag
     */
    public static function queueFor(int $userId, array $messageIds, string $flag, bool $add): void
    {
        if ($messageIds === []) {
            return;
        }

        MailMessage::query()
            // Explicit, not only the global scope: this decides which mailbox
            // gets written to, and it must not depend on who happens to be
            // authenticated when it runs.
            ->where('user_id', $userId)
            ->whereIn('id', $messageIds)
            ->whereNotNull('uid')
            ->whereNotNull('uidvalidity')
            ->whereNotNull('account_id')
            ->get(['id', 'account_id', 'folder', 'uid', 'uidvalidity'])
            // One batch per mailbox, folder and generation of that folder: a
            // UID means nothing outside of all three.
            ->groupBy(fn (MailMessage $m): string => $m->account_id.'|'.$m->folder.'|'.$m->uidvalidity)
            ->each(function ($group) use ($flag, $add): void {
                /** @var MailMessage $first */
                $first = $group->first();
                self::dispatch(
                    (int) $first->account_id,
                    $first->folder,
                    (int) $first->uidvalidity,
                    array_values($group->map(fn (MailMessage $m): int => (int) $m->uid)->all()),
                    $flag,
                    $add,
                );
            });
    }
}
