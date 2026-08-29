<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecordPaymentData
{
    private const MAX_MINOR = 99_999_999_999_999;

    public function __construct(
        public int $amountMinor,
        public string $currency,
        public DateTimeImmutable $receivedAt,
        public ?string $reference = null,
        public ?string $counterparty = null,
        public ?int $paymentMethodId = null,
        public ?string $sourceType = null,
        public ?string $sourceKey = null,
    ) {
        if ($amountMinor === 0) {
            throw new InvalidArgumentException('Payment amount must not be zero.');
        }
        if ($amountMinor > self::MAX_MINOR || $amountMinor < -self::MAX_MINOR) {
            throw new InvalidArgumentException('Payment amount exceeds the supported minor-unit range.');
        }
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new InvalidArgumentException('Payment currency must be uppercase ISO format.');
        }
        if ($paymentMethodId !== null && $paymentMethodId < 1) {
            throw new InvalidArgumentException('Payment method IDs must be positive.');
        }
        if (($sourceType === null) !== ($sourceKey === null)) {
            throw new InvalidArgumentException('Payment source type and key must be supplied together.');
        }
        foreach ([['reference', $reference, 255], ['counterparty', $counterparty, 255]] as [$field, $value, $limit]) {
            if ($value !== null && (trim($value) === '' || strlen($value) > $limit)) {
                throw new InvalidArgumentException("Payment {$field} must contain between 1 and {$limit} bytes.");
            }
        }
        if ($sourceType !== null && (trim($sourceType) === '' || strlen($sourceType) > 64)) {
            throw new InvalidArgumentException('Payment source type must contain between 1 and 64 bytes.');
        }
        if ($sourceKey !== null && (trim($sourceKey) === '' || strlen($sourceKey) > 255)) {
            throw new InvalidArgumentException('Payment source key must contain between 1 and 255 bytes.');
        }
    }
}
