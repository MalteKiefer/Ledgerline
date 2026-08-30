<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class ProjectTotalsView
{
    /** @param array<string,array{hours_scaled:int,time_value_minor:int,ledger_minor:int,financial_minor:int}> $currencies */
    public function __construct(public ProjectId $projectId, public array $currencies) {}
}
