<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Recurring;

use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use DateTimeImmutable;

final readonly class ClaimDueRecurringInvoiceRuns
{
    public const int GLOBAL_CAP = 1_000;

    public const int PER_TEMPLATE_CAP = 100;

    public function __construct(private RecurringInvoiceRepository $templates) {}

    /** @return list<array{run_id: int, owner_id: int, uuid: string}> */
    public function handle(DateTimeImmutable $asOf): array
    {
        return $this->templates->claimDueRuns($asOf, self::GLOBAL_CAP, self::PER_TEMPLATE_CAP);
    }
}
