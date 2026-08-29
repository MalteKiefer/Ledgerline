<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\HistoryPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectNoteFilter;
use App\Modules\Finance\Application\Ports\Projects\ProjectHistoryRepository;

final readonly class ListDocumentNotes
{
    public function __construct(private ProjectHistoryRepository $history) {}

    public function handle(int $ownerId, string $seriesUuid, ProjectNoteFilter $filter): HistoryPage
    {
        return $this->history->documentNotes($ownerId, $seriesUuid, $filter);
    }
}
