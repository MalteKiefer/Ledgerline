<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectFinancialSourceRow;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;

interface ProjectFinancialSource
{
    /** @return list<ProjectFinancialSourceRow> */
    public function rows(int $ownerId, ProjectId $projectId): array;
}
