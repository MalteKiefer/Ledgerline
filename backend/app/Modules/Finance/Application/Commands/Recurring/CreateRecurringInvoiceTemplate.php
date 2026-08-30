<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Recurring;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;

final readonly class CreateRecurringInvoiceTemplate
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    public function handle(RecurringTemplateData $data, IdempotencyKey $key): RecurringTemplateView
    {
        return $this->templates->createTemplate($data, $key);
    }
}
