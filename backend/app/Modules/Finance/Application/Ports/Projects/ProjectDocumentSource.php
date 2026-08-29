<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;

interface ProjectDocumentSource
{
    public function supports(string $sourceType): bool;

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata;

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage;
}
