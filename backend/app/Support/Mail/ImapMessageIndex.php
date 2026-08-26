<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\MailAccount;
use RuntimeException;

/**
 * Reads which Message-Id sits at which UID in a folder.
 *
 * This is what lets an archive built before origin UIDs were recorded catch up:
 * the Message-Id is the one identifier both sides already agree on, so a folder
 * can be indexed once and every archived row matched against it.
 *
 * Asked for per folder rather than per message. The other direction — a UID
 * SEARCH for each archived Message-Id — is one round trip per message, which on
 * a mailbox of seventeen thousand is thousands of round trips and still cannot
 * tell "moved to another folder" from "gone".
 *
 * Only the Message-Id header is fetched (BODY.PEEK, so nothing is marked read),
 * never a body.
 */
class ImapMessageIndex extends AbstractImapClient
{
    protected function tagPrefix(): string
    {
        return 'X';
    }

    /**
     * Index one folder: Message-Id (lowercased, without angle brackets) => UID.
     *
     * @return array{uidvalidity:?int, ids:array<string,int>}
     *
     * @throws RuntimeException on a connection / protocol failure
     */
    public function index(MailAccount $account, string $folder, string $password): array
    {
        $this->assertHostAllowed($account);

        $conn = $this->connect($account);
        try {
            $this->login($conn, $account, $password);
            $uidvalidity = $this->select($conn, $folder);
            $ids = $this->fetchIds($conn);
            $this->logout($conn);

            return ['uidvalidity' => $uidvalidity, 'ids' => $ids];
        } finally {
            $conn->close();
            sodium_memzero($password);
        }
    }

    private function select(ImapStream $conn, string $folder): ?int
    {
        $tag = $this->nextTag();
        // EXAMINE, not SELECT: read-only, so indexing a mailbox cannot change
        // anything in it, not even by accident.
        $conn->write(sprintf("%s EXAMINE %s\r\n", $tag, $this->quoted($folder)));

        $uidvalidity = null;
        while (true) {
            $line = $conn->readLine();
            if (preg_match('/^\* OK \[UIDVALIDITY (\d+)\]/i', $line, $m) === 1) {
                $uidvalidity = (int) $m[1];

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail index: examine failed');
                }

                return $uidvalidity;
            }
        }
    }

    /**
     * @return array<string,int>
     */
    private function fetchIds(ImapStream $conn): array
    {
        $tag = $this->nextTag();
        $conn->write("{$tag} UID FETCH 1:* (BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])\r\n");

        $out = [];
        $uid = null;
        while (true) {
            $line = $conn->readLine();

            if (str_starts_with($line, $tag.' ')) {
                if (preg_match('/^'.preg_quote($tag, '/').' OK/i', $line) !== 1) {
                    throw new RuntimeException('mail index: fetch failed');
                }

                return $out;
            }

            if (preg_match('/^\* \d+ FETCH \(.*UID (\d+)/i', $line, $m) === 1) {
                $uid = (int) $m[1];
            }

            // The header arrives as a literal: the line ends with its byte
            // count and the bytes follow. We only have line reads, but a header
            // block is made of lines, so it can be consumed by counting the
            // bytes we have taken — including the CRLF the reader strips.
            if (preg_match('/\{(\d+)\}$/', $line, $m) === 1) {
                $want = (int) $m[1];
                $taken = 0;
                $literal = '';
                while ($taken < $want) {
                    $part = $conn->readLine();
                    $literal .= $part."\r\n";
                    $taken += strlen($part) + 2;
                }
                if ($uid !== null && preg_match('/message-id:\s*<?([^>\s]+)>?/i', $literal, $h) === 1) {
                    $out[strtolower(trim($h[1]))] = $uid;
                }
                $uid = null;
            }
        }
    }
}
