<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Projects;

use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\LedgerEntryView;
use App\Modules\Finance\Application\DTOs\Projects\TimeEntryView;
use App\Modules\Finance\Application\DTOs\Projects\WorkItemView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectWorkResource extends JsonResource
{
    /**
     * @param  WorkItemView|TimeEntryView|LedgerEntryView|InvoiceDraftTarget|array{items: list<WorkItemView|TimeEntryView|LedgerEntryView>, page: int, per_page: int, total: int}  $value
     */
    public function __construct(private readonly WorkItemView|TimeEntryView|LedgerEntryView|InvoiceDraftTarget|array $value)
    {
        parent::__construct($value);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (is_array($this->value)) {
            $data = [];
            foreach ($this->value['items'] as $item) {
                $data[] = (new self($item))->resolve($request);
            }

            return [
                'data' => $data,
                'meta' => ['current_page' => $this->value['page'], 'per_page' => $this->value['per_page'], 'total' => $this->value['total']],
            ];
        }
        if ($this->value instanceof WorkItemView) {
            return [
                'resource_type' => 'work_item', 'id' => $this->value->uuid, 'title' => $this->value->title,
                'description' => $this->value->description, 'status' => $this->value->status->value,
                'starts_on' => $this->value->startsOn?->format('Y-m-d'), 'due_on' => $this->value->dueOn?->format('Y-m-d'),
                'estimate_quantity_scaled' => $this->value->estimateQuantityScaled === null ? null : (string) $this->value->estimateQuantityScaled,
                'is_milestone' => $this->value->isMilestone, 'sort' => $this->value->sort,
                'product_reference' => $this->value->productReference, 'version' => $this->value->version,
            ];
        }
        if ($this->value instanceof TimeEntryView) {
            return [
                'resource_type' => 'time_entry', 'id' => $this->value->uuid, 'work_item_id' => $this->value->workItemUuid,
                'worked_on' => $this->value->workedOn->format('Y-m-d'), 'quantity_scaled' => (string) $this->value->quantityScaled,
                'description' => $this->value->description, 'billable' => $this->value->billable,
                'hourly_rate_minor' => $this->value->hourlyRateMinor === null ? null : (string) $this->value->hourlyRateMinor,
                'currency' => $this->value->currency, 'invoice_target_reference' => $this->value->invoiceTargetReference,
                'invoiced_at' => $this->value->invoicedAt?->format('Y-m-d\TH:i:s.uP'), 'version' => $this->value->version,
            ];
        }
        if ($this->value instanceof InvoiceDraftTarget) {
            return [
                'target_reference' => $this->value->targetReference,
                'source' => [
                    'source_type' => $this->value->source->sourceType,
                    'source_reference' => $this->value->source->sourceReference,
                    'pinned_revision_id' => $this->value->source->pinnedRevisionId,
                ],
                'navigation_url' => $this->value->navigationCapability,
            ];
        }

        return [
            'resource_type' => 'ledger_entry', 'id' => $this->value->uuid, 'direction' => $this->value->direction,
            'amount_minor' => (string) $this->value->amountMinor, 'currency' => $this->value->currency,
            'occurred_on' => $this->value->occurredOn?->format('Y-m-d'), 'title' => $this->value->title,
            'note' => $this->value->note, 'category_reference' => $this->value->categoryReference,
            'payment_method_reference' => $this->value->paymentMethodReference, 'version' => $this->value->version,
        ];
    }
}
