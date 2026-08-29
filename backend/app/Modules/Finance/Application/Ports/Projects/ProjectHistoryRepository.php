<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\AppendDocumentNoteData;
use App\Modules\Finance\Application\DTOs\Projects\AppendProjectNoteData;
use App\Modules\Finance\Application\DTOs\Projects\HistoryItemView;
use App\Modules\Finance\Application\DTOs\Projects\HistoryPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectNoteFilter;

interface ProjectHistoryRepository
{
    public function appendProjectNote(AppendProjectNoteData $data): HistoryItemView;

    public function appendDocumentNote(AppendDocumentNoteData $data): HistoryItemView;

    public function projectNotes(ProjectId $projectId, ProjectNoteFilter $filter): HistoryPage;

    public function documentNotes(int $ownerId, string $seriesUuid, ProjectNoteFilter $filter): HistoryPage;

    public function projectActivity(ProjectId $projectId, ?string $cursor, int $perPage): HistoryPage;
}
