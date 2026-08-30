<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Payments;

use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\Ports\PaymentRepository;

final readonly class GetPayment
{
    public function __construct(private PaymentRepository $payments) {}

    public function handle(PaymentId $id): PaymentView
    {
        return $this->payments->get($id);
    }
}
