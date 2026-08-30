<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\LedgerEntryView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use DateTimeImmutable;

final readonly class ListProjectLedger
{
    public function __construct(private ProjectWorkRepository $work) {}

    /** @return array{items: list<LedgerEntryView>, page: int, per_page: int, total: int} */
    public function handle(ProjectId $projectId, ?string $direction = null, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, ?string $categoryReference = null, int $page = 1, int $perPage = 50): array
    {
        return $this->work->pageLedgerEntries($projectId, $direction, $from, $to, $categoryReference, $page, $perPage);
    }
}
