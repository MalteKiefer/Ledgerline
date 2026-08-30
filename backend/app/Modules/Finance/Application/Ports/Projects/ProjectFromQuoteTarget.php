<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource;
use App\Modules\Finance\Application\DTOs\Projects\ProjectTarget;

interface ProjectFromQuoteTarget
{
    public function create(int $ownerId, ProjectQuoteSource $source, string $idempotencyKey): ProjectTarget;
}
