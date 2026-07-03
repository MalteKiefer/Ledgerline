<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Carbon\Carbon;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

/**
 * IMAP reading and message actions via webklex/php-imap (pure PHP).
 *
 * Stateless: each method connects, does one bundled operation and
 * disconnects. Credentials are never persisted or logged.
 */
final class WebklexImapReader implements ImapReader
{
    private const TIMEOUT = 15;

    public function listFolders(ImapCredentials $c): array
    {
        $client = $this->connect($c);
        try {
            // Raw LIST flags per folder (webklex's Folder discards the
            // SPECIAL-USE attributes we need to tell standard folders apart).
            $flagsByPath = [];
            try {
                foreach ($client->getConnection()->folders('', '*')->validatedData() as $path => $item) {
                    $flagsByPath[$path] = array_map(
                        static fn ($f): string => strtolower(ltrim((string) $f, '\\')),
                        $item['flags'] ?? [],
                    );
                }
            } catch (\Throwable) {
            }

            $out = [];
            foreach ($client->getFolders(false) as $folder) {
                $total = 0;
                $unseen = 0;
                // STATUS on a \Noselect container (e.g. Gmail's "[Gmail]") returns
                // NO; skip it to avoid a wasted round-trip per such folder.
                if (! $folder->no_select) {
                    try {
                        $status = $folder->status();
                        $total = (int) ($status['messages'] ?? 0);
                        $unseen = (int) ($status['unseen'] ?? 0);
                    } catch (\Throwable) {
                    }
                }
                $out[] = [
                    'name' => $folder->name ?: $folder->path,
                    'path' => $folder->path,
                    'delimiter' => $folder->delimiter ?: '/',
                    'selectable' => ! $folder->no_select,
                    'role' => $this->folderRole($folder->name, $folder->path, $flagsByPath[$folder->path] ?? []),
                    'total' => $total,
                    'unseen' => $unseen,
                ];
            }

            return $out;
        } finally {
            $this->close($client);
        }
    }

    /**
     * Classify a mailbox as a standard role from its SPECIAL-USE flags, with a
     * name fallback (EN/DE) for servers that do not advertise SPECIAL-USE.
     * Returns null for user/custom folders.
     */
    private function folderRole(string $name, string $path, array $flags): ?string
    {
        if (strtoupper($path) === 'INBOX' || strtoupper($name) === 'INBOX') {
            return 'inbox';
        }
        foreach (['all', 'archive', 'drafts', 'flagged', 'junk', 'sent', 'trash', 'important'] as $role) {
            if (in_array($role, $flags, true)) {
                return $role;
            }
        }
        $n = mb_strtolower(trim($name));
        $names = [
            'all' => ['all mail', 'alle nachrichten'],
            'sent' => ['sent', 'sent mail', 'gesendet', 'gesendete objekte'],
            'drafts' => ['drafts', 'draft', 'entwürfe', 'entwuerfe'],
            'trash' => ['trash', 'deleted', 'deleted messages', 'deleted items', 'papierkorb', 'gelöschte objekte'],
            'junk' => ['junk', 'spam', 'junk e-mail', 'junk email'],
            'archive' => ['archive', 'archiv'],
            'important' => ['important', 'wichtig'],
            'flagged' => ['starred', 'markiert', 'flagged'],
        ];
        foreach ($names as $role => $aliases) {
            if (in_array($n, $aliases, true)) {
                return $role;
            }
        }

        return null;
    }

    public function createFolder(ImapCredentials $c, string $path): void
    {
        $client = $this->connect($c);
        try {
            $client->createFolder($path);
        } finally {
            $this->close($client);
        }
    }

    public function emptyFolder(ImapCredentials $c, string $path): void
    {
        $client = $this->connect($c);
        try {
            $folder = $client->getFolderByPath($path);
            $client->openFolder($path);
            foreach ($folder->query()->whereAll()->setFetchBody(false)->setFetchFlags(false)->get() as $message) {
                $message->delete(false); // mark \Deleted, expunge once below
            }
            $client->expunge();
        } finally {
            $this->close($client);
        }
    }

