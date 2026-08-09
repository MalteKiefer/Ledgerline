<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * Raw-IMAP client for ONE operation: DELETE messages from the origin mailbox —
 * either by RFC822 Message-Id (explicit per-message "delete from server", behind
 * a confirmation) or by a batch of origin UIDs (the opt-in delete-after-import
 * sync path, where the ingestor already knows the exact UIDs). A deliberate,
 * destructive WRITE-to-origin. Non-ZK: the Message-Id is read from the archived
 * row's denormalised column — no client round-trip.
 */
class ImapDeleter extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'd';
    }

    /**
     * Delete the message with the given RFC822 Message-Id from $folder. Returns
     * the number of messages expunged (0 if none matched). Throws on any
     * protocol/connection failure.
     */
    public function delete(MailAccount $account, string $folder, string $messageId, string $password): int
    {
        $this->assertHostAllowed($account);
        $messageId = trim($messageId);
        if ($messageId === '') {
            throw new RuntimeException('mail delete: empty message-id');
        }

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);
            $this->command($conn, sprintf('SELECT %s', $this->quoted($folder)));

            $uids = $this->searchByMessageId($conn, $messageId);
            if ($uids === []) {
                $this->logout($conn);

                return 0;
            }

            $this->expunge($conn, $uids);
            $this->logout($conn);

            return count($uids);
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /**
     * Delete a batch of messages from $folder by their origin IMAP UIDs, in ONE
     * session. Used by delete-after-import (UIDs from the Maildir filename, no
     * per-message search). Non-numeric UIDs are dropped. Returns the number
     * flagged (0 if none valid). Throws on connection/protocol failure.
     *
     * @param  list<string>  $uids
     */
    public function deleteUids(MailAccount $account, string $folder, array $uids, string $password): int
    {
        $this->assertHostAllowed($account);
        $clean = array_values(array_filter($uids, static fn (string $u): bool => ctype_digit($u)));
        if ($clean === []) {
            return 0;
        }

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);
            $this->command($conn, sprintf('SELECT %s', $this->quoted($folder)));
            $this->expunge($conn, $clean);
            $this->logout($conn);

            return count($clean);
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /**
     * UID STORE +FLAGS (\Deleted) then expunge. Prefer UIDPLUS UID EXPUNGE (only
     * our UIDs); fall back to EXPUNGE (expunges every \Deleted in the folder —
     * we only flagged ours).
     *
     * @param  list<string>  $uids
     */
    private function expunge(ImapStream $conn, array $uids): void
    {
        $set = implode(',', $uids);
        $this->command($conn, sprintf('UID STORE %s +FLAGS (\\Deleted)', $set));
        try {
            $this->command($conn, sprintf('UID EXPUNGE %s', $set));
        } catch (RuntimeException) {
            $this->command($conn, 'EXPUNGE');
        }
    }

    /**
     * UID SEARCH HEADER Message-Id "<id>"; parse the untagged "* SEARCH ..." line
     * into numeric UID strings.
     *
     * @return list<string>
     */
    private function searchByMessageId(ImapStream $conn, string $messageId): array
    {
        $tag = $this->nextTag();
        $conn->write(sprintf("%s UID SEARCH HEADER Message-Id %s\r\n", $tag, $this->quoted($messageId)));

        $uids = [];
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/^\* SEARCH\b(.*)$/i', $line, $m) === 1) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $tok) {
                    if ($tok !== '' && ctype_digit($tok)) {
                        $uids[] = $tok;
                    }
                }

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail delete: search failed');
                }

                return $uids;
            }
        }
    }
}
