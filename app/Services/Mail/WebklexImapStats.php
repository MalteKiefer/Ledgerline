<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Webklex\PHPIMAP\ClientManager;

/**
 * IMAP statistics via the pure-PHP webklex/php-imap client (no ext-imap).
 *
 * Aggregates message and unseen counts across all folders (cheap STATUS
 * commands, no message bodies fetched) and reads STORAGE quota when the server
 * advertises the QUOTA extension.
 */
final class WebklexImapStats implements ImapStats
{
    private const TIMEOUT = 10;

    public function fetch(ImapCredentials $c): array
    {
        $client = (new ClientManager)->make([
            'host' => $c->host,
            'port' => $c->port,
            'encryption' => $c->encryption,
            'validate_cert' => $c->validateCert,
            'username' => $c->username,
            'password' => $c->password,
            'protocol' => 'imap',
            'timeout' => self::TIMEOUT,
        ]);

        $client->connect();

        try {
            $total = 0;
            $unseen = 0;
            $folders = [];

            foreach ($client->getFolders(false) as $folder) {
                $messages = 0;
                $unread = 0;
                // STATUS on a \Noselect container returns NO; skip the round-trip
                // (this runs for every folder on every background-sync tick).
                if (! $folder->no_select) {
                    try {
                        $status = $folder->status();
                        $messages = (int) ($status['messages'] ?? 0);
                        $unread = (int) ($status['unseen'] ?? 0);
                    } catch (\Throwable) {
                    }
                }
                $folders[] = ['name' => $folder->name ?: $folder->path, 'path' => $folder->path, 'total' => $messages, 'unseen' => $unread];
                $total += $messages;
                $unseen += $unread;
            }

            [$quotaUsed, $quotaLimit] = $this->quota($client);

            return [
                'total' => $total,
                'unseen' => $unseen,
                'quotaUsed' => $quotaUsed,
                'quotaLimit' => $quotaLimit,
                'folders' => $folders,
            ];
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
                // ignore teardown errors
            }
        }
    }

    /**
     * STORAGE quota (bytes) from GETQUOTAROOT, or [null, null] when the server
     * has no QUOTA support. RFC 2087 reports STORAGE in kibibytes.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function quota($client): array
    {
        try {
            $storage = $this->findStorage($client->getQuotaRoot('INBOX'));
            if ($storage !== null) {
                return [$storage[0] * 1024, $storage[1] * 1024];
            }
        } catch (\Throwable) {
            // no QUOTA extension / not permitted
        }

        return [null, null];
    }

    /**
     * Recursively locate a ['STORAGE', used, limit] triplet in the quota
     * response.
     *
     * @return array{0: int, 1: int}|null
     */
    private function findStorage(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }
            $count = count($item);
            for ($i = 0; $i < $count; $i++) {
                if (is_string($item[$i]) && strtoupper($item[$i]) === 'STORAGE'
                    && isset($item[$i + 1], $item[$i + 2])
                    && is_numeric($item[$i + 1]) && is_numeric($item[$i + 2])) {
                    return [(int) $item[$i + 1], (int) $item[$i + 2]];
                }
            }
            $nested = $this->findStorage($item);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }
}