    public function listMessages(ImapCredentials $c, string $folder, int $page, int $perPage): array
    {
        $client = $this->connect($c);
        try {
            $mailbox = $client->getFolderByPath($folder);
            $total = 0;
            try {
                $total = (int) ($mailbox->status()['messages'] ?? 0);
            } catch (\Throwable) {
            }

            // whereAll() → "SEARCH ALL": without an explicit criterion webklex
            // sends an empty SEARCH, which some servers reject with
            // "BAD Could not parse command".
            try {
                $messages = $mailbox->query()
                    ->whereAll()
                    ->setFetchBody(false)
                    ->setFetchFlags(true)
                    ->setFetchOrderDesc()
                    ->limit($perPage, max(1, $page))
                    ->get();

                $out = [];
                foreach ($messages as $m) {
                    $out[] = [
                        'uid' => $this->uid($m),
                        'subject' => $this->str($m->getSubject()),
                        'from' => $this->firstAddress($m->getFrom()),
                        'date' => $this->date($m),
                        'seen' => $this->flag($m, 'Seen'),
                        'flagged' => $this->flag($m, 'Flagged'),
                        'answered' => $this->flag($m, 'Answered'),
                        // Attachment detection is intentionally omitted here: probing
                        // it per message forces an extra body/structure fetch and made
                        // large folders take tens of seconds. It is shown when the
                        // message is opened (its parts are fetched then anyway).
                    ];
                }
            } catch (\Throwable $e) {
                // Some servers (notably iCloud) answer "SEARCH ALL" with an empty
                // response, breaking the query above. Fall back to fetching the
                // newest window by message-sequence number — no SEARCH involved.
                $out = $this->listBySequence($client, $folder, max(1, $page), $perPage, $total);
            }

            return [
                'total' => $total,
                'page' => max(1, $page),
                'perPage' => $perPage,
                'uidValidity' => $this->uidValidity($mailbox),
                'messages' => $out,
            ];
        } finally {
            $this->close($client);
        }
    }

    /**
     * List a page of envelopes by message-sequence number, without SEARCH.
     *
     * Fallback for servers (iCloud) that return an empty response to "SEARCH
     * ALL". Highest sequence number = newest message, so the page window is the
     * top slice of [1..total]. Flags + headers are fetched in one round-trip and
     * turned into Message objects to reuse webklex's header decoding.
     *
     * @return list<array{uid:int, subject:string, from:array{name:string,email:string}|null, date:string|null, seen:bool, flagged:bool, answered:bool}>
     */
    private function listBySequence(Client $client, string $folder, int $page, int $perPage, int $total): array
    {
        if ($total < 1) {
            return [];
        }
        $client->openFolder($folder);
        $conn = $client->getConnection();

        $high = $total - ($page - 1) * $perPage;
        if ($high < 1) {
            return [];
        }
        $low = max(1, $high - $perPage + 1);
        $msgns = range($high, $low); // newest first

        $uids = $conn->fetch(['UID'], $msgns, null, IMAP::ST_MSGN)->validatedData();
        $flags = $conn->flags($msgns, IMAP::ST_MSGN)->validatedData();
        $headers = $conn->headers($msgns, 'RFC822', IMAP::ST_MSGN)->validatedData();

        $out = [];
        foreach ($msgns as $n) {
            $uid = (int) trim($this->unwrapFetch($uids[$n] ?? null));
            if ($uid < 1) {
                continue;
            }
            $m = Message::make(
                $uid, null, $client,
                (string) ($headers[$n] ?? ''), '', (array) ($flags[$n] ?? []),
                null, IMAP::ST_UID,
            );
            $out[] = [
                'uid' => $uid,
                'subject' => $this->str($m->getSubject()),
                'from' => $this->firstAddress($m->getFrom()),
                'date' => $this->date($m),
                'seen' => $this->flag($m, 'Seen'),
                'flagged' => $this->flag($m, 'Flagged'),
                'answered' => $this->flag($m, 'Answered'),
            ];
        }

        return $out;
    }

