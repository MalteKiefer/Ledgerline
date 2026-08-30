<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\OperationReservation;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use DateTimeImmutable;
use DomainException;
use Throwable;

final readonly class AttachProjectDocument
{
    public function __construct(private ProjectDocumentSource $catalog, private ProjectDocumentRepository $documents, private ProjectOperationRepository $operations) {}

    public function handle(ProjectId $projectId, ProjectDocumentSourceRef $source, string $role, int $actorId, DateTimeImmutable $at, string $idempotencyKey): ProjectDocumentView
    {
        $hash = hash('sha256', json_encode(['project' => strtolower($projectId->uuid), 'source_type' => $source->sourceType, 'source_reference' => $source->sourceReference, 'pinned_revision_id' => $source->pinnedRevisionId, 'role' => $role, 'actor_id' => $actorId, 'occurred_at' => $at->format('Y-m-d\TH:i:s.uP')], JSON_THROW_ON_ERROR));
        $reservation = $this->operations->reserve($projectId->ownerId, 'project.document.attach', $idempotencyKey, $hash, $projectId);
        if ($reservation->status === 'replay') {
            return $this->documents->get($projectId, $this->replayLinkId($reservation->result), $this->catalog);
        }
        if ($reservation->status === 'in_progress') {
            return $this->recover($reservation, $projectId, $source, $role);
        }
        if ($reservation->status === 'failed') {
            $reservation = $this->operations->retryFailed($reservation);
            $recovered = $this->documents->findAttachedByOperation($projectId, $reservation->recordId, $this->catalog);
            if ($recovered !== null) {
                $this->operations->succeed($reservation, ['link_id' => $recovered->linkId]);

                return $recovered;
            }
        }
        try {
            $metadata = $this->catalog->resolve($projectId->ownerId, $source);
            $view = $this->documents->attach($projectId, $metadata, $role, $actorId, $at, $reservation->recordId);
            $this->operations->succeed($reservation, ['link_id' => $view->linkId]);

            return $view;
        } catch (Throwable $exception) {
            $this->operations->fail($reservation, $exception instanceof DomainException ? $exception->getMessage() : 'document_attach_failed');
            throw $exception;
        }
    }

    /** @param array<string, mixed>|null $result */
    private function replayLinkId(?array $result): int
    {
        $linkId = $result['link_id'] ?? null;
        if (! is_int($linkId) || $linkId < 1) {
            throw new DomainException('operation_result_invalid');
        }

        return $linkId;
    }

    private function recover(OperationReservation $reservation, ProjectId $projectId, ProjectDocumentSourceRef $source, string $role): ProjectDocumentView
    {
        $recovered = $this->documents->findAttachedByOperation($projectId, $reservation->recordId, $this->catalog);
        if ($recovered === null) {
            throw new DomainException('operation_in_progress');
        }
        $this->operations->succeed($reservation, ['link_id' => $recovered->linkId]);

        return $recovered;
    }
}
