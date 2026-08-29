<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;

final readonly class SearchProjectDocumentSources
{
    public function __construct(private ProjectDocumentSource $catalog) {}

    public function handle(ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        return $this->catalog->search($filter->ownerId, $filter);
    }
}
