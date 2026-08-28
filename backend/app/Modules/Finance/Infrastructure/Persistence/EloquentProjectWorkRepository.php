<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

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
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\TimeCharge;
use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectLedgerEntryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectTimeEntryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectWorkItemRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class EloquentProjectWorkRepository implements ProjectWorkRepository
{
    public function createWorkItem(CreateWorkItemData $data): WorkItemView
    {
        return DB::transaction(function () use ($data): WorkItemView {
            $project = $this->lockedProject($data->projectId);
            $this->assertProjectActive($project);
            $maximumSort = DB::table('finance_project_work_items')
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->max('sort');
            $sort = is_int($maximumSort) ? $maximumSort + 1 : 1;
            $timestamp = $this->timestamp($data->occurredAt);
            $id = (int) DB::table('finance_project_work_items')->insertGetId([
                'user_id' => $data->projectId->ownerId,
                'project_id' => $project->id,
                'uuid' => (string) Str::uuid(),
                'title' => $data->title,
                'description' => $data->description,
                'status' => $data->status->value,
                'starts_on' => $data->startsOn?->format('Y-m-d'),
                'due_on' => $data->dueOn?->format('Y-m-d'),
                'estimate_quantity_scaled' => $data->estimate?->scaled(),
                'is_milestone' => $data->isMilestone,
                'sort' => $sort,
                'source_revision_id' => null,
                'source_line_index' => null,
                'product_reference' => $data->productReference,
                'version' => 0,
                'created_by' => $data->actorId,
                'deleted_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $this->activity($data->projectId->ownerId, (int) $project->id, 'work_item.created', ['work_item_uuid' => $this->ownedWorkById($data->projectId, $id)->uuid], $data->actorId, $data->occurredAt);

            return $this->workView($this->ownedWorkById($data->projectId, $id), $data->projectId);
        }, 1);
    }

    public function workItem(ProjectId $projectId, string $uuid): WorkItemView
    {
        return $this->workView($this->ownedWork($projectId, $uuid), $projectId);
    }

    public function updateWorkItem(UpdateWorkItemData $data): WorkItemView
    {
        return DB::transaction(function () use ($data): WorkItemView {
            $project = $this->lockedProject($data->projectId);
            $this->assertProjectActive($project);
            $record = $this->lockedWork($data->projectId, $data->workItemUuid);
            $this->compareVersion($record, $data->expectedVersion);
            $oldStatus = (string) $record->status;
            DB::table('finance_project_work_items')->where('id', $record->id)->update([
                'title' => $data->title,
                'description' => $data->description,
                'status' => $data->status->value,
                'starts_on' => $data->startsOn?->format('Y-m-d'),
                'due_on' => $data->dueOn?->format('Y-m-d'),
                'estimate_quantity_scaled' => $data->estimate?->scaled(),
                'is_milestone' => $data->isMilestone,
                'product_reference' => $data->productReference,
                'version' => $data->expectedVersion + 1,
                'updated_at' => $this->timestamp($data->occurredAt),
            ]);
            $this->activity($data->projectId->ownerId, (int) $project->id, 'work_item.updated', [
                'new_status' => $data->status->value,
                'old_status' => $oldStatus,
                'work_item_uuid' => $data->workItemUuid,
            ], $data->actorId, $data->occurredAt);

            return $this->workItem($data->projectId, $data->workItemUuid);
        }, 1);
    }

    public function deleteWorkItem(ProjectId $projectId, string $uuid, int $expectedVersion, int $actorId, DateTimeImmutable $occurredAt): void
    {
        DB::transaction(function () use ($projectId, $uuid, $expectedVersion, $actorId, $occurredAt): void {
            $project = $this->lockedProject($projectId);
            $this->assertProjectActive($project);
            $record = $this->lockedWork($projectId, $uuid);
            $this->compareVersion($record, $expectedVersion);
            DB::table('finance_project_time_entries')
                ->where('user_id', $projectId->ownerId)
                ->where('project_id', $project->id)
                ->where('work_item_id', $record->id)
                ->whereNull('invoice_target_reference')
                ->whereNull('deleted_at')
                ->update([
                    'work_item_id' => null,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => $this->timestamp($occurredAt),
                ]);
            DB::table('finance_project_work_items')->where('id', $record->id)->update([
                'version' => $expectedVersion + 1,
                'deleted_at' => $this->timestamp($occurredAt),
                'updated_at' => $this->timestamp($occurredAt),
            ]);
            $this->activity($projectId->ownerId, (int) $project->id, 'work_item.deleted', ['work_item_uuid' => $uuid], $actorId, $occurredAt);
        }, 1);
    }

    public function reorderWorkItems(ProjectId $projectId, array $orderedUuids, int $actorId, DateTimeImmutable $occurredAt): array
    {
        return DB::transaction(function () use ($projectId, $orderedUuids, $actorId, $occurredAt): array {
            $project = $this->lockedProject($projectId);
            $this->assertProjectActive($project);
            $records = ProjectWorkItemRecord::query()->withoutGlobalScopes()
                ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)
                ->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            $current = $records->map(static fn (ProjectWorkItemRecord $record): string => (string) $record->uuid)->all();
            if (count($orderedUuids) !== count(array_unique($orderedUuids))
                || count($orderedUuids) !== count($current)
                || array_diff($orderedUuids, $current) !== []
                || array_diff($current, $orderedUuids) !== []) {
                throw new InvalidProjectAction('work_item_set_mismatch');
            }

            foreach ($orderedUuids as $sort => $uuid) {
                DB::table('finance_project_work_items')
                    ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->where('uuid', $uuid)
                    ->update(['sort' => $sort, 'version' => DB::raw('version + 1'), 'updated_at' => $this->timestamp($occurredAt)]);
            }
            $this->activity($projectId->ownerId, (int) $project->id, 'work_item.reordered', ['ordered_uuids' => $orderedUuids], $actorId, $occurredAt);

            return array_map(fn (string $uuid): WorkItemView => $this->workItem($projectId, $uuid), $orderedUuids);
        }, 1);
    }

    public function pageWorkItems(ProjectId $projectId, int $page, int $perPage): array
    {
        $project = $this->project($projectId);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = ProjectWorkItemRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereNull('deleted_at');
        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderBy('id')->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(fn (ProjectWorkItemRecord $record): WorkItemView => $this->workView($record, $projectId))->all();

        return ['items' => array_values($items), 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    public function logTime(LogTimeData $data, ?Money $frozenRate): TimeEntryView
    {
        return DB::transaction(function () use ($data, $frozenRate): TimeEntryView {
            $project = $this->lockedProject($data->projectId);
            $this->assertProjectActive($project);
            $workItemId = $this->workInternalId($data->projectId, (int) $project->id, $data->workItemUuid);
            $timestamp = $this->timestamp($data->occurredAt);
            $id = (int) DB::table('finance_project_time_entries')->insertGetId([
                'user_id' => $data->projectId->ownerId,
                'project_id' => $project->id,
                'work_item_id' => $workItemId,
                'uuid' => (string) Str::uuid(),
                'worked_on' => $data->workedOn->format('Y-m-d'),
                'quantity_scaled' => $data->quantity->scaled(),
                'description' => $data->description,
                'billable' => $data->billable,
                'hourly_rate_minor' => $frozenRate?->minor(),
                'currency' => $data->currency,
                'invoice_target_reference' => null,
                'invoiced_at' => null,
                'version' => 0,
                'created_by' => $data->actorId,
                'deleted_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $record = $this->ownedTimeById($data->projectId, $id);
            $this->activity($data->projectId->ownerId, (int) $project->id, 'time_entry.logged', [
                'quantity_scaled' => $data->quantity->scaled(),
                'time_entry_uuid' => (string) $record->uuid,
            ], $data->actorId, $data->occurredAt);

            return $this->timeView($record, $data->projectId);
        }, 1);
    }

    public function timeEntry(ProjectId $projectId, string $uuid): TimeEntryView
    {
        return $this->timeView($this->ownedTime($projectId, $uuid), $projectId);
    }

    public function updateTime(UpdateTimeData $data, ?Money $frozenRate): TimeEntryView
    {
        return DB::transaction(function () use ($data, $frozenRate): TimeEntryView {
            $project = $this->lockedProject($data->projectId);
            $this->assertProjectActive($project);
            $record = $this->lockedTime($data->projectId, $data->timeEntryUuid);
            $this->assertTimeMutable($record);
            $this->compareTimeVersion($record, $data->expectedVersion);
            $workItemId = $this->workInternalId($data->projectId, (int) $project->id, $data->workItemUuid);
            DB::table('finance_project_time_entries')->where('id', $record->id)->update([
                'work_item_id' => $workItemId,
                'worked_on' => $data->workedOn->format('Y-m-d'),
                'quantity_scaled' => $data->quantity->scaled(),
                'description' => $data->description,
                'billable' => $data->billable,
                'hourly_rate_minor' => $frozenRate?->minor(),
                'currency' => $data->currency,
                'version' => $data->expectedVersion + 1,
                'updated_at' => $this->timestamp($data->occurredAt),
            ]);
            $this->activity($data->projectId->ownerId, (int) $project->id, 'time_entry.updated', [
                'quantity_scaled' => $data->quantity->scaled(), 'time_entry_uuid' => $data->timeEntryUuid,
            ], $data->actorId, $data->occurredAt);

            return $this->timeEntry($data->projectId, $data->timeEntryUuid);
        }, 1);
    }

    public function deleteTime(ProjectId $projectId, string $uuid, int $expectedVersion, int $actorId, DateTimeImmutable $occurredAt): void
    {
        DB::transaction(function () use ($projectId, $uuid, $expectedVersion, $actorId, $occurredAt): void {
            $project = $this->lockedProject($projectId);
            $this->assertProjectActive($project);
            $record = $this->lockedTime($projectId, $uuid);
            $this->assertTimeMutable($record);
            $this->compareTimeVersion($record, $expectedVersion);
            DB::table('finance_project_time_entries')->where('id', $record->id)->update([
                'version' => $expectedVersion + 1,
                'deleted_at' => $this->timestamp($occurredAt),
                'updated_at' => $this->timestamp($occurredAt),
            ]);
            $this->activity($projectId->ownerId, (int) $project->id, 'time_entry.deleted', ['time_entry_uuid' => $uuid], $actorId, $occurredAt);
        }, 1);
    }

    public function pageTimeEntries(ProjectId $projectId, int $page, int $perPage): array
    {
        $project = $this->project($projectId);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = ProjectTimeEntryRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereNull('deleted_at');
        $total = (clone $query)->count();
        $items = $query->orderByDesc('worked_on')->orderByDesc('id')->offset(($page - 1) * $perPage)->limit($perPage)->get()
            ->map(fn (ProjectTimeEntryRecord $record): TimeEntryView => $this->timeView($record, $projectId))->all();

        return ['items' => array_values($items), 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    public function createLedgerEntry(CreateLedgerEntryData $data): LedgerEntryView
    {
        return DB::transaction(function () use ($data): LedgerEntryView {
            $project = $this->lockedProject($data->projectId);
            $this->assertProjectActive($project);
            $uuid = (string) Str::uuid();
            $timestamp = $this->timestamp($data->occurredAt);
            DB::table('finance_project_ledger_entries')->insert($this->ledgerValues($data, (int) $project->id, $uuid, $timestamp));
            $this->activity($data->projectId->ownerId, (int) $project->id, 'ledger_entry.created', ['ledger_entry_uuid' => $uuid], $data->actorId, $data->occurredAt);

            return $this->ledgerEntry($data->projectId, $uuid);
        }, 1);
    }

    public function correctLedgerEntry(ProjectId $projectId, string $uuid, int $expectedVersion, CreateLedgerEntryData $replacement): LedgerEntryView
    {
        return DB::transaction(function () use ($projectId, $uuid, $expectedVersion, $replacement): LedgerEntryView {
            $project = $this->lockedProject($projectId);
            $this->assertProjectActive($project);
            $record = $this->lockedLedger($projectId, $uuid);
            $this->compareLedgerVersion($record, $expectedVersion);
            $timestamp = $this->timestamp($replacement->occurredAt);
            DB::table('finance_project_ledger_entries')->where('id', $record->id)->update(['version' => $expectedVersion + 1, 'deleted_at' => $timestamp, 'updated_at' => $timestamp]);
            $newUuid = (string) Str::uuid();
            $values = $this->ledgerValues($replacement, (int) $project->id, $newUuid, $timestamp);
            $values['legacy_metadata'] = json_encode(['corrects_uuid' => $uuid], JSON_THROW_ON_ERROR);
            DB::table('finance_project_ledger_entries')->insert($values);
            $this->activity($projectId->ownerId, (int) $project->id, 'ledger_entry.corrected', ['ledger_entry_uuid' => $newUuid, 'corrects_uuid' => $uuid], $replacement->actorId, $replacement->occurredAt);

            return $this->ledgerEntry($projectId, $newUuid);
        }, 1);
    }

    public function deleteLedgerEntry(ProjectId $projectId, string $uuid, int $expectedVersion, int $actorId, DateTimeImmutable $occurredAt): void
    {
        DB::transaction(function () use ($projectId, $uuid, $expectedVersion, $actorId, $occurredAt): void {
            $project = $this->lockedProject($projectId);
            $this->assertProjectActive($project);
            $record = $this->lockedLedger($projectId, $uuid);
            $this->compareLedgerVersion($record, $expectedVersion);
            $timestamp = $this->timestamp($occurredAt);
            DB::table('finance_project_ledger_entries')->where('id', $record->id)->update(['version' => $expectedVersion + 1, 'deleted_at' => $timestamp, 'updated_at' => $timestamp]);
            $this->activity($projectId->ownerId, (int) $project->id, 'ledger_entry.deleted', ['ledger_entry_uuid' => $uuid], $actorId, $occurredAt);
        }, 1);
    }

    public function pageLedgerEntries(ProjectId $projectId, ?string $direction, ?DateTimeImmutable $from, ?DateTimeImmutable $to, ?string $categoryReference, int $page, int $perPage): array
    {
        $project = $this->project($projectId);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = ProjectLedgerEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereNull('deleted_at');
        if ($direction !== null) {
            $query->where('direction', $direction);
        } if ($from !== null) {
            $query->whereDate('occurred_on', '>=', $from->format('Y-m-d'));
        } if ($to !== null) {
            $query->whereDate('occurred_on', '<=', $to->format('Y-m-d'));
        } if ($categoryReference !== null) {
            $query->where('category_reference', $categoryReference);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('occurred_on')->orderByDesc('id')->offset(($page - 1) * $perPage)->limit($perPage)->get()->map(fn ($record) => $this->ledgerView($record, $projectId))->all();

        return ['items' => array_values($items), 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    private function ledgerEntry(ProjectId $projectId, string $uuid): LedgerEntryView
    {
        return $this->ledgerView(ProjectLedgerEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('uuid', $uuid)->whereNull('deleted_at')->firstOrFail(), $projectId);
    }

    private function lockedLedger(ProjectId $projectId, string $uuid): ProjectLedgerEntryRecord
    {
        $project = $this->project($projectId);

        return ProjectLedgerEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->where('uuid', $uuid)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
    }

    private function compareLedgerVersion(ProjectLedgerEntryRecord $record, int $expectedVersion): void
    {
        if ((int) $record->version !== $expectedVersion) {
            throw new DomainException('version_conflict');
        }
    }

    /** @return array<string, int|string|bool|null> */
    private function ledgerValues(CreateLedgerEntryData $data, int $projectId, string $uuid, string $timestamp): array
    {
        return ['user_id' => $data->projectId->ownerId, 'project_id' => $projectId, 'uuid' => $uuid, 'direction' => $data->direction, 'amount_minor' => $data->amountMinor, 'currency' => $data->currency, 'occurred_on' => $data->occurredOn?->format('Y-m-d'), 'title' => $data->title, 'note' => $data->note, 'category_reference' => $data->categoryReference, 'payment_method_reference' => $data->paymentMethodReference, 'legacy_metadata' => null, 'version' => 0, 'created_by' => $data->actorId, 'deleted_at' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp];
    }

    private function ledgerView(ProjectLedgerEntryRecord $r, ProjectId $projectId): LedgerEntryView
    {
        return new LedgerEntryView($projectId, (string) $r->uuid, (string) $r->direction, (int) $r->amount_minor, (string) $r->currency, $this->date($r->occurred_on), is_string($r->title) ? $r->title : null, is_string($r->note) ? $r->note : null, is_string($r->category_reference) ? $r->category_reference : null, is_string($r->payment_method_reference) ? $r->payment_method_reference : null, (int) $r->version);
    }

    public function invoiceableTime(ProjectId $projectId, array $uuids): array
    {
        $project = $this->project($projectId);
        $records = ProjectTimeEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereIn('uuid', $uuids)->whereNull('deleted_at')->orderBy('id')->get();
        if ($records->count() !== count($uuids)) {
            throw new InvalidProjectAction('time_entry_set_mismatch');
        }

        return array_values($records->map(fn ($r) => $this->timeView($r, $projectId))->all());
    }

    /**
     * @param  list<string>  $uuids
     * @return list<TimeEntryView>
     */
    public function claimInvoiceTime(ProjectId $projectId, array $uuids, string $claimReference, DateTimeImmutable $occurredAt): array
    {
        return DB::transaction(function () use ($projectId, $uuids, $claimReference, $occurredAt): array {
            $project = $this->lockedProject($projectId);
            $this->assertProjectActive($project);
            $records = ProjectTimeEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereIn('uuid', $uuids)->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($uuids)) {
                throw new InvalidProjectAction('time_entry_set_mismatch');
            }
            /** @var array<string, array{hours: int, rate: int, currency: string}> $groups */
            $groups = [];
            foreach ($records as $record) {
                if (! (bool) $record->billable || $record->hourly_rate_minor === null) {
                    throw new InvalidProjectAction('time_entry_not_invoiceable');
                }
                $currency = (string) $record->currency;
                if ($currency !== (string) $project->currency) {
                    throw new InvalidProjectAction('invoice_time_currency_mismatch');
                }
                $rate = (int) $record->hourly_rate_minor;
                $key = $currency.':'.$rate;
                $groups[$key] ??= ['hours' => 0, 'rate' => $rate, 'currency' => $currency];
                $groups[$key]['hours'] = $this->checkedAdd($groups[$key]['hours'], (int) $record->quantity_scaled);
            }
            foreach ($groups as $group) {
                TimeCharge::calculate(DecimalQuantity::fromString($this->scaledDecimal($group['hours'])), Money::fromMinor($group['rate'], $group['currency']));
            }
            foreach ($records as $record) {
                if ($record->invoice_target_reference !== null && $record->invoice_target_reference !== $claimReference) {
                    throw new InvalidProjectAction('time_entry_invoiced');
                }
                if ($record->invoice_target_reference === null) {
                    DB::table('finance_project_time_entries')->where('id', $record->id)->update(['invoice_target_reference' => $claimReference, 'invoiced_at' => $this->timestamp($occurredAt), 'version' => DB::raw('version + 1'), 'updated_at' => $this->timestamp($occurredAt)]);
                }
            }
            $claimed = ProjectTimeEntryRecord::query()->withoutGlobalScopes()->whereIn('id', $records->pluck('id')->all())->orderBy('id')->get();

            return array_values($claimed->map(fn ($record) => $this->timeView($record, $projectId))->all());
        }, 1);
    }

    public function stampInvoicedTime(ProjectId $projectId, array $uuids, string $claimReference, InvoiceDraftTarget $target, int $actorId, DateTimeImmutable $occurredAt): void
    {
        DB::transaction(function () use ($projectId, $uuids, $claimReference, $target, $actorId, $occurredAt): void {
            $project = $this->lockedProject($projectId);
            $records = ProjectTimeEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereIn('uuid', $uuids)->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($uuids)) {
                throw new InvalidProjectAction('time_entry_set_mismatch');
            }
            $stamped = false;
            foreach ($records as $record) {
                if ($record->invoice_target_reference !== $claimReference && $record->invoice_target_reference !== $target->targetReference) {
                    throw new InvalidProjectAction('time_entry_invoiced');
                }
                if ($record->invoice_target_reference === $claimReference) {
                    DB::table('finance_project_time_entries')->where('id', $record->id)->update(['invoice_target_reference' => $target->targetReference, 'invoiced_at' => $this->timestamp($occurredAt), 'version' => DB::raw('version + 1'), 'updated_at' => $this->timestamp($occurredAt)]);
                    $stamped = true;
                }
            }
            $exists = DB::table('finance_project_document_links')->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->where('source_type', $target->source->sourceType)->where('source_reference', $target->source->sourceReference)->where('role', 'invoice')->whereNull('detached_at')->exists();
            if (! $exists) {
                DB::table('finance_project_document_links')->insert(['user_id' => $projectId->ownerId, 'project_id' => $project->id, 'source_type' => $target->source->sourceType, 'source_reference' => $target->source->sourceReference, 'document_series_id' => null, 'pinned_revision_id' => $target->source->pinnedRevisionId, 'role' => 'invoice', 'metadata_snapshot' => json_encode(['target_reference' => $target->targetReference], JSON_THROW_ON_ERROR), 'attached_by' => $actorId, 'attached_at' => $this->timestamp($occurredAt), 'detached_by' => null, 'detached_at' => null]);
            }
            if ($stamped) {
                $this->activity($projectId->ownerId, (int) $project->id, 'time_entries.invoiced', ['target_reference' => $target->targetReference, 'time_entry_uuids' => $uuids], $actorId, $occurredAt);
            }
        }, 1);
    }

    public function localTotals(ProjectId $projectId): array
    {
        $project = $this->project($projectId);
        $totals = [];
        $times = ProjectTimeEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereNull('deleted_at')->whereNull('invoice_target_reference')->where('billable', true)->get();
        foreach ($times as $time) {
            $currency = (string) $time->currency;
            $totals[$currency] ??= ['hours_scaled' => 0, 'time_value_minor' => 0, 'ledger_minor' => 0];
            $hours = (int) $time->quantity_scaled;
            $totals[$currency]['hours_scaled'] = $this->checkedAdd($totals[$currency]['hours_scaled'], $hours);
            if ($time->hourly_rate_minor !== null) {
                $charge = TimeCharge::calculate(DecimalQuantity::fromString($this->scaledDecimal($hours)), Money::fromMinor((int) $time->hourly_rate_minor, $currency))->minor();
                $totals[$currency]['time_value_minor'] = $this->checkedAdd($totals[$currency]['time_value_minor'], $charge);
            }
        }
        $ledger = ProjectLedgerEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->where('project_id', $project->id)->whereNull('deleted_at')->get();
        foreach ($ledger as $entry) {
            $currency = (string) $entry->currency;
            $totals[$currency] ??= ['hours_scaled' => 0, 'time_value_minor' => 0, 'ledger_minor' => 0];
            $signed = (string) $entry->direction === 'in' ? (int) $entry->amount_minor : -(int) $entry->amount_minor;
            $totals[$currency]['ledger_minor'] = $this->checkedAdd($totals[$currency]['ledger_minor'], $signed);
        }

        return $totals;
    }

    private function scaledDecimal(int $scaled): string
    {
        $raw = (string) $scaled;
        $sign = str_starts_with($raw, '-') ? '-' : '';
        $digits = str_pad(ltrim($raw, '-'), 5, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -4).'.'.substr($digits, -4);
    }

    private function checkedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right) || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new DomainException('project_total_overflow');
        }

        return $left + $right;
    }

    private function workInternalId(ProjectId $projectId, int $internalProjectId, ?string $uuid): ?int
    {
        if ($uuid === null) {
            return null;
        }

        return (int) ProjectWorkItemRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $internalProjectId)
            ->where('uuid', $uuid)->whereNull('deleted_at')->lockForUpdate()->firstOrFail(['id'])->id;
    }

    private function ownedTime(ProjectId $projectId, string $uuid): ProjectTimeEntryRecord
    {
        $project = $this->project($projectId);

        return ProjectTimeEntryRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)
            ->where('uuid', $uuid)->whereNull('deleted_at')->firstOrFail();
    }

    private function lockedTime(ProjectId $projectId, string $uuid): ProjectTimeEntryRecord
    {
        $project = $this->project($projectId);

        return ProjectTimeEntryRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)
            ->where('uuid', $uuid)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
    }

    private function ownedTimeById(ProjectId $projectId, int $id): ProjectTimeEntryRecord
    {
        return ProjectTimeEntryRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->whereKey($id)->firstOrFail();
    }

    private function assertTimeMutable(ProjectTimeEntryRecord $record): void
    {
        if ($record->invoice_target_reference !== null) {
            throw new InvalidProjectAction('time_entry_invoiced');
        }
    }

    private function compareTimeVersion(ProjectTimeEntryRecord $record, int $expectedVersion): void
    {
        if ((int) $record->version !== $expectedVersion) {
            throw new DomainException('version_conflict');
        }
    }

    private function timeView(ProjectTimeEntryRecord $record, ProjectId $projectId): TimeEntryView
    {
        $workUuid = null;
        if ($record->work_item_id !== null) {
            $work = ProjectWorkItemRecord::query()->withoutGlobalScopes()
                ->where('user_id', $projectId->ownerId)->whereKey($record->work_item_id)->first();
            $workUuid = $work instanceof ProjectWorkItemRecord ? (string) $work->uuid : null;
        }

        return new TimeEntryView(
            $projectId, (string) $record->uuid, $workUuid,
            $this->date($record->worked_on) ?? throw new LogicException('Time date is missing.'),
            (int) $record->quantity_scaled, is_string($record->description) ? $record->description : null,
            (bool) $record->billable,
            $record->hourly_rate_minor !== null ? (int) $record->hourly_rate_minor : null,
            (string) $record->currency,
            is_string($record->invoice_target_reference) ? $record->invoice_target_reference : null,
            $this->date($record->invoiced_at), (int) $record->version,
        );
    }

    private function project(ProjectId $id): ProjectRecord
    {
        return ProjectRecord::query()->withoutGlobalScopes()->where('user_id', $id->ownerId)->where('uuid', $id->uuid)->firstOrFail();
    }

    private function lockedProject(ProjectId $id): ProjectRecord
    {
        return ProjectRecord::query()->withoutGlobalScopes()->where('user_id', $id->ownerId)->where('uuid', $id->uuid)->lockForUpdate()->firstOrFail();
    }

    private function assertProjectActive(ProjectRecord $project): void
    {
        if ($project->archived_at !== null) {
            throw new InvalidProjectAction('project_archived');
        }
    }

    private function ownedWork(ProjectId $projectId, string $uuid): ProjectWorkItemRecord
    {
        $project = $this->project($projectId);

        return ProjectWorkItemRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)
            ->where('uuid', $uuid)->whereNull('deleted_at')->firstOrFail();
    }

    private function lockedWork(ProjectId $projectId, string $uuid): ProjectWorkItemRecord
    {
        $project = $this->project($projectId);

        return ProjectWorkItemRecord::query()->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)
            ->where('uuid', $uuid)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
    }

    private function ownedWorkById(ProjectId $projectId, int $id): ProjectWorkItemRecord
    {
        return ProjectWorkItemRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)->whereKey($id)->firstOrFail();
    }

    private function compareVersion(ProjectWorkItemRecord $record, int $expectedVersion): void
    {
        if ((int) $record->version !== $expectedVersion) {
            throw new DomainException('version_conflict');
        }
    }

    private function workView(ProjectWorkItemRecord $record, ProjectId $projectId): WorkItemView
    {
        return new WorkItemView(
            $projectId,
            (string) $record->uuid,
            (string) $record->title,
            is_string($record->description) ? $record->description : null,
            WorkItemStatus::from((string) $record->status),
            $this->date($record->starts_on),
            $this->date($record->due_on),
            $record->estimate_quantity_scaled !== null ? (int) $record->estimate_quantity_scaled : null,
            (bool) $record->is_milestone,
            (int) $record->sort,
            $record->source_revision_id !== null ? (int) $record->source_revision_id : null,
            $record->source_line_index !== null ? (int) $record->source_line_index : null,
            is_string($record->product_reference) ? $record->product_reference : null,
            (int) $record->version,
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
    }

    private function timestamp(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $payload */
    private function activity(int $ownerId, int $projectId, string $type, array $payload, int $actorId, DateTimeImmutable $occurredAt): void
    {
        $timestamp = $this->timestamp($occurredAt);
        DB::table('finance_project_activities')->insert([
            'user_id' => $ownerId, 'project_id' => $projectId, 'type' => $type,
            'subject_type' => null, 'subject_reference' => null,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_by' => $actorId,
            'occurred_at' => $timestamp, 'created_at' => $timestamp,
        ]);
    }
}
