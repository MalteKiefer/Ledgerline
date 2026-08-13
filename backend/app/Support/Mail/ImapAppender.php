<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * Raw-IMAP client for ONE operation: APPEND a message back to the origin
 * mailbox (the "push back to server" feature). A deliberate WRITE-to-origin,
 * used only on an explicit per-message user action. Non-ZK: the server already
 * holds the archived raw .eml, so no client upload of plaintext is needed — the
 * caller passes the stored bytes straight in.
 */
class ImapAppender extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'a';
    }

    /**
     * APPEND $rawMessage (a full RFC822 message) to $folder on the account's
     * origin IMAP server. Throws RuntimeException on any protocol/connection
     * failure (the caller maps that to a generic error — never leaks internals).
     */
    public function append(MailAccount $account, string $folder, string $rawMessage, string $password, bool $seen = false): void
    {
        $this->assertHostAllowed($account);
        if ($rawMessage === '') {
            throw new RuntimeException('mail push-back: empty message');
        }

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);
            $this->appendLiteral($conn, $folder, $rawMessage, $seen);
            $this->logout($conn);
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /**
     * APPEND with a literal: send the command, wait for the "+" continuation,
     * stream the exact-length message, then read the tagged OK. Restores the
     * origin read state by setting \Seen when the archived message was read.
     */
    private function appendLiteral(ImapStream $conn, string $folder, string $rawMessage, bool $seen): void
    {
        $tag = $this->nextTag();
        $flags = $seen ? ' (\\Seen)' : '';
        $conn->write(sprintf("%s APPEND %s%s {%d}\r\n", $tag, $this->quoted($folder), $flags, strlen($rawMessage)));

        if (! str_starts_with($conn->readLine(), '+')) {
            throw new RuntimeException('mail push-back: server rejected APPEND');
        }
        $conn->write($rawMessage."\r\n");
        $this->expectTagged($conn, $tag);
    }
}
