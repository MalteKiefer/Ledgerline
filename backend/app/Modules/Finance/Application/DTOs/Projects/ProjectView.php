<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use DateTimeImmutable;

final readonly class ProjectView
{
    public function __construct(
        public ProjectId $id,
        public ?ProjectId $parentId,
        public bool $parentAvailable,
        public string $name,
        public ProjectKind $kind,
        public ProjectStatus $status,
        public ?string $partnerReference,
        public ?DateTimeImmutable $startsOn,
        public ?DateTimeImmutable $dueOn,
        public ?int $budgetMinor,
        public string $currency,
        public int $version,
        public bool $archived,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
