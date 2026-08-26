<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\ImapFolders;
use App\Support\Mail\ImapMessageIndex;
use App\Support\Mail\MailLogger;
use App\Support\Redactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads every mailbox again and matches the archive against it.
 *
 * Two things it fixes, both of which need data the archive did not used to
 * keep:
 *
 * 1. The origin UID. Write-back — a read mark, a star, a move — can only name a
 *    message by its UID in a folder, and that was first recorded in v1.769.0.
 *    Everything archived before then is unreachable on the server; this walks
 *    the mailbox and attaches the UID to those rows.
 *
 * 2. Mail that is no longer there. A message deleted from another client simply
 *    stopped appearing in the sync, so the archive still showed it as if it
 *    were in the mailbox. It is now stamped as removed from the server —
 *    marked, never deleted here: the archived copy is the point of the archive.
 *
 * It does NOT re-download anything. The .eml bodies are already held, and the
 * ingest dedup would discard a second copy anyway; what is missing is the
 * mapping, not the mail.
 *
 * Matching is by Message-Id, the one identifier both sides already agree on,
 * and across ALL of the account's folders rather than the one the row names —
 * otherwise a message moved on the phone would look deleted. That also means a
 * move made elsewhere is picked up here.
 */
class ReconcileMailArchive extends Command
{
    protected $signature = 'mail:reconcile
        {--account= : Only this account}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Match the mail archive against the mailboxes: attach origin UIDs, mark mail the server no longer has';

    public function handle(ImapFolders $folders, ImapMessageIndex $index): int
    {
        $accounts = MailAccount::query()
            ->when($this->option('account') !== null, fn ($q) => $q->whereKey((int) $this->option('account')))
            ->where('enabled', true)
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No enabled mail accounts.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        foreach ($accounts as $account) {
            $this->line("Account {$account->id} ({$account->name})");
            try {
                $this->reconcile($account, $folders, $index, $dry);
            } catch (Throwable $e) {
                // One unreachable mailbox must not stop the others.
                $this->error('  '.Redactor::redact($e->getMessage()));
                MailLogger::record($account, 'warn', 'reconcile', null, Redactor::redact($e->getMessage()));
                Log::warning('mail.reconcile.failed', ['account_id' => $account->id, 'error' => Redactor::redact($e->getMessage())]);
            }
        }

        return self::SUCCESS;
    }

    private function reconcile(MailAccount $account, ImapFolders $folders, ImapMessageIndex $index, bool $dry): void
    {
        $password = (string) $account->password;

        // Every folder the server has, not only the ones we hold mail from: a
        // message moved elsewhere lives in one of the others.
        $names = [];
        foreach ($folders->list($account, $password) as $folder) {
            if ($folder['selectable']) {
                $names[] = $folder['name'];
            }
        }

        /** @var array<string, array{folder:string, uid:int, uidvalidity:?int}> $found */
        $found = [];
        foreach ($names as $name) {
            try {
                $result = $index->index($account, $name, $password);
            } catch (Throwable $e) {
                // A folder we cannot read tells us nothing either way, so its
                // messages must not be counted as missing. Skipping it is the
                // only safe answer.
                $this->warn("  {$name}: ".Redactor::redact($e->getMessage()).' (skipped)');
                $names = array_values(array_diff($names, [$name]));

                continue;
            }
            foreach ($result['ids'] as $messageId => $uid) {
                // First folder wins: a Message-Id in two folders is one message
                // the server has, and either location proves it is not gone.
                $found[$messageId] ??= ['folder' => $name, 'uid' => $uid, 'uidvalidity' => $result['uidvalidity']];
            }
            $this->line("  {$name}: ".count($result['ids']).' message(s)');
        }

        if ($names === []) {
            $this->warn('  No readable folders — nothing marked, to avoid calling everything missing.');

            return;
        }

        $linked = 0;
        $moved = 0;
        $gone = 0;
        $unidentifiable = 0;

        MailMessage::query()
            ->withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->select(['id', 'folder', 'uid', 'uidvalidity', 'message_id', 'removed_from_server_at'])
            ->chunkById(500, function ($rows) use ($found, $dry, &$linked, &$moved, &$gone, &$unidentifiable): void {
                foreach ($rows as $row) {
                    $key = strtolower(trim((string) $row->message_id, " \t<>"));
                    if ($key === '') {
                        // No Message-Id header at all: nothing to match on, so
                        // it is left exactly as it is rather than guessed at.
                        $unidentifiable++;

                        continue;
                    }

                    $hit = $found[$key] ?? null;
                    if ($hit === null) {
                        if ($row->removed_from_server_at !== null) {
                            continue;
                        }
                        $gone++;
                        if (! $dry) {
                            // Marked, never deleted: the archived copy stays,
                            // and its UID is cleared because it names nothing.
                            $row->forceFill([
                                'removed_from_server_at' => now(),
                                'uid' => null,
                                'uidvalidity' => null,
                            ])->save();
                        }

                        continue;
                    }

                    $patch = [];
                    if ((int) $row->uid !== $hit['uid'] || (int) $row->uidvalidity !== (int) $hit['uidvalidity']) {
                        $patch['uid'] = $hit['uid'];
                        $patch['uidvalidity'] = $hit['uidvalidity'];
                        $linked++;
                    }
                    if ($row->folder !== $hit['folder']) {
                        // Moved by another client — the mailbox is right about
                        // where its own mail is.
                        $patch['folder'] = $hit['folder'];
                        $moved++;
                    }
                    if ($row->removed_from_server_at !== null) {
                        // It is back (restored from a trash folder, say).
                        $patch['removed_from_server_at'] = null;
                    }
                    if ($patch !== [] && ! $dry) {
                        $row->forceFill($patch)->save();
                    }
                }
            });

        $this->info(sprintf(
            '  %s%d linked, %d relocated, %d no longer on the server, %d without a Message-Id (left alone)',
            $dry ? '[dry run] ' : '',
            $linked,
            $moved,
            $gone,
            $unidentifiable,
        ));
    }
}
