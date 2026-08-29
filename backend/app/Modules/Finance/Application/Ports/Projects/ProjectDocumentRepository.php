<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use DateTimeImmutable;

interface ProjectDocumentRepository
{
    public function attach(ProjectId $projectId, ProjectDocumentMetadata $metadata, string $role, int $actorId, DateTimeImmutable $at): ProjectDocumentView;

    public function detach(ProjectId $projectId, int $linkId, int $actorId, DateTimeImmutable $at): ProjectDocumentView;

    public function get(ProjectId $projectId, int $linkId, ?ProjectDocumentSource $catalog = null): ProjectDocumentView;

    public function findActive(ProjectId $projectId, ProjectDocumentSourceRef $source, string $role, ProjectDocumentSource $catalog): ?ProjectDocumentView;

    public function page(ProjectDocumentFilter $filter, ProjectDocumentSource $catalog): ProjectDocumentPage;
}
