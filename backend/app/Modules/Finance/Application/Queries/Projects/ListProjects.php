<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectListFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectPage;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;

final readonly class ListProjects
{
    public function __construct(private ProjectRepository $projects) {}

    public function handle(ProjectListFilter $filter): ProjectPage
    {
        return $this->projects->page($filter);
    }
}
