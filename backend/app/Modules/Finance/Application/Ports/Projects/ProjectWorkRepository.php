<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateLedgerEntryData;
use App\Modules\Finance\Application\DTOs\Projects\CreateWorkItemData;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\LedgerEntryView;
use App\Modules\Finance\Application\DTOs\Projects\LogTimeData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\TimeEntryView;
use App\Modules\Finance\Application\DTOs\Projects\UpdateTimeData;
use App\Modules\Finance\Application\DTOs\Projects\UpdateWorkItemData;
use App\Modules\Finance\Application\DTOs\Projects\WorkItemView;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

interface ProjectWorkRepository
{
    public function createWorkItem(CreateWorkItemData $data): WorkItemView;

    public function workItem(ProjectId $projectId, string $uuid): WorkItemView;

    public function updateWorkItem(UpdateWorkItemData $data): WorkItemView;

    public function deleteWorkItem(
        ProjectId $projectId,
        string $uuid,
        int $expectedVersion,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): void;

    /**
     * @param  list<string>  $orderedUuids
     * @return list<WorkItemView>
     */
    public function reorderWorkItems(
        ProjectId $projectId,
        array $orderedUuids,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): array;

    /** @return array{items: list<WorkItemView>, page: int, per_page: int, total: int} */
    public function pageWorkItems(ProjectId $projectId, int $page, int $perPage): array;

    public function logTime(LogTimeData $data, ?Money $frozenRate): TimeEntryView;

    public function timeEntry(ProjectId $projectId, string $uuid): TimeEntryView;

    public function updateTime(UpdateTimeData $data, ?Money $frozenRate): TimeEntryView;

    public function deleteTime(
        ProjectId $projectId,
        string $uuid,
        int $expectedVersion,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): void;

    /** @return array{items: list<TimeEntryView>, page: int, per_page: int, total: int} */
    public function pageTimeEntries(ProjectId $projectId, int $page, int $perPage): array;

    public function createLedgerEntry(CreateLedgerEntryData $data): LedgerEntryView;

    public function correctLedgerEntry(ProjectId $projectId, string $uuid, int $expectedVersion, CreateLedgerEntryData $replacement): LedgerEntryView;

    public function deleteLedgerEntry(ProjectId $projectId, string $uuid, int $expectedVersion, int $actorId, DateTimeImmutable $occurredAt): void;

    /** @return array{items: list<LedgerEntryView>, page: int, per_page: int, total: int} */
    public function pageLedgerEntries(ProjectId $projectId, ?string $direction, ?DateTimeImmutable $from, ?DateTimeImmutable $to, ?string $categoryReference, int $page, int $perPage): array;

    /**
     * @param  list<string>  $uuids
     * @return list<TimeEntryView>
     */
    public function invoiceableTime(ProjectId $projectId, array $uuids): array;

    /** @param list<string> $uuids */
    public function stampInvoicedTime(ProjectId $projectId, array $uuids, InvoiceDraftTarget $target, int $actorId, DateTimeImmutable $occurredAt): void;

    /** @return array<string,array{hours_scaled:int,time_value_minor:int,ledger_minor:int}> */
    public function localTotals(ProjectId $projectId): array;
}
