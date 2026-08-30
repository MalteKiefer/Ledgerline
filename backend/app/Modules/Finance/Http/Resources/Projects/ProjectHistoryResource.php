<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Projects;

use App\Modules\Finance\Application\DTOs\Projects\HistoryItemView;
use App\Modules\Finance\Application\DTOs\Projects\HistoryPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectHistoryResource extends JsonResource
{
    private const PAYLOAD_KEYS = [
        'allocation_id', 'amount_minor', 'archived', 'availability', 'based_on_revision_id', 'batch_id', 'category_reference',
        'changes', 'claim_reference', 'corrects_uuid', 'creation_key_sha256', 'currency', 'current_revision_id', 'delivery_id',
        'direction', 'document_label', 'document_type', 'due_date', 'entry_count', 'error_code', 'from_status', 'gross_minor',
        'invoice_uuid', 'ledger_entry_uuid', 'level', 'link_id', 'metadata', 'net_minor', 'new_parent_uuid', 'new_status',
        'new_version', 'note_id', 'number', 'old_parent_uuid', 'old_status', 'old_version', 'operation', 'operation_id',
        'ordered_uuids', 'parent_uuid', 'payment_id', 'payment_reference', 'pdf_sha256', 'previous', 'previous_revision_id',
        'project_uuid', 'quantity_scaled', 'reason_code', 'recipient_domain', 'reopened', 'retry_of_delivery_id',
        'reverses_allocation_id', 'revision_id', 'revision_number', 'role', 'sha256', 'snapshot_sha256', 'source_line_index',
        'source_reference', 'source_revision_id', 'source_type', 'status', 'target_quote_uuid', 'target_reference',
        'time_entry_ids', 'time_entry_uuid', 'time_entry_uuids', 'to_status', 'type', 'vat_minor', 'version', 'visibility',
        'work_item_uuid',
    ];

    public function __construct(private readonly HistoryItemView|HistoryPage $value)
    {
        parent::__construct($value);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->value instanceof HistoryPage) {
            $result = ['data' => array_map(static fn ($item): array => (new self($item))->resolve($request), $this->value->items)];
            if ($this->value->page !== null) {
                $result['meta'] = ['current_page' => $this->value->page, 'per_page' => $this->value->perPage, 'total' => $this->value->total];
            } else {
                $result['next_cursor'] = $this->value->nextCursor;
            }

            return $result;
        }

        return [
            'id' => $this->value->sourceId,
            'source_kind' => $this->value->sourceKind,
            'type' => $this->value->type,
            'visibility' => $this->value->visibility,
            'body' => $this->value->body,
            'supersedes_note_id' => $this->value->supersedesNoteId,
            'subject_type' => $this->value->subjectType,
            'subject_reference' => $this->value->subjectReference,
            'payload' => $this->safePayload($this->value->payload),
            'author_id' => $this->value->authorId,
            'occurred_at' => $this->value->occurredAt->format('Y-m-d\TH:i:s.uP'),
            'series_id' => $this->value->seriesUuid,
            'revision_id' => $this->value->revisionId,
        ];
    }

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::PAYLOAD_KEYS, true)) {
                continue;
            }
            if (($key === 'metadata' || $key === 'changes' || $key === 'previous') && is_array($value)) {
                $value = $this->safePayload($value);
            }
            if ((str_ends_with($key, '_minor') || str_ends_with($key, '_scaled')) && is_int($value)) {
                $value = (string) $value;
            }
            $safe[$key] = $value;
        }

        return $safe;
    }
}
