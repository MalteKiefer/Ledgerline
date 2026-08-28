<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateLedgerEntryData;
use App\Modules\Finance\Application\DTOs\Projects\LedgerEntryView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;

final readonly class UpdateLedgerEntry
{
    public function __construct(private ProjectWorkRepository $work) {}

    public function handle(ProjectId $projectId, string $uuid, int $expectedVersion, CreateLedgerEntryData $replacement): LedgerEntryView
    {
        if ($replacement->projectId != $projectId) {
            throw new \InvalidArgumentException('project_mismatch');
        }

return $this->work->correctLedgerEntry($projectId, $uuid, $expectedVersion, $replacement);
    }
}
