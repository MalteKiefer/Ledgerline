<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Transient IMAP connection credentials.
 *
 * These are decrypted in the browser from the vault manifest and sent to the
 * server only for the duration of a single stats fetch. They are never
 * persisted or logged.
 */
final readonly class ImapCredentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $encryption, // 'ssl' | 'starttls'
        public string $username,
        public string $password,
        public bool $validateCert = true,
    ) {}
}
