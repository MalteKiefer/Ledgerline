<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Outcome of a {@see MailSender} send: the generated RFC822 Message-Id of the
 * sent message and whether the sent copy was successfully appended to the origin
 * Sent folder (best-effort — a failed IMAP append never fails the send).
 */
final class SendResult
{
    public function __construct(
        public string $messageId,
        public bool $appendedToSent,
    ) {}
}
