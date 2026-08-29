<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentPage;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;

final readonly class ListProjectDocuments
{
    public function __construct(private ProjectDocumentRepository $documents, private ProjectDocumentSource $catalog) {}

    public function handle(ProjectDocumentFilter $filter): ProjectDocumentPage
    {
        return $this->documents->page($filter, $this->catalog);
    }
}
