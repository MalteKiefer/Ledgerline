<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Fetches read-only statistics for an IMAP account.
 *
 * Implementations connect with the given transient credentials, gather counts
 * and quota, and return a plain array. They must not persist or log the
 * credentials.
 */
interface ImapStats
{
    /**
     * @return array{
     *     total: int,
     *     unseen: int,
     *     quotaUsed: int|null,
     *     quotaLimit: int|null,
     *     folders: list<array{name: string, total: int, unseen: int}>,
     * }
     *
     * @throws \Throwable when the connection or authentication fails
     */
    public function fetch(ImapCredentials $credentials): array;
}
