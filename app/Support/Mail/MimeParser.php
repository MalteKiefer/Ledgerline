<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Services\Mail\ParsedAttachment;
use App\Services\Mail\ParsedMessage;
use Illuminate\Support\Carbon;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Thin server-side wrapper over zbateson/mail-mime-parser: turns a raw RFC822
 * message into a normalised App\Services\Mail\ParsedMessage (denormalised
 * envelope + text/HTML body + attachment count + parsed Authentication-Results).
 * Pure-PHP, no ext-mailparse, no shell-out. The raw .eml remains authoritative
 * on disk; this only feeds the denormalised columns + search index.
 */
final class MimeParser
{
    /** Parse raw RFC822 bytes into a normalised envelope + bodies. */
    public function parse(string $raw): ParsedMessage
    {
        $message = (new MailMimeParser)->parse($raw, true);

        [$fromName, $fromEmail] = $this->firstAddress($message->getHeader('from'));
        $to = $this->addressList($message->getHeader('to'));
        $cc = $this->addressList($message->getHeader('cc'));
        [, $replyTo] = $this->firstAddress($message->getHeader('reply-to'));

        $date = null;
        $dateHeader = $message->getHeader('date');
        if ($dateHeader instanceof DateHeader) {
            $dt = $dateHeader->getDateTimeImmutable();
            if ($dt !== null) {
                $date = Carbon::instance($dt);
            }
        }

        // The RAW header value — getHeaderValue() truncates a structured header
        // (Authentication-Results) at its authserv-id, dropping the spf/dkim/dmarc parts.
        $authHeader = $message->getHeader('authentication-results');
        $auth = $this->parseAuthResults($authHeader?->getRawValue());

        return new ParsedMessage(
            messageId: $this->trimHeader($message->getHeaderValue('message-id')),
            inReplyTo: $this->trimHeader($message->getHeaderValue('in-reply-to')),
            references: $this->trimHeader($message->getHeaderValue('references')),
            subject: $this->trimHeader($message->getSubject()),
            fromName: $fromName,
            fromEmail: $fromEmail,
            to: $to,
            cc: $cc,
            replyTo: $replyTo,
            date: $date,
            textBody: $this->nullIfBlank($message->getTextContent()),
            htmlBody: $this->nullIfBlank($message->getHtmlContent()),
            attachmentCount: $message->getAttachmentCount(),
            spf: $auth['spf'],
            dkim: $auth['dkim'],
            dmarc: $auth['dmarc'],
            attachments: $this->attachments($message),
        );
    }

    /**
     * Every attachment part (real + inline/cid) with its decoded bytes. An
     * attachment is "inline" when its Content-Disposition is inline or it
     * carries a Content-Id (cid: reference from an HTML body). A part whose
     * bytes cannot be read is skipped rather than aborting the whole parse.
     *
     * @return list<ParsedAttachment>
     */
    private function attachments(IMessage $message): array
    {
        $out = [];
        foreach ($message->getAllAttachmentParts() as $part) {
            if (! $part instanceof IMessagePart) {
                continue;
            }

            $stream = $part->getBinaryContentStream();
            if ($stream === null) {
                continue;
            }
            $bytes = $stream->getContents();

            $contentId = $this->normalizeContentId($part->getContentId());
            $disposition = strtolower(trim((string) $part->getContentDisposition()));

            $out[] = new ParsedAttachment(
                filename: $this->nullIfBlank($part->getFilename()),
                contentType: $this->nullIfBlank($part->getContentType()),
                contentId: $contentId,
                inline: $disposition === 'inline' || $contentId !== null,
                bytes: $bytes,
            );
        }

        return $out;
    }

    /** A Content-Id with the surrounding angle brackets stripped, or null. */
    private function normalizeContentId(?string $value): ?string
    {
        $v = $this->nullIfBlank($value);
        if ($v === null) {
            return null;
        }

        return $this->nullIfBlank(trim($v, '<>'));
    }

    /**
     * The first address of a header as [name, email], or [null, null] when the
     * header is absent / not an address header / empty.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function firstAddress(mixed $header): array
    {
        if (! $header instanceof AddressHeader) {
            return [null, null];
        }

        foreach ($header->getAddresses() as $addr) {
            $email = trim($addr->getEmail());
            if ($email === '') {
                continue;
            }

            return [$this->nullIfBlank($addr->getName()), $email];
        }

        return [null, null];
    }

    /**
     * Every address of a header as a list of {name, email}, empty emails skipped.
     *
     * @return list<array{name:?string, email:string}>
     */
    private function addressList(mixed $header): array
    {
        if (! $header instanceof AddressHeader) {
            return [];
        }

        $out = [];
        foreach ($header->getAddresses() as $addr) {
            $email = trim($addr->getEmail());
            if ($email === '') {
                continue;
            }
            $out[] = ['name' => $this->nullIfBlank($addr->getName()), 'email' => $email];
        }

        return $out;
    }

    /**
     * Extract the per-mechanism verdicts from an Authentication-Results header
     * ("spf=pass ... dkim=pass ... dmarc=pass"). Each verdict is capped at 16
     * chars (the column width). Returns nulls for any mechanism not present.
     *
     * @return array{spf:?string, dkim:?string, dmarc:?string}
     */
    private function parseAuthResults(?string $value): array
    {
        $out = ['spf' => null, 'dkim' => null, 'dmarc' => null];
        if ($value === null || $value === '') {
            return $out;
        }

        foreach (['spf', 'dkim', 'dmarc'] as $mech) {
            if (preg_match('/\b'.$mech.'=([a-zA-Z]+)/', $value, $m) === 1) {
                $out[$mech] = mb_substr(strtolower($m[1]), 0, 16);
            }
        }

        return $out;
    }

    private function trimHeader(?string $value): ?string
    {
        return $this->nullIfBlank($value);
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
