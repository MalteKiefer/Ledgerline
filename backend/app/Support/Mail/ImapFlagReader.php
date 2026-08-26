<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * Reads back the flags the mailbox holds, so a mail read on the phone shows as
 * read here too.
 *
 * This cannot come from the sync. mbsync is deliberately pull-only and would
 * carry flag changes down — but only onto a local Maildir copy, and the
 * ingestor shreds that copy the moment the message is archived (that is what
 * makes the archive loss-safe). There is nothing left for it to update, so the
 * flags have to be asked for directly.
 *
 * With CONDSTORE the server is asked only for what changed since the last look
 * (`CHANGEDSINCE`), which on a large folder is the difference between a few
 * lines and every message in it. Without it, flags for the whole folder are
 * fetched — still only flags, no bodies.
 */
class ImapFlagReader extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'R';
    }

    /**
     * Flags per UID for one folder.
     *
     * @return array{uidvalidity:?int, modseq:?int, flags:array<int, array{seen:bool, flagged:bool}>}
     *
     * @throws RuntimeException on a connection / protocol failure
     */
    public function read(MailAccount $account, string $folder, ?int $sinceModseq, string $password): array
    {
        $this->assertHostAllowed($account);

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);

            // ENABLE CONDSTORE where the server has it. A server without it
            // answers BAD, which is not a failure — it just means the whole
            // folder's flags get fetched instead of the changed ones.
            $condstore = true;
            try {
                $this->command($conn, 'ENABLE CONDSTORE');
            } catch (RuntimeException) {
                $condstore = false;
            }

            $select = $this->select($conn, $folder);

            // A folder that has been renumbered invalidates every UID we hold
            // for it, so a changed-since window would be meaningless: the
            // caller re-reads everything and rebuilds its state.
            $useSince = $condstore && $sinceModseq !== null && $sinceModseq > 0;

            $command = $useSince
                ? sprintf('UID FETCH 1:* (FLAGS) (CHANGEDSINCE %d)', $sinceModseq)
                : 'UID FETCH 1:* (FLAGS)';

            $flags = $this->fetchFlags($conn, $command);
            $this->logout($conn);

            return [
                'uidvalidity' => $select['uidvalidity'],
                'modseq' => $select['modseq'],
                'flags' => $flags,
            ];
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /**
     * @return array{uidvalidity:?int, modseq:?int}
     */
    private function select(ImapStream $conn, string $folder): array
    {
        $tag = $this->nextTag();
        $conn->write(sprintf("%s SELECT %s\r\n", $tag, $this->quoted($folder)));

        $uidvalidity = null;
        $modseq = null;
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/^\* OK \[UIDVALIDITY (\d+)\]/i', $line, $m) === 1) {
                $uidvalidity = (int) $m[1];

                continue;
            }
            if (preg_match('/\[HIGHESTMODSEQ (\d+)\]/i', $line, $m) === 1) {
                $modseq = (int) $m[1];

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail flags: select failed');
                }

                return ['uidvalidity' => $uidvalidity, 'modseq' => $modseq];
            }
        }
    }

    /**
     * Read the untagged FETCH lines into UID => flags.
     *
     * A line looks like `* 12 FETCH (UID 42 FLAGS (\Seen \Flagged))`; the order
     * of the items is not fixed, so both are matched independently rather than
     * assumed to arrive in one shape.
     *
     * @return array<int, array{seen:bool, flagged:bool}>
     */
    private function fetchFlags(ImapStream $conn, string $command): array
    {
        $tag = $this->nextTag();
        $conn->write("{$tag} {$command}\r\n");

        $out = [];
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/^\* \d+ FETCH \(/i', $line) === 1) {
                if (preg_match('/UID (\d+)/i', $line, $u) !== 1) {
                    // Without a UID the line names nothing we can store.
                    continue;
                }
                $flags = preg_match('/FLAGS \(([^)]*)\)/i', $line, $f) === 1 ? strtolower($f[1]) : '';
                $out[(int) $u[1]] = [
                    'seen' => str_contains($flags, '\\seen'),
                    'flagged' => str_contains($flags, '\\flagged'),
                ];

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail flags: fetch failed');
                }

                return $out;
            }
        }
    }
}
