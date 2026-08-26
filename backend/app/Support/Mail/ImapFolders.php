<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * The folders that exist on the origin server, and creating, renaming and
 * removing them.
 *
 * The archive's own folder list is derived from the mail it holds, so an empty
 * folder does not appear in it and a folder cannot be filed into before
 * something is already in it. This asks the server instead.
 *
 * Deleting is the one operation here that destroys something, and IMAP's DELETE
 * takes the messages with it. It is therefore refused for a folder that still
 * holds mail — the caller has to empty or move it first, deliberately, rather
 * than discover afterwards that a click removed a thousand messages.
 */
class ImapFolders extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'L';
    }

    /**
     * Every folder the account can see, with the separator the server uses.
     *
     * @return list<array{name:string, delimiter:?string, selectable:bool}>
     */
    public function list(MailAccount $account, string $password): array
    {
        $this->assertHostAllowed($account);

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);

            $tag = $this->nextTag();
            $conn->write("{$tag} LIST \"\" *\r\n");

            $out = [];
            while (true) {
                $line = $conn->readLine();
                // * LIST (\HasNoChildren) "." INBOX.Archive
                if (preg_match('/^\* LIST \(([^)]*)\) (NIL|"[^"]*") (.+)$/i', $line, $m) === 1) {
                    $flags = strtolower($m[1]);
                    $delimiter = $m[2] === 'NIL' ? null : trim($m[2], '"');
                    $name = trim($m[3]);
                    // A quoted name may contain the delimiter; unquote it here
                    // rather than making every caller do it.
                    if (str_starts_with($name, '"') && str_ends_with($name, '"')) {
                        $name = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($name, 1, -1));
                    }
                    $out[] = [
                        'name' => $name,
                        'delimiter' => $delimiter,
                        // \Noselect marks a folder that only holds other
                        // folders; nothing can be filed into it.
                        'selectable' => ! str_contains($flags, '\\noselect'),
                    ];

                    continue;
                }
                if (str_starts_with($line, $tag.' ')) {
                    if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                        throw new RuntimeException('mail folders: list failed');
                    }
                    $this->logout($conn);

                    return $out;
                }
            }
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    public function create(MailAccount $account, string $name, string $password): void
    {
        $this->simple($account, sprintf('CREATE %s', $this->quoted($name)), $password);
    }

    public function rename(MailAccount $account, string $from, string $to, string $password): void
    {
        $this->simple($account, sprintf('RENAME %s %s', $this->quoted($from), $this->quoted($to)), $password);
    }

    /**
     * Remove a folder — only if it is empty.
     *
     * IMAP's DELETE removes the messages with the folder and there is no undo,
     * so an accidental click on a full folder would be unrecoverable on the
     * server side. The count comes from the server, not from our archive: mail
     * we never fetched is still mail.
     *
     * @throws RuntimeException when the folder still holds messages
     */
    public function delete(MailAccount $account, string $name, string $password): void
    {
        $this->assertHostAllowed($account);

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);

            $count = $this->messageCount($conn, $name);
            if ($count > 0) {
                throw new RuntimeException('mail folders: '.$name.' still holds '.$count.' message(s)');
            }

            $this->command($conn, sprintf('DELETE %s', $this->quoted($name)));
            $this->logout($conn);
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    /** STATUS ... (MESSAGES n) — asks the server, not our copy of it. */
    private function messageCount(ImapStream $conn, string $folder): int
    {
        $tag = $this->nextTag();
        $conn->write(sprintf("%s STATUS %s (MESSAGES)\r\n", $tag, $this->quoted($folder)));

        $count = 0;
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/MESSAGES (\d+)/i', $line, $m) === 1) {
                $count = (int) $m[1];
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail folders: status failed');
                }

                return $count;
            }
        }
    }

    private function simple(MailAccount $account, string $command, string $password): void
    {
        $this->assertHostAllowed($account);

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);
            $this->command($conn, $command);
            $this->logout($conn);
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }
}
