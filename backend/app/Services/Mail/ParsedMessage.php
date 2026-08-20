<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Illuminate\Support\Carbon;

/**
 * Normalised, server-parsed view of one RFC822 message. Produced by
 * App\Support\Mail\MimeParser (a thin wrapper over zbateson/mail-mime-parser)
 * and consumed by MaildirIngestor to fill the denormalised mail_messages
 * columns. Header/address/date parsing is done ONCE here; the raw .eml blob
 * remains the authoritative source of truth on disk.
 */
final readonly class ParsedMessage
{
    /**
     * @param  list<array{name:?string, email:string}>  $to
     * @param  list<array{name:?string, email:string}>  $cc
     * @param  list<ParsedAttachment>  $attachments
     */
    public function __construct(
        public ?string $messageId,
        public ?string $inReplyTo,
        public ?string $references,
        public ?string $subject,
        public ?string $fromName,
        public ?string $fromEmail,
        public array $to,
        public array $cc,
        public ?string $replyTo,
        public ?Carbon $date,
        public ?string $textBody,
        public ?string $htmlBody,
        public int $attachmentCount,
        public ?string $spf,
        public ?string $dkim,
        public ?string $dmarc,
        public array $attachments = [],
    ) {}

}
