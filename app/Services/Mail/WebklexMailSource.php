<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

/**
 * IMAP-backed MailSource for the archiver (pure-PHP webklex client). Mirrors the
 * connection handling of WebklexImapReader. This is untested integration glue;
 * the archive/deletion logic it feeds lives in MailArchiver and is tested with
 * a fake source.
 */
class WebklexMailSource implements MailSource
{
    private const TIMEOUT = 30;

    /** Largest plain-text body stored for content search (bytes/chars). */
    private const BODY_TEXT_CAP = 100000;

    /** Message-sequence enumeration batch size (iCloud SEARCH-ALL fallback). */
    private const SEQ_CHUNK = 1000;

    public function folders(ImapCredentials $c): array
    {
        $client = $this->connect($c);
        try {
            $out = [];
            foreach ($client->getFolders(false) as $folder) {
                if ($folder->no_select) {
                    continue;
                }
                // Reading UIDVALIDITY selects the folder; a server that answers
                // with an empty response for a quirky folder (seen on iCloud)
                // must not abort enumeration of the others — skip it.
                try {
                    $uidValidity = $this->uidValidity($client, $folder->path);
                } catch (\Throwable) {
                    continue;
                }
                $out[] = [
                    'path' => $folder->path,
                    'name' => $folder->name ?: $folder->path,
                    'delimiter' => (string) ($folder->delimiter ?: '/'),
                    'role' => $this->role($folder->name, $folder->path),
                    'uidvalidity' => $uidValidity,
                ];
            }

            return $out;
        } finally {
            $this->close($client);
        }
    }

    public function uids(ImapCredentials $c, string $folder): array
    {
        $client = $this->connect($c);
        try {
            try {
                $out = [];
                $messages = $client->getFolderByPath($folder)->query()
                    ->whereAll()->setFetchBody(false)->setFetchFlags(true)->get();
                foreach ($messages as $m) {
                    $out[(int) $m->getUid()] = [
                        'seen' => $this->flag($m, 'Seen'),
                        'flagged' => $this->flag($m, 'Flagged'),
                        'answered' => $this->flag($m, 'Answered'),
                    ];
                }

                return $out;
            } catch (\Throwable) {
                // Some servers (notably iCloud, e.g. its "Archive" folder) answer
                // "SEARCH ALL" with an empty response. Enumerate by message-
                // sequence number instead — no SEARCH — in bounded chunks so a
                // huge folder also never loads all messages into memory at once.
                return $this->uidsBySequence($client, $folder);
            }
        } finally {
            $this->close($client);
        }
    }

    /**
     * Enumerate a folder's UIDs + flags by message-sequence number, chunked.
     * Fallback for servers that reject "SEARCH ALL"; also caps memory use.
     *
     * @return array<int, array{seen:bool, flagged:bool, answered:bool}>
     */
    private function uidsBySequence(Client $client, string $folder): array
    {
        $mailbox = $client->getFolderByPath($folder);
        $total = 0;
        try {
            $total = (int) ($mailbox->status()['messages'] ?? 0);
        } catch (\Throwable) {
        }
        if ($total < 1) {
            return [];
        }

        $client->openFolder($folder);
        $conn = $client->getConnection();

        $out = [];
        for ($low = 1; $low <= $total; $low += self::SEQ_CHUNK) {
            $high = min($low + self::SEQ_CHUNK - 1, $total);
            $msgns = range($low, $high);
            $uids = $conn->fetch(['UID'], $msgns, null, IMAP::ST_MSGN)->validatedData();
            $flags = $conn->flags($msgns, IMAP::ST_MSGN)->validatedData();
            foreach ($msgns as $n) {
                $uid = (int) trim($this->unwrapFetch($uids[$n] ?? null));
                if ($uid < 1) {
                    continue;
                }
                $out[$uid] = $this->flagsFrom($flags[$n] ?? []);
            }
        }

        return $out;
    }

    /** @return array{seen:bool, flagged:bool, answered:bool} */
    private function flagsFrom(mixed $raw): array
    {
        $flags = array_map(static fn ($f): string => strtolower((string) $f), (array) $raw);
        $has = static fn (string $name): bool => in_array('\\'.$name, $flags, true) || in_array($name, $flags, true);

        return ['seen' => $has('seen'), 'flagged' => $has('flagged'), 'answered' => $has('answered')];
    }

