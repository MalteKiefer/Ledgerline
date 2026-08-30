<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Recurring;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;

/**
 * Resets a `failed` run back to `pending` so the next processing attempt
 * resumes from its persisted `last_completed_step` instead of skipping the
 * occurrence or creating a duplicate invoice.
 */
final readonly class RetryRecurringInvoiceRun
{
    public function __construct(private RecurringInvoiceRepository $templates) {}

    /** @return array<string, mixed> */
    public function handle(RecurringRunId $id): array
    {
        return $this->templates->transitionRun($id, 'pending', null, null, null, null, null);
    }
}
