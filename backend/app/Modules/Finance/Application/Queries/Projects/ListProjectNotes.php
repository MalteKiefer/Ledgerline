<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\HistoryPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectNoteFilter;
use App\Modules\Finance\Application\Ports\Projects\ProjectHistoryRepository;

final readonly class ListProjectNotes
{
    public function __construct(private ProjectHistoryRepository $history) {}

    public function handle(ProjectId $projectId, ProjectNoteFilter $filter): HistoryPage
    {
        return $this->history->projectNotes($projectId, $filter);
    }
}
