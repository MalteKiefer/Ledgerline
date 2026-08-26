<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\ImapMover;
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
 * Moves messages on the origin server to match a move made here.
 *
 * Throwing a mail away in a mail client is expected to reach the mailbox; the
 * archive's own copy is never touched by this — only the server's.
 *
 * On the queue and best effort, like the flag write-back: the local state is
 * already committed, so an unreachable mailbox delays the move rather than
 * losing it. The one thing it must never do is guess, so a batch whose folder
 * has been renumbered is refused by the mover and lands here as a log line.
 */
class WriteBackMailMove implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * @param  list<int>  $uids
     * @param  list<string>  $messageIds  archive ids, positionally unrelated to $uids
     */
    public function __construct(
        public readonly int $accountId,
        public readonly string $folder,
        public readonly int $uidvalidity,
        public readonly array $uids,
        /** Where the messages are going — readable so a caller can assert on it. */
        public readonly string $target,
        public readonly array $messageIds,
    ) {}

    public function handle(ImapMover $mover): void
    {
        $account = MailAccount::query()->find($this->accountId);
        if ($account === null || ! $account->enabled || ! $account->write_back_deletes) {
            return;
        }

        $password = $account->password;
        if (! is_string($password) || $password === '') {
            return;
        }

        try {
            $result = $mover->move($account, $this->folder, $this->uids, $this->target, $this->uidvalidity, $password);
        } catch (Throwable $e) {
            MailLogger::record($account, 'warn', 'move.write_back', $this->folder, Redactor::redact($e->getMessage()));
            Log::warning('mail.move.write_back_failed', [
                'account_id' => $this->accountId,
                'folder' => $this->folder,
                'target' => $this->target,
                'count' => count($this->uids),
                'error' => Redactor::redact($e->getMessage()),
            ]);

            throw $e;
        }

        $this->recordNewLocation($result['map'], $result['uidvalidity']);
    }

    /**
     * Point the archive rows at where the messages now live.
     *
     * With UIDPLUS the server told us the new UIDs and the rows stay
     * addressable. Without it we know the folder but not the numbers, and a
     * stale UID is worse than none — it would aim the next write-back at
     * whatever message holds that number in the new folder — so it is cleared.
     *
     * @param  array<int,int>  $map  old UID => new UID
     */
    private function recordNewLocation(array $map, ?int $newUidvalidity): void
    {
        $rows = MailMessage::query()
            ->whereIn('id', $this->messageIds)
            ->where('account_id', $this->accountId)
            ->get(['id', 'uid']);

        foreach ($rows as $row) {
            $old = (int) $row->uid;
            $new = $map[$old] ?? null;
            $row->forceFill([
                'folder' => $this->target,
                'uid' => $new,
                'uidvalidity' => $new === null ? null : $newUidvalidity,
            ])->save();
        }
    }

    /**
     * Queue a move for whatever of these messages can still be found on the
     * origin server, and say which archive rows each batch covers.
     *
     * @param  list<string>  $messageIds
     */
    public static function queueFor(int $userId, array $messageIds, string $target): void
    {
        if ($messageIds === []) {
            return;
        }

        MailMessage::query()
            ->where('user_id', $userId)
            ->whereIn('id', $messageIds)
            ->whereNotNull('uid')
            ->whereNotNull('uidvalidity')
            ->whereNotNull('account_id')
            // Already in the target folder: nothing to move, and asking the
            // server to move a message onto itself is an error on some servers.
            ->where('folder', '!=', $target)
            ->get(['id', 'account_id', 'folder', 'uid', 'uidvalidity'])
            ->groupBy(fn (MailMessage $m): string => $m->account_id.'|'.$m->folder.'|'.$m->uidvalidity)
            ->each(function ($group) use ($target): void {
                /** @var MailMessage $first */
                $first = $group->first();
                self::dispatch(
                    (int) $first->account_id,
                    $first->folder,
                    (int) $first->uidvalidity,
                    array_values($group->map(fn (MailMessage $m): int => (int) $m->uid)->all()),
                    $target,
                    array_values($group->map(fn (MailMessage $m): string => (string) $m->id)->all()),
                );
            });
    }
}
