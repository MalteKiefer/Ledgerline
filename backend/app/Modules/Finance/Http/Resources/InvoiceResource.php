<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceView */
final class InvoiceResource extends JsonResource
{
    public function __construct(private readonly InvoiceView $invoice)
    {
        parent::__construct($invoice);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invoice = $this->invoice;
        $paidMinor = max(0, $invoice->allocatedMinor);

        return FinanceWireValues::exactIntegerStrings([
            'id' => $invoice->uuid,
            'kind' => $invoice->kind,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issueDate->format('Y-m-d'),
            'due_date' => $invoice->dueDate->format('Y-m-d'),
            'partner_id' => $invoice->partnerId,
            'project_id' => $invoice->projectId,
            'totals' => [
                'net_minor' => $invoice->netMinor,
                'vat_minor' => $invoice->vatMinor,
                'gross_minor' => $invoice->grossMinor,
                'currency' => $invoice->currency,
            ],
            'allocated_minor' => $invoice->allocatedMinor,
            'paid_minor' => $paidMinor,
            'open_minor' => $invoice->openMinor,
            'source' => $invoice->sourceType === null ? null : [
                'type' => $invoice->sourceType,
                'key' => $invoice->sourceKey,
            ],
            'snapshot' => $invoice->snapshot,
            'version' => $invoice->version,
            'created_at' => $invoice->createdAt->format(DATE_ATOM),
            'updated_at' => $invoice->updatedAt->format(DATE_ATOM),
        ]);
    }
}
