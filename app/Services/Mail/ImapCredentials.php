<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Transient IMAP connection credentials.
 *
 * Loaded server-side from the (password-encrypted) mail account row for the
 * duration of a single IMAP operation. They never travel to the browser and
 * are never logged.
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
