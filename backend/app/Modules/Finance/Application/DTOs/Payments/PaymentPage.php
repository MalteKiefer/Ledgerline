<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

final readonly class PaymentPage
{
    /** @param list<PaymentView> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
