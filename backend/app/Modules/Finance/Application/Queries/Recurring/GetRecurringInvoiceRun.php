<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Recurring;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunView;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;

final readonly class GetRecurringInvoiceRun
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    public function handle(RecurringRunId $id): RecurringRunView
    {
        return $this->templates->getRunView($id);
    }
}
