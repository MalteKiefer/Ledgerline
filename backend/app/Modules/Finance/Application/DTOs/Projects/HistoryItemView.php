<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;

final readonly class HistoryItemView
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $sourceKind,
        public int $sourceId,
        public string $type,
        public ?string $visibility,
        public ?string $body,
        public ?int $supersedesNoteId,
        public ?string $subjectType,
        public ?string $subjectReference,
        public array $payload,
        public ?int $authorId,
        public DateTimeImmutable $occurredAt,
        public ?string $seriesUuid = null,
        public ?int $revisionId = null,
    ) {}
}
