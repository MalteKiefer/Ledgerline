<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Payments;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use InvalidArgumentException;

final readonly class ReversePaymentAllocation
{
    public function __construct(private PaymentRepository $payments) {}

    public function handle(
        AllocationId $allocation,
        IdempotencyKey $key,
        ?int $expectedPaymentVersion = null,
    ): AllocationResult {
        if ($expectedPaymentVersion !== null && $expectedPaymentVersion < 0) {
            throw new InvalidArgumentException('Expected payment version must not be negative.');
        }

        return $this->payments->reverse($allocation, $key, $expectedPaymentVersion);
    }
}
