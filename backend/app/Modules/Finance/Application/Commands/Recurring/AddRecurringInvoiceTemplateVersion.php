<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Recurring;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use InvalidArgumentException;

final readonly class AddRecurringInvoiceTemplateVersion
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    public function handle(
        RecurringTemplateId $id,
        RecurringTemplateVersionData $data,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected recurring template version must not be negative.');
        }

        return $this->templates->addTemplateVersion($id, $data, $expectedVersion, $key);
    }
}
