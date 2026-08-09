<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * The minimal line-oriented IMAP transport the raw-IMAP clients (ImapAppender /
 * ImapDeleter) speak over. Abstracted from the concrete socket (ImapConnection)
 * so the write-to-origin protocol logic is unit-testable against a scripted
 * fake without opening a real network socket.
 */
interface ImapStream
{
    /** Read one CRLF-terminated response line (without the terminator). */
    public function readLine(): string;

    /** Write raw bytes to the server. */
    public function write(string $data): void;

    /** Upgrade the connection to TLS (STARTTLS). Returns whether it succeeded. */
    public function enableCrypto(): bool;

    /** Close the underlying transport. */
    public function close(): void;
}
