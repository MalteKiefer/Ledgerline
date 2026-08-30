<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringRunView */
final class RecurringInvoiceRunResource extends JsonResource
{
    public function __construct(private readonly RecurringRunView $run)
    {
        parent::__construct($run);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $run = $this->run;

        return [
            'id' => $run->uuid,
            'scheduled_for' => $run->scheduledFor->format(DATE_ATOM),
            'scheduled_local_date' => $run->scheduledLocalDate,
            'status' => $run->status,
            'last_completed_step' => $run->lastCompletedStep,
            'attempts' => $run->attempts,
            'claimed_at' => $run->claimedAt?->format(DATE_ATOM),
            'claim_expires_at' => $run->claimExpiresAt?->format(DATE_ATOM),
            'next_retry_at' => $run->nextRetryAt?->format(DATE_ATOM),
            'last_error_code' => $run->lastErrorCode,
            'created_at' => $run->createdAt->format(DATE_ATOM),
            'updated_at' => $run->updatedAt->format(DATE_ATOM),
        ];
    }
}
