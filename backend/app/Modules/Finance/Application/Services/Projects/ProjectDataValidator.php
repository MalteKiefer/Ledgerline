<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Services\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use InvalidArgumentException;

final readonly class ProjectDataValidator
{
    public function __construct(private ProjectReferenceResolver $references) {}

    public function create(CreateProjectData $data): CreateProjectData
    {
        $name = $this->common(
            $data->ownerId,
            $data->actorId,
            $data->name,
            $data->startsOn,
            $data->dueOn,
            $data->partnerReference,
        );

        return new CreateProjectData(
            $data->ownerId,
            $name,
            $data->kind,
            $data->budget,
            $data->actorId,
            $data->occurredAt,
            $data->parentId,
            $data->partnerReference,
            $data->startsOn,
            $data->dueOn,
            $data->sourceType,
            $data->sourceId,
        );
    }

    public function update(UpdateProjectData $data): UpdateProjectData
    {
        $name = $this->common(
            $data->projectId->ownerId,
            $data->actorId,
            $data->name,
            $data->startsOn,
            $data->dueOn,
            $data->partnerReference,
        );

        return new UpdateProjectData(
            $data->projectId,
            $data->expectedVersion,
            $name,
            $data->kind,
            $data->budget,
            $data->actorId,
            $data->occurredAt,
            $data->partnerReference,
            $data->startsOn,
            $data->dueOn,
        );
    }

    public function actor(ProjectId $projectId, int $actorId): void
    {
        $this->assertActor($projectId->ownerId, $actorId);
    }

    private function common(
        int $ownerId,
        int $actorId,
        string $name,
        ?\DateTimeImmutable $startsOn,
        ?\DateTimeImmutable $dueOn,
        ?string $partnerReference,
    ): string {
        $this->assertActor($ownerId, $actorId);

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Project name is invalid.');
        }
        if ($startsOn !== null && $dueOn !== null && $startsOn > $dueOn) {
            throw new InvalidArgumentException('Project date range is invalid.');
        }

        $this->references->assertOwnedPartnerReference($ownerId, $partnerReference);

        return $name;
    }

    private function assertActor(int $ownerId, int $actorId): void
    {
        if ($ownerId !== $actorId) {
            throw new InvalidArgumentException('Project actor must match owner.');
        }
    }
}
