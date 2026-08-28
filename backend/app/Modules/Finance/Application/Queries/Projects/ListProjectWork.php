<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use InvalidArgumentException;

final readonly class ListProjectWork
{
    public function __construct(private ProjectWorkRepository $work) {}

    public function handle(ProjectId $projectId, string $resource = 'work_items', int $page = 1, int $perPage = 50): array
    {
        return match ($resource) {
            'work_items' => $this->work->pageWorkItems($projectId, $page, $perPage),
            'time_entries' => $this->work->pageTimeEntries($projectId, $page, $perPage),
            default => throw new InvalidArgumentException('Unknown project work resource.'),
        };
    }
}
