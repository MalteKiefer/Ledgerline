<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class ProjectDocumentSourceRef
{
    public function __construct(
        public string $sourceType,
        public string $sourceReference,
        public ?int $pinnedRevisionId = null,
    ) {}
}
