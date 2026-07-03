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

    public function folders(ImapCredentials $c): array
    {
        $client = $this->connect($c);
        try {
            $out = [];
            foreach ($client->getFolders(false) as $folder) {
                if ($folder->no_select) {
                    continue;
                }
                $out[] = [
                    'path' => $folder->path,
                    'name' => $folder->name ?: $folder->path,
                    'delimiter' => (string) ($folder->delimiter ?: '/'),
                    'role' => $this->role($folder->name, $folder->path),
                    'uidvalidity' => $this->uidValidity($client, $folder->path),
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
        } finally {
            $this->close($client);
        }
    }

    public function fetch(ImapCredentials $c, string $folder, int $uid): array
    {
        $client = $this->connect($c);
        try {
            $m = $client->getFolderByPath($folder)->query()->getMessageByUid($uid);

            $from = $m->getFrom()[0] ?? null;
            $to = [];
            foreach ($m->getTo() as $addr) {
                $to[] = ['name' => $this->str($addr->personal ?? ''), 'email' => $this->str($addr->mail ?? '')];
            }
            $text = $this->str($m->getTextBody());

            return [
                'raw' => $this->raw($client, $folder, $uid, $m),
                'message_id' => $this->str($m->getMessageId()) ?: null,
                'subject' => $this->str($m->getSubject()) ?: null,
                'from_name' => $from ? ($this->str($from->personal ?? '') ?: null) : null,
                'from_email' => $from ? ($this->str($from->mail ?? '') ?: null) : null,
                'to' => $to,
                'date' => $this->date($m),
                'has_attachments' => $m->getAttachments()->count() > 0,
                'size' => (int) ($m->getSize() ?: 0),
                'preview' => $text !== '' ? mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 200) : null,
            ];
        } finally {
            $this->close($client);
        }
    }

    /* ---- helpers ---- */

    private function raw(Client $client, string $folder, int $uid, $message): string
    {
        try {
            $client->openFolder($folder);
            $data = $client->getConnection()->fetch(['BODY[]'], [$uid], null, IMAP::ST_UID)->validatedData();
            $val = $data[$uid] ?? null;
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
        if (function_exists('imap_errors')) {
            @imap_errors();
            @imap_alerts();
        }
        try {
            $client->disconnect();
        } catch (\Throwable) {
        }
    }
}
