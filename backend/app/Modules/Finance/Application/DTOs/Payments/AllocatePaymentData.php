<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use InvalidArgumentException;

final readonly class AllocatePaymentData
{
    /** @param list<AllocationLineData> $lines */
    public function __construct(
        public PaymentId $paymentId,
        public array $lines,
        public ?int $expectedVersion = null,
    ) {
        if ($lines === []) {
            throw new InvalidArgumentException('Payment allocation requires at least one line.');
        }
        if ($expectedVersion !== null && $expectedVersion < 0) {
            throw new InvalidArgumentException('Expected payment version must not be negative.');
        }
        $invoiceIds = [];
        foreach ($lines as $line) {
            if (! $line instanceof AllocationLineData) {
                throw new InvalidArgumentException('Every allocation line must be AllocationLineData.');
            }
            if (isset($invoiceIds[$line->invoiceId->value])) {
                throw new InvalidArgumentException('Payment allocation invoice IDs must be unique.');
            }
            $invoiceIds[$line->invoiceId->value] = true;
        }
    }
}
