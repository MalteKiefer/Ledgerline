<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringTemplateView */
final class RecurringInvoiceTemplateResource extends JsonResource
{
    public function __construct(private readonly RecurringTemplateView $template)
    {
        parent::__construct($template);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $template = $this->template;

        return [
            'id' => $template->uuid,
            'mode' => $template->mode,
            'interval' => $template->interval,
            'timezone' => $template->timezone,
            'start_date' => $template->startDate,
            'end_date' => $template->endDate,
            'run_time' => $template->runTime,
            'anchor_day' => $template->anchorDay,
            'month_end_anchor' => $template->monthEndAnchor,
            'next_run_at' => $template->nextRunAt->format(DATE_ATOM),
            'status' => $template->status,
            'paused_at' => $template->pausedAt?->format(DATE_ATOM),
            'current_version' => [
                'number' => $template->currentVersionNumber,
                'effective_from' => $template->currentEffectiveFrom,
                'snapshot_sha256' => $template->currentSnapshotSha256,
            ],
            'version' => $template->version,
        ];
    }
}
