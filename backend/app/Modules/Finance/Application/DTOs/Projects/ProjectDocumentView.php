<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;

final readonly class ProjectDocumentView
{
    /** @param array<string, mixed> $snapshot */
    public function __construct(
        public int $linkId,
        public ProjectId $projectId,
        public ProjectDocumentSourceRef $source,
        public string $role,
        public array $snapshot,
        public ?ProjectDocumentMetadata $current,
        public string $availability,
        public int $attachedBy,
        public DateTimeImmutable $attachedAt,
        public ?int $detachedBy,
        public ?DateTimeImmutable $detachedAt,
    ) {}
}
