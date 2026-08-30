<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Quotes;

use InvalidArgumentException;

final readonly class SendQuoteData
{
    public function __construct(
        public QuoteId $quoteId,
        public int $expectedVersion,
        public string $idempotencyKey,
        public ?string $recipient = null,
        public ?string $changeReason = null,
    ) {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected quote version must not be negative.');
        }
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Quote idempotency key must contain between 1 and 255 bytes.');
        }
        if ($recipient !== null && filter_var(trim($recipient), FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Quote recipient must be a valid email address.');
        }
    }
}
