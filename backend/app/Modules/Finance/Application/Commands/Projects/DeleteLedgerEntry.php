<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use DateTimeImmutable;

final readonly class DeleteLedgerEntry
{
    public function __construct(private ProjectWorkRepository $work) {}

    public function handle(ProjectId $projectId, string $uuid, int $expectedVersion, int $actorId, DateTimeImmutable $occurredAt): void
    {
        $this->work->deleteLedgerEntry($projectId, $uuid, $expectedVersion, $actorId, $occurredAt);
    }
}
