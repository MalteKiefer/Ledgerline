<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Payments;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Application\Ports\PaymentRepository;

final readonly class RecordPayment
{
    public function __construct(private PaymentRepository $payments) {}

    public function handle(RecordPaymentData $data, IdempotencyKey $key): PaymentView
    {
        return $this->payments->record($data, $key);
    }
}
