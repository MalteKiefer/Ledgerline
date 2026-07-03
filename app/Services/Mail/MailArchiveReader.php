<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Webklex\PHPIMAP\Message;

/**
 * Renders an archived message from its stored raw .eml (no IMAP connection).
 * Parsing raw MIME is done server-side (correctness + not shipping a MIME
 * parser to every client).
 */
class MailArchiveReader
{
    /** @return array<string,mixed> */
    public function parse(string $raw): array
    {
        $m = Message::fromString($raw);

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

        $from = $m->getFrom()[0] ?? null;
        $html = $this->str($m->getHTMLBody());
        $text = $this->str($m->getTextBody());

        return [
            'subject' => $this->str($m->getSubject()),
            'from' => $from ? ['name' => $this->str($from->personal ?? ''), 'email' => $this->str($from->mail ?? '')] : null,
            'to' => $this->addresses($m->getTo()),
            'cc' => $this->addresses($m->getCc()),
            'date' => $this->date($m),
            'html' => $html !== '' ? $html : null,
            'text' => $text !== '' ? $text : null,
            'attachments' => $attachments,
        ];
    }

    /** @return array{name:string, mime:string, content:string}|null */
    public function attachment(string $raw, int $index): ?array
    {
        $m = Message::fromString($raw);
        $attachments = $m->getAttachments();
        $a = $attachments->get($index) ?? $attachments[$index] ?? null;
        if ($a === null) {
            return null;
        }

        return [
            'name' => $this->str($a->getName()) ?: 'attachment',
            'mime' => (string) ($a->getMimeType() ?? 'application/octet-stream'),
            'content' => (string) $a->getContent(),
        ];
    }

    /** @return list<array{name:?string, email:string}> */
    private function addresses($list): array
    {
        $out = [];
        foreach ($list ?? [] as $a) {
            $out[] = ['name' => $this->str($a->personal ?? '') ?: null, 'email' => $this->str($a->mail ?? '')];
        }

        return $out;
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

    private function str($v): string
    {
        return trim((string) ($v ?? ''));
    }
}
