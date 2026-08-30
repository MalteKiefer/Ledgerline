<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Recurring;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;

final readonly class GetRecurringInvoiceTemplate
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    public function handle(RecurringTemplateId $id): RecurringTemplateView
    {
        return $this->templates->getView($id);
    }
}
