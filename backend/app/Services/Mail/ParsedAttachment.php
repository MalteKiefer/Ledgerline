<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * One decoded MIME attachment part of a parsed message: its filename, content
 * type, optional Content-Id (for cid: inline images), whether it is inline, and
 * the decoded bytes. Produced by App\Support\Mail\MimeParser and consumed by
 * MaildirIngestor (to write the plaintext attachment blob + row) and by the
 * body renderer (to inline cid: images as data: URIs).
 */
final readonly class ParsedAttachment
{
    public function __construct(
        public ?string $filename,
        public ?string $contentType,
        public ?string $contentId,
        public bool $inline,
        public string $bytes,
    ) {}

    public function size(): int
    {
        return strlen($this->bytes);
    }
}
