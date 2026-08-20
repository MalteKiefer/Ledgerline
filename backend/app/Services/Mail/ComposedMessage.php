<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\ComposedMail;
use App\Models\CryptoRecipient;
use App\Models\MailPgpKey;

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
        public bool $readReceipt = false,
        public bool $highPriority = false,
        /** @var 'none'|'sign'|'encrypt'|'sign_encrypt' */
        public string $cryptoMode = 'none',
        /** @var 'pgp'|'smime'|null */
        public ?string $cryptoType = null,
        public ?int $signingKeyId = null,
        /** @var list<int> */
        public array $recipientKeyIds = [],
        public ?MailPgpKey $signingKey = null,
        /** @var list<CryptoRecipient> */
        public array $recipientKeys = [],
    ) {}

    /** True when at least one recipient (to/cc/bcc) is present. */
    public function hasRecipient(): bool
    {
        return $this->to !== [] || $this->cc !== [] || $this->bcc !== [];
    }
}
