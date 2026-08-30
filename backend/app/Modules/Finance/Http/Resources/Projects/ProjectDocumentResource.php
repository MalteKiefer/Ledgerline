<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

final class ProjectDocumentResource extends JsonResource
{
    public function __construct(private readonly ProjectDocumentView|ProjectDocumentMetadata|ProjectDocumentPage|ProjectDocumentSourcePage $value)
    {
        parent::__construct($value);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->value instanceof ProjectDocumentPage) {
            return ['data' => array_map(static fn ($item): array => (new self($item))->resolve($request), $this->value->items),
                'meta' => ['current_page' => $this->value->page, 'per_page' => $this->value->perPage, 'total' => $this->value->total]];
        }
        if ($this->value instanceof ProjectDocumentSourcePage) {
            return ['data' => array_map(static fn ($item): array => (new self($item))->resolve($request), $this->value->items), 'next_cursor' => $this->value->nextCursor];
        }
        if ($this->value instanceof ProjectDocumentMetadata) {
            return $this->metadata($this->value);
        }

        $allowedSnapshot = array_flip(['source_type', 'source_reference', 'title', 'mime', 'size', 'sha256', 'document_type', 'document_label', 'occurred_at']);

        return [
            'link_id' => $this->value->linkId,
            'project_id' => $this->value->projectId->uuid,
            'source' => ['source_type' => $this->value->source->sourceType, 'source_reference' => $this->value->source->sourceReference, 'pinned_revision_id' => $this->value->source->pinnedRevisionId],
            'role' => $this->value->role,
            'snapshot' => array_intersect_key($this->value->snapshot, $allowedSnapshot),
            'current' => $this->value->current === null ? null : $this->metadata($this->value->current),
            'availability' => $this->value->availability,
            'attached_at' => $this->value->attachedAt->format('Y-m-d\TH:i:s.uP'),
            'detached' => $this->value->detachedAt !== null,
            'detached_at' => $this->value->detachedAt?->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(ProjectDocumentMetadata $metadata): array
    {
        $capability = null;
        if ($metadata->capabilityRoute !== null && Route::has($metadata->capabilityRoute)) {
            $capability = route($metadata->capabilityRoute, $metadata->capabilityParameters, false);
        }

        return [
            'source_type' => $metadata->source->sourceType, 'source_reference' => $metadata->source->sourceReference,
            'pinned_revision_id' => $metadata->source->pinnedRevisionId, 'title' => $metadata->title,
            'mime' => $metadata->mime, 'size' => $metadata->size, 'sha256' => $metadata->sha256,
            'document_type' => $metadata->documentType, 'document_label' => $metadata->documentLabel,
            'occurred_at' => $metadata->occurredAt?->format('Y-m-d\TH:i:s.uP'), 'availability' => $metadata->availability,
            'capability_url' => $capability,
        ];
    }
}
