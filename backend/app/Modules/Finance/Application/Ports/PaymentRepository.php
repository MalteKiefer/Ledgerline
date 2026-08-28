<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;

interface PaymentRepository
{
    public function get(PaymentId $id): PaymentView;

    public function record(RecordPaymentData $data, IdempotencyKey $key): PaymentView;

    public function allocate(AllocatePaymentData $data, IdempotencyKey $key): AllocationResult;

    public function reverse(AllocationId $id, IdempotencyKey $key): AllocationResult;
}
