<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Payments;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\Ports\PaymentRepository;

final readonly class AllocatePayment
{
    public function __construct(private PaymentRepository $payments) {}

    public function handle(AllocatePaymentData $data, IdempotencyKey $key): AllocationResult
    {
        return $this->payments->allocate($data, $key);
    }
}
