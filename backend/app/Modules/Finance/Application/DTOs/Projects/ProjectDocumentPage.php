<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class ProjectDocumentPage
{
    /** @param list<ProjectDocumentView> $items */
    public function __construct(public array $items, public int $page, public int $perPage, public int $total) {}
}
