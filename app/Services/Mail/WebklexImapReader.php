<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

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
            $out = [];
            foreach ($client->getFolders(false) as $folder) {
                $total = 0;
                $unseen = 0;
                try {
                    $status = $folder->status();
                    $total = (int) ($status['messages'] ?? 0);
                    $unseen = (int) ($status['unseen'] ?? 0);
                } catch (\Throwable) {
                    // \Noselect containers etc. — still list them.
                }
                $out[] = ['name' => $folder->name ?: $folder->path, 'path' => $folder->path, 'total' => $total, 'unseen' => $unseen];
            }

            return $out;
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

            return ['total' => $total, 'page' => max(1, $page), 'perPage' => $perPage, 'messages' => $out];
        } finally {
            $this->close($client);
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

    public function deleteMessage(ImapCredentials $c, string $folder, int $uid, bool $permanent): array
    {
        $client = $this->connect($c);
        try {
            $m = $client->getFolderByPath($folder)->query()->getMessageByUid($uid);
            $trash = $permanent ? null : $this->trashPath($client);

            if ($trash !== null && $trash !== $folder) {
                $m->delete(true, $trash, true); // move to Trash

                return ['deleted' => true, 'trashed' => true];
            }

            $m->delete(true); // permanent expunge

            return ['deleted' => true, 'trashed' => false];
        } finally {
            $this->close($client);
        }
    }

    public function moveMessage(ImapCredentials $c, string $folder, int $uid, string $targetFolder): void
    {
        $client = $this->connect($c);
        try {
            $client->getFolderByPath($folder)->query()->getMessageByUid($uid)->move($targetFolder);
        } finally {
            $this->close($client);
        }
    }

    public function flagMessage(ImapCredentials $c, string $folder, int $uid, bool $seen): void
    {
        $client = $this->connect($c);
        try {
            $m = $client->getFolderByPath($folder)->query()->getMessageByUid($uid);
            $seen ? $m->setFlag('Seen') : $m->unsetFlag('Seen');
        } finally {
            $this->close($client);
        }
    }

    public function transferMessage(ImapCredentials $source, string $folder, int $uid, ImapCredentials $target, string $targetFolder): void
    {
        $src = $this->connect($source);
        try {
            $message = $src->getFolderByPath($folder)->query()->getMessageByUid($uid);
            // IMAP APPEND requires CRLF line endings; a bare-LF raw message makes
            // strict servers reject the command.
            $raw = preg_replace('/\r?\n/', "\r\n", (string) $message->getRawBody());

            $dst = $this->connect($target);
            try {
                $dst->getFolderByPath($targetFolder)->appendMessage($raw);
            } finally {
                $this->close($dst);
            }

            // Only remove from the source once the append succeeded.
            $trash = $this->trashPath($src);
            $trash !== null && $trash !== $folder ? $message->delete(true, $trash, true) : $message->delete(true);
        } finally {
            $this->close($src);
        }
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
        ]);
        $client->connect();

        return $client;
    }

    private function close(Client $client): void
    {
        try {
            $client->disconnect();
        } catch (\Throwable) {
        }
    }

    private function trashPath(Client $client): ?string
    {
        try {
            foreach ($client->getFolders(false) as $folder) {
                if (preg_match('/^(trash|deleted|papierkorb|deleted items|deleted messages)$/i', $folder->name ?? '')) {
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