    private function unwrapFetch(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) ? $value : '';
    }

    public function fetch(ImapCredentials $c, string $folder, int $uid): array
    {
        $client = $this->connect($c);
        try {
            // leaveUnread() = fetch with BODY.PEEK so archiving never sets \Seen
            // on the server — otherwise new mail shows as read on other devices.
            $m = $client->getFolderByPath($folder)->query()->leaveUnread()->getMessageByUid($uid);

            $from = $m->getFrom()[0] ?? null;
            $to = $this->addresses($m->getTo());
            $cc = $this->addresses($m->getCc());
            $text = $this->str($m->getTextBody());
            $attachmentNames = [];
            foreach ($m->getAttachments() as $a) {
                $attachmentNames[] = MimeHeader::decode($this->str($a->getName())) ?: 'attachment';
            }

            return [
                'raw' => $this->raw($client, $folder, $uid, $m),
                'message_id' => $this->str($m->getMessageId()) ?: null,
                'subject' => MimeHeader::decode($this->str($m->getSubject())) ?: null,
                'from_name' => $from ? (MimeHeader::decode($this->str($from->personal ?? '')) ?: null) : null,
                'from_email' => $from ? ($this->str($from->mail ?? '') ?: null) : null,
                'to' => $to,
                'cc' => $cc,
                'date' => $this->date($m),
                'has_attachments' => $attachmentNames !== [],
                'attachment_names' => $attachmentNames,
                'size' => (int) ($m->getSize() ?: 0),
                'preview' => $text !== '' ? mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 200) : null,
                // Capped plain-text body for content search.
                'body_text' => $text !== '' ? mb_substr($text, 0, self::BODY_TEXT_CAP) : null,
            ];
        } finally {
            $this->close($client);
        }
    }

    public function appendMessage(ImapCredentials $c, string $folder, string $raw): void
    {
        $client = $this->connect($c);
        try {
            $client->getFolderByPath($folder)->appendMessage($raw, null, null);
        } finally {
            $this->close($client);
        }
    }

    /* ---- helpers ---- */

    private function raw(Client $client, string $folder, int $uid, $message): string
    {
        try {
            $client->openFolder($folder);
            // BODY.PEEK[] fetches the raw message WITHOUT setting the \Seen flag.
            $data = $client->getConnection()->fetch(['BODY.PEEK[]'], [$uid], null, IMAP::ST_UID)->validatedData();
            $val = $data[$uid]['BODY[]'] ?? $data[$uid] ?? null;
            if (is_array($val)) {
                $val = reset($val);
            }
            if (is_string($val) && $val !== '') {
                return $val;
            }
        } catch (\Throwable) {
        }

        $header = rtrim((string) ($message->getHeader()?->raw ?? ''), "\r\n");

        return $header."\r\n\r\n".(string) $message->getRawBody();
    }

    private function uidValidity(Client $client, string $path): int
    {
        try {
            $status = $client->getFolderByPath($path)->status();

            return (int) ($status['uidvalidity'] ?? $status['UIDVALIDITY'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function flag($message, string $flag): bool
    {
        try {
            foreach ($message->getFlags()->all() as $f) {
                if (strcasecmp(ltrim((string) $f, '\\'), $flag) === 0) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function date($message): ?string
    {
        try {
            $d = $message->getDate()?->first() ?? $message->getDate();

            return $d ? (string) $d : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Classify a folder to a standard role from common EN/DE names. */
    private function role(?string $name, string $path): ?string
    {
        $hay = strtolower($name.' '.$path);
        $map = [
            'inbox' => ['inbox', 'posteingang'],
            'sent' => ['sent', 'gesendet'],
            'drafts' => ['draft', 'entw'],
            'trash' => ['trash', 'deleted', 'papierkorb', 'gel'],
            'junk' => ['junk', 'spam'],
            'archive' => ['archive', 'archiv'],
        ];
        foreach ($map as $role => $needles) {
            foreach ($needles as $n) {
                if (str_contains($hay, $n)) {
                    return $role;
                }
            }
        }

        return null;
    }

    private function str($v): string
    {
        return trim((string) ($v ?? ''));
    }

    /**
     * @param  iterable<object>|null  $list
     * @return list<array{name:?string, email:string}>
     */
    private function addresses($list): array
    {
        $out = [];
        foreach ($list ?? [] as $addr) {
            $out[] = ['name' => MimeHeader::decode($this->str($addr->personal ?? '')) ?: null, 'email' => $this->str($addr->mail ?? '')];
        }

        return $out;
    }

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
            'options' => ['rfc822' => false],
        ]);
        $client->connect();
        try {
            $client->getConnection()->ID(['name' => 'Ledgerline']);
        } catch (\Throwable) {
        }
        set_error_handler(static fn (): bool => true, E_WARNING | E_NOTICE | E_DEPRECATED);

        return $client;
    }

    private function close(Client $client): void
    {
        restore_error_handler();
        try {
            $client->disconnect();
        } catch (\Throwable) {
        }
    }
}
