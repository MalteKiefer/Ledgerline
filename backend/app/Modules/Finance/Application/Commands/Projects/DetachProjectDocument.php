<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\OperationReservation;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use DateTimeImmutable;
use DomainException;
use Throwable;

final readonly class DetachProjectDocument
{
    public function __construct(private ProjectDocumentRepository $documents, private ProjectOperationRepository $operations, private ProjectDocumentSource $catalog) {}

    public function handle(ProjectId $projectId, int $linkId, int $actorId, DateTimeImmutable $at, string $idempotencyKey): ProjectDocumentView
    {
        $hash = hash('sha256', json_encode(['project' => strtolower($projectId->uuid), 'link_id' => $linkId, 'actor_id' => $actorId, 'occurred_at' => $at->format('Y-m-d\TH:i:s.uP')], JSON_THROW_ON_ERROR));
        $reservation = $this->operations->reserve($projectId->ownerId, 'project.document.detach', $idempotencyKey, $hash, $projectId);
        if ($reservation->status === 'replay') {
            return $this->documents->get($projectId, $this->replayLinkId($reservation->result), $this->catalog);
        }
        if ($reservation->status === 'in_progress') {
            return $this->recover($reservation, $projectId, $linkId);
        }
        if ($reservation->status === 'failed') {
            $reservation = $this->operations->retryFailed($reservation);
            $existing = $this->documents->findDetachedByOperation($projectId, $reservation->recordId, $this->catalog);
            if ($existing !== null) {
                $this->operations->succeed($reservation, ['link_id' => $existing->linkId]);

                return $existing;
            }
        }
        try {
            $this->documents->detach($projectId, $linkId, $actorId, $at, $reservation->recordId);
            $view = $this->documents->get($projectId, $linkId, $this->catalog);
            $this->operations->succeed($reservation, ['link_id' => $view->linkId]);

            return $view;
        } catch (Throwable $exception) {
            $this->operations->fail($reservation, $exception instanceof DomainException ? $exception->getMessage() : 'document_detach_failed');
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

    private function recover(OperationReservation $reservation, ProjectId $projectId, int $linkId): ProjectDocumentView
    {
        $existing = $this->documents->findDetachedByOperation($projectId, $reservation->recordId, $this->catalog);
        if ($existing === null) {
            throw new DomainException('operation_in_progress');
        }
        $this->operations->succeed($reservation, ['link_id' => $existing->linkId]);

        return $existing;
    }
}
