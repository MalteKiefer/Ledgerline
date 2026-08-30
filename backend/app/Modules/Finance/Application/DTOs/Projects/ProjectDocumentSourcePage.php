<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class ProjectDocumentSourcePage
{
    /** @param list<ProjectDocumentMetadata> $items */
    public function __construct(public array $items, public ?string $nextCursor) {}
}
