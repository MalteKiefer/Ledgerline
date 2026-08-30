<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class ProjectTarget
{
    public function __construct(public ProjectId $projectId, public bool $created) {}
}
