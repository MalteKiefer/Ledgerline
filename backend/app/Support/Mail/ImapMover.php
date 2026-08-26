<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * Moves messages between folders on the origin server.
 *
 * What makes this more than a COPY is the handle: after a move the message has
 * a new UID in its new folder, and the one we recorded refers to nothing. A
 * server with UIDPLUS says what the new one is (`COPYUID`), and then the row
 * stays addressable; one without leaves us holding a stale number, which we
 * throw away rather than keep and later aim at a different message.
 *
 * UID MOVE (RFC 6851) where available, COPY + \Deleted + expunge where not —
 * the same fallback shape ImapDeleter already uses for UID EXPUNGE, and for the
 * same reason: capability strings are worth less than trying the command.
 */
class ImapMover extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'M';
    }

    /**
     * Move a batch out of one folder into another, in one session.
     *
     * Returns the new UIDs keyed by the old ones, and the new folder's
     * generation — empty when the server did not tell us, which is not a
     * failure, only a lost handle.
     *
     * @param  list<int>  $uids
     * @return array{moved:int, uidvalidity:?int, map:array<int,int>}
     *
     * @throws RuntimeException when the source folder was renumbered, or on a
     *                          connection / protocol failure
     */
    public function move(MailAccount $account, string $folder, array $uids, string $target, int $uidvalidity, string $password): array
    {
        $this->assertHostAllowed($account);

        $clean = array_values(array_filter($uids, static fn (int $u): bool => $u > 0));
        if ($clean === []) {
            return ['moved' => 0, 'uidvalidity' => null, 'map' => []];
        }

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);

            $seen = $this->selectUidValidity($conn, $folder);
            if ($seen !== $uidvalidity) {
                // The folder was renumbered since these UIDs were recorded, so
                // they now name other messages — or none. Moving them would be
                // moving someone else's mail.
                throw new RuntimeException('mail move: folder was renumbered ('.$seen.' != '.$uidvalidity.')');
            }

            $set = implode(',', array_map(strval(...), $clean));
            $quotedTarget = $this->quoted($target);

            try {
                $copied = $this->commandWithCopyUid($conn, sprintf('UID MOVE %s %s', $set, $quotedTarget));
            } catch (RuntimeException) {
                // No MOVE: copy, mark the originals deleted, then expunge. Not
                // atomic — a failure between the steps leaves the message in
                // both folders, which is recoverable; the reverse (deleted but
                // not copied) is not, so the copy goes first.
                $copied = $this->commandWithCopyUid($conn, sprintf('UID COPY %s %s', $set, $quotedTarget));
                $this->command($conn, sprintf('UID STORE %s +FLAGS.SILENT (\\Deleted)', $set));
                try {
                    $this->command($conn, sprintf('UID EXPUNGE %s', $set));
                } catch (RuntimeException) {
                    $this->command($conn, 'EXPUNGE');
                }
            }

            $this->logout($conn);

            return ['moved' => count($clean), 'uidvalidity' => $copied['uidvalidity'], 'map' => $copied['map']];
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /**
     * Run a COPY/MOVE and pick the COPYUID out of its response.
     *
     * UIDPLUS answers with `[COPYUID <uidvalidity> <source-set> <dest-set>]`,
     * the two sets in matching order. Absent → no map, and the caller drops the
     * handle instead of assuming the numbers stayed the same.
     *
     * @return array{uidvalidity:?int, map:array<int,int>}
     */
    private function commandWithCopyUid(ImapStream $conn, string $command): array
    {
        $tag = $this->nextTag();
        $conn->write("{$tag} {$command}\r\n");

        $uidvalidity = null;
        $map = [];
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/\[COPYUID (\d+) ([\d,:]+) ([\d,:]+)\]/i', $line, $m) === 1) {
                $uidvalidity = (int) $m[1];
                $map = $this->pairSets($m[2], $m[3]);
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail move: '.$command.' rejected');
                }

                return ['uidvalidity' => $uidvalidity, 'map' => $map];
            }
        }
    }

    /**
     * Pair a COPYUID source set with its destination set, position by position.
     *
     * Both sides are UID sets — `1,5,9` or `1:3` — and RFC 4315 guarantees they
     * line up once expanded. A malformed or mismatched pair yields nothing
     * rather than a half-map that would rename the wrong messages.
     *
     * @return array<int,int>
     */
    private function pairSets(string $from, string $to): array
    {
        $left = $this->expandSet($from);
        $right = $this->expandSet($to);
        if ($left === [] || count($left) !== count($right)) {
            return [];
        }

        return array_combine($left, $right);
    }

    /**
     * @return list<int>
     */
    private function expandSet(string $set): array
    {
        $out = [];
        foreach (explode(',', $set) as $part) {
            if (preg_match('/^(\d+):(\d+)$/', $part, $m) === 1) {
                $lo = (int) $m[1];
                $hi = (int) $m[2];
                if ($lo > $hi) {
                    [$lo, $hi] = [$hi, $lo];
                }
                // A range that is not a range of a few thousand is a malformed
                // response, not a batch we sent; refuse rather than allocate.
                if ($hi - $lo > 100_000) {
                    return [];
                }
                for ($i = $lo; $i <= $hi; $i++) {
                    $out[] = $i;
                }

                continue;
            }
            if (ctype_digit($part)) {
                $out[] = (int) $part;
            }
        }

        return $out;
    }

    /** SELECT the folder and report the UIDVALIDITY the server gives for it. */
    private function selectUidValidity(ImapStream $conn, string $folder): ?int
    {
        $tag = $this->nextTag();
        $conn->write(sprintf("%s SELECT %s\r\n", $tag, $this->quoted($folder)));

        $value = null;
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/^\* OK \[UIDVALIDITY (\d+)\]/i', $line, $m) === 1) {
                $value = (int) $m[1];

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail move: select failed');
                }

                return $value;
            }
        }
    }
}
