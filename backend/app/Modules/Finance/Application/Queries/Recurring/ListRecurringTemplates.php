<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Recurring;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplatePage;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;

final readonly class ListRecurringTemplates
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    /** @param array<string, mixed> $filters */
    public function handle(array $filters, int $page, int $perPage): RecurringTemplatePage
    {
        return $this->templates->templates($filters, $page, $perPage);
    }
}
