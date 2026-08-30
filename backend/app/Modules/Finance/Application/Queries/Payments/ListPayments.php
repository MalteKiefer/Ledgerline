<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Payments;

use App\Modules\Finance\Application\DTOs\Payments\PaymentPage;
use App\Modules\Finance\Application\Ports\PaymentRepository;

final readonly class ListPayments
{
    public function __construct(private PaymentRepository $payments) {}

    /** @param array<string, mixed> $filters */
    public function handle(array $filters, int $page, int $perPage): PaymentPage
    {
        return $this->payments->page($filters, $page, $perPage);
    }
}
