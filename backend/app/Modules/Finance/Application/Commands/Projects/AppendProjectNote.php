<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\AppendProjectNoteData;
use App\Modules\Finance\Application\DTOs\Projects\HistoryItemView;
use App\Modules\Finance\Application\Ports\Projects\ProjectHistoryRepository;

final readonly class AppendProjectNote
{
    public function __construct(private ProjectHistoryRepository $history) {}

    public function handle(AppendProjectNoteData $data): HistoryItemView
    {
        return $this->history->appendProjectNote($data);
    }
}