    /**
     * A folder's UIDVALIDITY. UIDs are only meaningful within one UIDVALIDITY
     * (RFC 3501 §2.3.1.1); the client keys cached messages by it so a mailbox
     * that was recreated can never map a stale cached UID to a different message.
     */
    private function uidValidity($mailbox): int
    {
        try {
            return (int) ($mailbox->examine()['uidvalidity'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getMessage(ImapCredentials $c, string $folder, int $uid, bool $markSeen): array
    {
        $client = $this->connect($c);
        try {
            $m = $client->getFolderByPath($folder)->query()->getMessageByUid($uid);

            $attachments = [];
            $i = 0;
            foreach ($m->getAttachments() as $a) {
                $attachments[] = [
                    'id' => $i++,
                    'name' => $this->str($a->getName()) ?: 'attachment',
                    'mime' => (string) ($a->getMimeType() ?? 'application/octet-stream'),
                    'size' => (int) $a->getSize(),
                ];
            }

            $html = $this->str($m->getHTMLBody());
            $text = $this->str($m->getTextBody());

            if ($markSeen) {
                try {
                    $m->setFlag('Seen');
                } catch (\Throwable) {
                }
            }

            return [
                'uid' => $uid,
                'subject' => $this->str($m->getSubject()),
                'from' => $this->firstAddress($m->getFrom()),
                'to' => $this->addresses($m->getTo()),
                'cc' => $this->addresses($m->getCc()),
                'date' => $this->date($m),
                'seen' => $markSeen ? true : $this->flag($m, 'Seen'),
                'html' => $html !== '' ? $html : null,
                'text' => $text !== '' ? $text : null,
                'attachments' => $attachments,
                'rawHeaders' => $this->str($m->getHeader()?->raw ?? ''),
                'uidValidity' => $this->uidValidity($client->getFolderByPath($folder)),
            ];
        } finally {
            $this->close($client);
        }
    }

    public function getAttachment(ImapCredentials $c, string $folder, int $uid, int $attachmentId): array
    {
        $client = $this->connect($c);
        try {
            $m = $client->getFolderByPath($folder)->query()->getMessageByUid($uid);
            $attachments = $m->getAttachments();
            $a = $attachments->get($attachmentId) ?? $attachments[$attachmentId] ?? null;
            if ($a === null) {
                throw new \RuntimeException('attachment not found');
            }

            return [
                'name' => $this->str($a->getName()) ?: 'attachment',
                'mime' => (string) ($a->getMimeType() ?? 'application/octet-stream'),
                'content' => (string) $a->getContent(),
            ];
        } finally {
            $this->close($client);
        }
    }

    public function actOnMessages(ImapCredentials $c, string $folder, array $uids, string $action, ?string $target): array
    {
        $client = $this->connect($c);
        try {
            // Resolve the Trash folder once per batch. A "trash" request must
            // never silently expunge: if none is found, refuse the whole batch.
            $trash = $action === 'trash' ? $this->trashPath($client) : null;
            if ($action === 'trash' && $trash === null) {
                throw new \RuntimeException('No Trash folder found — refusing to permanently delete messages that were only meant to be trashed.');
            }

            $mailbox = $client->getFolderByPath($folder);
            $count = 0;
            foreach ($uids as $uid) {
                $m = $mailbox->query()->getMessageByUid((int) $uid);
                switch ($action) {
                    case 'trash':
                        ($trash !== $folder) ? $m->delete(true, $trash, true) : $m->delete(true);
                        break;
                    case 'delete':
                        $m->delete(true); // permanent expunge (explicitly requested)
                        break;
                    case 'move':
                        if ($target !== null) {
                            $m->move($target);
                        }
                        break;
                    case 'seen':
                        $m->setFlag('Seen');
                        break;
                    case 'unseen':
                        $m->unsetFlag('Seen');
                        break;
                }
                $count++;
            }

            return ['count' => $count];
        } finally {
            $this->close($client);
        }
    }

    public function transferMessages(ImapCredentials $source, string $folder, array $uids, ImapCredentials $target, string $targetFolder): array
    {
        $src = $this->connect($source);
        try {
            $srcFolder = $src->getFolderByPath($folder);
            $trash = $this->trashPath($src);

            $dst = $this->connect($target);
            $count = 0;
            try {
                $dstFolder = $dst->getFolderByPath($targetFolder);
                foreach ($uids as $uid) {
                    $uid = (int) $uid;
                    $message = $srcFolder->query()->getMessageByUid($uid);

                    // Copy verbatim: prefer the full BODY[] literal (headers +
                    // body, byte-for-byte), fall back to header + body. APPEND
                    // needs CRLF line endings.
                    $raw = preg_replace('/\r?\n/', "\r\n", $this->rawMessage($src, $folder, $uid, $message));

                    // Preserve the original flags (\Seen, …) and received time so
                    // the copy is not reset to unread / "now".
                    $flags = $this->appendFlags($message);
                    $internalDate = $this->internalDate($src, $folder, $uid);

                    // appendMessage throws on a protocol error, so reaching the
                    // next line means the target accepted the message.
                    $dstFolder->appendMessage($raw, $flags ?: null, $internalDate);

                    // Remove from the source only into its Trash (recoverable) —
                    // never expunge on a transfer. If no Trash is detected, keep
                    // the source copy so a message can never be lost here.
                    if ($trash !== null && $trash !== $folder) {
                        $message->delete(true, $trash, true);
                    }
                    $count++;
                }
            } finally {
                $this->close($dst);
            }

            return ['count' => $count];
        } finally {
            $this->close($src);
        }
    }

    /**
     * The full RFC822 message, byte-for-byte where the server supports it.
     *
     * Fetching BODY[] returns the exact octets on the wire, so 8bit/MIME parts
     * are copied without re-encoding. Falls back to the parsed header + body
     * (which together form the RFC822 message) if the literal fetch fails.
     */
    private function rawMessage(Client $client, string $folder, int $uid, $message): string
    {
        try {
            $client->openFolder($folder);
            $data = $client->getConnection()->fetch(['BODY[]'], [$uid], null, IMAP::ST_UID)->validatedData();
            $raw = $this->unwrapFetch($data[$uid] ?? null);
            if ($raw !== '') {
                return $raw;
            }
        } catch (\Throwable) {
        }

        $header = rtrim((string) ($message->getHeader()?->raw ?? ''), "\r\n");

        return $header."\r\n\r\n".(string) $message->getRawBody();
    }

    /**
     * The message's system flags mapped to IMAP APPEND form (\Seen, \Answered,
     * \Flagged, \Draft). \Recent is dropped (it cannot be set by a client).
     * Keyword flags are passed through unchanged.
     *
     * @return list<string>
     */
    private function appendFlags($message): array
    {
        $system = ['seen' => '\\Seen', 'answered' => '\\Answered', 'flagged' => '\\Flagged', 'draft' => '\\Draft'];
        $out = [];
        try {
            foreach ($message->getFlags()->all() as $flag) {
                $key = strtolower(ltrim((string) $flag, '\\'));
                if ($key === 'recent' || $key === '') {
                    continue;
                }
                $out[] = $system[$key] ?? (string) $flag;
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique($out));
    }

    /**
     * The message's INTERNALDATE as a Carbon (or null → server uses "now").
     *
     * Returning a Carbon lets webklex emit the exact RFC 3501 APPEND date-time
     * ("d-M-Y H:i:s O"); passing the raw FETCH string through unchanged made
     * strict servers (e.g. iCloud) reject the APPEND with "BAD Invalid date
     * format".
     */
    private function internalDate(Client $client, string $folder, int $uid): ?Carbon
    {
        try {
            $client->openFolder($folder);
            $data = $client->getConnection()->fetch(['INTERNALDATE'], [$uid], null, IMAP::ST_UID)->validatedData();
            $raw = trim($this->unwrapFetch($data[$uid] ?? null), " \t\n\r\0\x0B\"");
            if ($raw !== '') {
                return Carbon::parse($raw);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /** Normalise a webklex fetch value (string, or [item => value]) to a string. */
    private function unwrapFetch($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) ? $value : '';
    }

    /* ---- helpers ---- */

    private function connect(ImapCredentials $c): Client
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
            // Do not parse headers with ext-imap's imap_rfc822_parse_headers:
            // on some servers (iCloud) it yields empty/garbled results — the
            // cause of "GetMessagesFailedException: Empty response" — and emits
            // c-client warnings. webklex's own parser is used instead.
            'options' => ['rfc822' => false],
        ]);
        $client->connect();

        // Some providers (notably iCloud) return empty responses to later
        // commands unless the client sends an IMAP ID first. Best-effort.
        try {
            $client->getConnection()->ID(['name' => 'Ledgerline']);
        } catch (\Throwable) {
        }

        // Malformed sender/recipient headers (e.g. "undisclosed-recipients:;")
        // make the address parser emit E_WARNING noise. Swallow non-fatal
        // warnings for the duration of the IMAP session; errors still throw.
        set_error_handler(static fn (): bool => true, E_WARNING | E_NOTICE | E_DEPRECATED);

        return $client;
    }

    private function close(Client $client): void
    {
        restore_error_handler();
        // Drain the c-client error queue so ext-imap does not dump accumulated
        // "Unterminated mailbox" / address-parse warnings at request shutdown.
        if (function_exists('imap_errors')) {
            @imap_errors();
            @imap_alerts();
        }
        try {
            $client->disconnect();
        } catch (\Throwable) {
        }
    }

    private function trashPath(Client $client): ?string
    {
        try {
            // Prefer the \Trash SPECIAL-USE flag (RFC 6154); fall back to the
            // EN/DE name map. Same resolution as the sidebar's folder roles, so
            // a localized or oddly-named Trash is still found.
            $flagsByPath = [];
            try {
                foreach ($client->getConnection()->folders('', '*')->validatedData() as $path => $item) {
                    $flagsByPath[$path] = array_map(
                        static fn ($f): string => strtolower(ltrim((string) $f, '\\')),
                        $item['flags'] ?? [],
                    );
                }
            } catch (\Throwable) {
            }

            foreach ($client->getFolders(false) as $folder) {
                if ($this->folderRole($folder->name ?? '', $folder->path ?? '', $flagsByPath[$folder->path] ?? []) === 'trash') {
                    return $folder->path;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function uid($message): int
    {
        try {
            return (int) $message->getUid();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function str($value): string
    {
        return trim((string) $value);
    }

    private function flag($message, string $flag): bool
    {
        try {
            return $message->hasFlag($flag);
        } catch (\Throwable) {
            return false;
        }
    }

    private function date($message): ?string
    {
        try {
            $value = $message->getDate()?->first();
            if (! $value) {
                return null;
            }

            return method_exists($value, 'toIso8601String') ? $value->toIso8601String() : (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{name:string, email:string}|null
     */
    private function firstAddress($attribute): ?array
    {
        return $this->addresses($attribute)[0] ?? null;
    }

    /**
     * webklex returns addresses as an Attribute (ArrayAccess, not iterable);
     * read the underlying array via all().
     *
     * @return list<array{name:string, email:string}>
     */
    private function addresses($attribute): array
    {
        $out = [];
        try {
            foreach ($attribute?->all() ?? [] as $a) {
                $out[] = ['name' => $this->str($a->personal ?? ''), 'email' => $this->str($a->mail ?? '')];
            }
        } catch (\Throwable) {
        }

        return $out;
    }
}
