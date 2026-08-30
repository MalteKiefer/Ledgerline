<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Recurring;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunPage;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;

final readonly class ListRecurringInvoiceRuns
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    /** @param array<string, mixed> $filters */
    public function handle(RecurringTemplateId $id, array $filters, int $page, int $perPage): RecurringRunPage
    {
        return $this->templates->runsForTemplate($id, $filters, $page, $perPage);
    }
}
