<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\ComposedMail;

/**
 * An outbound message the user composed (compose / reply / forward), ready to be
 * handed to {@see MailSender}. Immutable value object — the controller builds it
 * from validated request input (recipients, subject, bodies, attachments) and
 * the origin message (reply/forward threading headers). The raw bodies are the
 * user's own content and are sent verbatim (never Blade-rendered — see
 * {@see ComposedMail}).
 */
final class ComposedMessage
{
    /**
     * @param  list<array{name:?string, email:string}>  $to
     * @param  list<array{name:?string, email:string}>  $cc
     * @param  list<array{name:?string, email:string}>  $bcc
     * @param  list<string>  $references  RFC822 References chain (reply threading).
     * @param  list<array{bytes:string, filename:string, mime:string}>  $attachments
     */
    public function __construct(
        public string $subject,
        public ?string $text,
        public ?string $html,
        public string $fromEmail,
        public ?string $fromName,
        public array $to,
        public array $cc = [],
        public array $bcc = [],
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public array $references = [],
        public array $attachments = [],
        public string $sentFolder = 'Sent',
    ) {}

    /** True when at least one recipient (to/cc/bcc) is present. */
    public function hasRecipient(): bool
    {
        return $this->to !== [] || $this->cc !== [] || $this->bcc !== [];
    }
}
