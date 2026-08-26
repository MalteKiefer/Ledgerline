<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * Carries a read mark or a star back to the origin mailbox.
 *
 * Until now every flag lived only in the archive: marking a hundred newsletters
 * read here left them bold in the phone's mail app, which makes the read state
 * of a mailbox two different answers depending on where you look. This writes
 * the archive's answer back.
 *
 * Bounded on purpose — \Seen and \Flagged only. They are the two flags a user
 * sets by hand and expects to travel; \Deleted has its own path (ImapDeleter)
 * because it destroys something, and \Draft/\Recent are the server's business.
 *
 * The UIDVALIDITY check is the point of the class. A UID identifies a message
 * only within one generation of a folder; when a server renumbers a folder it
 * says so, and every UID stored before that now points at a different message —
 * or at nothing. So the folder is selected, its generation compared with the
 * one recorded at ingest, and a mismatch refuses the whole batch rather than
 * starring a stranger's message.
 */
class ImapFlagWriter extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'F';
    }

    /**
     * Set or clear one flag on a batch of UIDs in one session.
     *
     * Returns the number of UIDs sent. Zero means nothing was attempted: no
     * usable UIDs, or the folder has been renumbered since these were recorded
     * — the caller can tell the two apart from the exception, because only the
     * renumbering throws.
     *
     * @param  list<int>  $uids
     * @param  'seen'|'flagged'  $flag
     *
     * @throws RuntimeException when the folder was renumbered, or on a
     *                          connection / protocol failure
     */
    public function store(MailAccount $account, string $folder, array $uids, string $flag, bool $add, int $uidvalidity, string $password): int
    {
        $this->assertHostAllowed($account);

        // Server-set values, but they end up in a command line: a UID that is
        // not a positive integer never reaches the socket.
        $clean = array_values(array_filter($uids, static fn (int $u): bool => $u > 0));
        if ($clean === []) {
            return 0;
        }

        $imapFlag = match ($flag) {
            'seen' => '\\Seen',
            'flagged' => '\\Flagged',
        };

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);

            $seen = $this->selectUidValidity($conn, $folder);
            if ($seen !== $uidvalidity) {
                // Not an error on the server's part — it renumbered, as it is
                // allowed to. Refusing is the only safe answer: these UIDs now
                // mean something else.
                throw new RuntimeException('mail flags: folder was renumbered ('.$seen.' != '.$uidvalidity.')');
            }

            // SILENT: we are not interested in the server echoing each new flag
            // set back at us, and a large batch would otherwise answer with one
            // untagged FETCH per message.
            $this->command($conn, sprintf(
                'UID STORE %s %sFLAGS.SILENT (%s)',
                implode(',', array_map(strval(...), $clean)),
                $add ? '+' : '-',
                $imapFlag,
            ));
            $this->logout($conn);

            return count($clean);
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /**
     * SELECT the folder and return the UIDVALIDITY the server reports for it.
     *
     * It arrives as an untagged `* OK [UIDVALIDITY n]` before the tagged OK, so
     * the response has to be read rather than just checked. A server that does
     * not send one is out of spec (RFC 3501 requires it on SELECT); we treat
     * that as a mismatch rather than assuming agreement.
     */
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
                    throw new RuntimeException('mail flags: select failed');
                }

                return $value;
            }
        }
    }
}
