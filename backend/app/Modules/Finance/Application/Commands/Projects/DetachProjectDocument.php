<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use DateTimeImmutable;
use DomainException;
use Throwable;

final readonly class DetachProjectDocument
{
    public function __construct(private ProjectDocumentRepository $documents, private ProjectOperationRepository $operations) {}

    public function handle(ProjectId $projectId, int $linkId, int $actorId, DateTimeImmutable $at, string $idempotencyKey): ProjectDocumentView
    {
        $hash = hash('sha256', json_encode(['project' => strtolower($projectId->uuid), 'link_id' => $linkId, 'actor_id' => $actorId, 'occurred_at' => $at->format(DATE_ATOM)], JSON_THROW_ON_ERROR));
        $reservation = $this->operations->reserve($projectId->ownerId, 'project.document.detach', $idempotencyKey, $hash, $projectId);
        if ($reservation->status === 'replay') {
            return $this->documents->get($projectId, $this->replayLinkId($reservation->result));
        }
        if ($reservation->status === 'in_progress') {
            throw new DomainException('operation_in_progress');
        }
        if ($reservation->status === 'failed') {
            $reservation = $this->operations->retryFailed($reservation);
        }
        try {
            $view = $this->documents->detach($projectId, $linkId, $actorId, $at);
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
}
