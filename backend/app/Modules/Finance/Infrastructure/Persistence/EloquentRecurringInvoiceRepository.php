<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\FinanceProject;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionConflict;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\IdempotencyStore;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use App\Modules\Finance\Domain\Recurring\RecurrenceInterval;
use App\Modules\Finance\Domain\Recurring\RecurrenceSchedule;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceRunRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceTemplateRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceTemplateVersionRecord;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class EloquentRecurringInvoiceRepository implements RecurringInvoiceRepository
{
    private readonly IdempotencyStore $idempotency;

    private readonly Clock $clock;

    public function __construct(?IdempotencyStore $idempotency = null, ?Clock $clock = null)
    {
        $this->idempotency = $idempotency ?? app(IdempotencyStore::class);
        $this->clock = $clock ?? app(Clock::class);
    }

    public function createTemplate(RecurringTemplateData $data, IdempotencyKey $key): RecurringTemplateView
    {
        $requestHash = $this->requestHash([
            'mode' => $data->mode,
            'interval' => $data->interval->value,
            'timezone' => $data->timezone,
            'start_date' => $data->startLocalDate(),
            'end_date' => $data->endLocalDate(),
            'run_time' => $data->runTime,
            'effective_from' => $data->initialVersion->effectiveLocalDate(),
            'snapshot_sha256' => $data->initialVersion->snapshotSha256,
        ]);

        return DB::transaction(function () use ($data, $key, $requestHash): RecurringTemplateView {
            $reservation = $this->idempotency->reserve('recurring.template.create', $key, $requestHash);
            if ($reservation['status'] === 'replay') {
                return $this->replayedView($reservation['response_payload']);
            }
            $this->assertNewReservation($reservation['status']);

            $ownerId = $this->ownerId();
            $this->assertOwnedReferences($ownerId, $data->initialVersion);
            $timestamp = $this->timestamp($this->clock->now());
            $templateId = (int) DB::table('finance_recurring_invoice_templates')->insertGetId([
                'user_id' => $ownerId,
                'uuid' => strtolower((string) Str::uuid()),
                'mode' => $data->mode,
                'interval' => $data->interval->value,
                'timezone' => $data->timezone,
                'start_date' => $data->startLocalDate(),
                'end_date' => $data->endLocalDate(),
                'run_time' => $data->runTime,
                'anchor_day' => $data->anchorDay(),
                'month_end_anchor' => $data->monthEndAnchor(),
                'next_run_at' => $this->timestamp($data->firstRunAt()),
                'status' => 'active',
                'paused_at' => null,
                'current_version_id' => null,
                'version' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $versionId = $this->insertVersion(
                $ownerId,
                $templateId,
                1,
                $data->initialVersion,
                $timestamp,
            );
            DB::table('finance_recurring_invoice_templates')
                ->where('user_id', $ownerId)
                ->where('id', $templateId)
                ->update(['current_version_id' => $versionId]);

            $view = $this->view($this->lockedTemplate(new RecurringTemplateId($templateId)));
            $this->idempotency->complete($reservation['record_id'], 201, $view->toArray());

            return $view;
        }, 1);
    }

    public function addTemplateVersion(
        RecurringTemplateId $id,
        RecurringTemplateVersionData $data,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView {
        $requestHash = $this->requestHash([
            'template_id' => $id->value,
            'expected_version' => $expectedVersion,
            'effective_from' => $data->effectiveLocalDate(),
            'snapshot_sha256' => $data->snapshotSha256,
        ]);

        return DB::transaction(function () use ($id, $data, $expectedVersion, $key, $requestHash): RecurringTemplateView {
            $reservation = $this->idempotency->reserve('recurring.template.version', $key, $requestHash);
            if ($reservation['status'] === 'replay') {
                return $this->replayedView($reservation['response_payload']);
            }
            $this->assertNewReservation($reservation['status']);

            $template = $this->lockedTemplate($id);
            $current = $this->view($template);
            if ((int) $template->version !== $expectedVersion) {
                throw new RecurringTemplateVersionConflict($current);
            }
            if ((string) $template->status === 'completed') {
                throw new DomainException('recurring_template_completed');
            }
            if ($data->effectiveLocalDate() < $this->dateValue($template->getAttribute('start_date'))) {
                throw new DomainException('recurring_template_effective_date_before_start');
            }

            $ownerId = $this->ownerId();
            $this->assertOwnedReferences($ownerId, $data);
            if (RecurringInvoiceTemplateVersionRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('template_id', $id->value)
                ->whereDate('effective_from', $data->effectiveLocalDate())
                ->exists()) {
                throw new DomainException('recurring_template_effective_date_conflict');
            }

            $maximumVersion = RecurringInvoiceTemplateVersionRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('template_id', $id->value)
                ->max('version_number');
            if (! is_int($maximumVersion) && (! is_string($maximumVersion) || ! ctype_digit($maximumVersion))) {
                throw new LogicException('Recurring template version sequence is invalid.');
            }
            $versionNumber = (int) $maximumVersion + 1;
            $timestamp = $this->timestamp($this->clock->now());
            $versionId = $this->insertVersion($ownerId, $id->value, $versionNumber, $data, $timestamp);
            $updated = DB::table('finance_recurring_invoice_templates')
                ->where('user_id', $ownerId)
                ->where('id', $id->value)
                ->where('version', $expectedVersion)
                ->update([
                    'current_version_id' => $versionId,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $timestamp,
                ]);
            if ($updated !== 1) {
                throw new RecurringTemplateVersionConflict($this->view($this->lockedTemplate($id)));
            }

            $view = $this->view($this->lockedTemplate($id));
            $this->idempotency->complete($reservation['record_id'], 200, $view->toArray());

            return $view;
        }, 1);
    }

    public function pauseTemplate(
        RecurringTemplateId $id,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView {
        return $this->transitionTemplate($id, $expectedVersion, $key, 'pause');
    }

    public function resumeTemplate(
        RecurringTemplateId $id,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView {
        return $this->transitionTemplate($id, $expectedVersion, $key, 'resume');
    }

    public function versionForOccurrence(RecurringTemplateId $id, DateTimeImmutable $localDate): array
    {
        $template = $this->ownedTemplate($id);
        $version = RecurringInvoiceTemplateVersionRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->where('template_id', $template->id)
            ->whereDate('effective_from', '<=', $localDate->format('Y-m-d'))
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number')
            ->firstOrFail();
        $snapshot = $this->objectArray($version->getAttribute('draft_snapshot'));

        return [
            'id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'effective_from' => $this->dateValue($version->getAttribute('effective_from')),
            'draft_snapshot' => $snapshot,
            'snapshot_sha256' => (string) $version->snapshot_sha256,
        ];
    }

    public function template(RecurringTemplateId $id): array
    {
        return $this->templateData($this->ownedTemplate($id));
    }

    public function run(RecurringRunId $id): array
    {
        return $this->runData($this->ownedRun($id));
    }

    public function templates(int $page = 1, int $perPage = 25): array
    {
        $this->assertPagination($page, $perPage);
        $query = RecurringInvoiceTemplateRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId());
        $total = (clone $query)->count();
        $items = array_values($query->orderBy('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (RecurringInvoiceTemplateRecord $template): array => $this->templateData($template))
            ->all());

        return ['items' => $items, 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    public function runs(int $page = 1, int $perPage = 25): array
    {
        $this->assertPagination($page, $perPage);
        $query = RecurringInvoiceRunRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId());
        $total = (clone $query)->count();
        $items = array_values($query->orderBy('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (RecurringInvoiceRunRecord $run): array => $this->runData($run))
            ->all());

        return ['items' => $items, 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    public function withLockedTemplate(RecurringTemplateId $id, Closure $callback): mixed
    {
        return DB::transaction(function () use ($id, $callback): mixed {
            $template = RecurringInvoiceTemplateRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $this->ownerId())
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();

            return $callback($this->templateData($template));
        }, 1);
    }

    public function withLockedRun(RecurringRunId $id, Closure $callback): mixed
    {
        return DB::transaction(function () use ($id, $callback): mixed {
            $locator = $this->ownedRun($id);
            RecurringInvoiceTemplateRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $this->ownerId())
                ->whereKey($locator->template_id)
                ->lockForUpdate()
                ->firstOrFail(['id']);
            $run = RecurringInvoiceRunRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $this->ownerId())
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();

            return $callback($this->runData($run));
        }, 1);
    }

    public function claimDueRuns(DateTimeImmutable $asOf, int $globalCap, int $perTemplateCap): array
    {
        if ($globalCap < 1 || $perTemplateCap < 1) {
            throw new InvalidArgumentException('Recurring claim caps must be positive.');
        }

        $asOfTimestamp = $this->timestamp($asOf);
        $claimed = [];

        DB::transaction(function () use ($asOf, $asOfTimestamp, $globalCap, $perTemplateCap, &$claimed): void {
            $driver = DB::connection()->getDriverName();
            $templates = $driver === 'pgsql'
                ? DB::select(
                    'select * from finance_recurring_invoice_templates '
                    ."where status = 'active' and next_run_at <= ? "
                    .'order by next_run_at, id for update skip locked',
                    [$asOfTimestamp],
                )
                : DB::table('finance_recurring_invoice_templates')
                    ->where('status', 'active')
                    ->where('next_run_at', '<=', $asOfTimestamp)
                    ->orderBy('next_run_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->all();

            foreach ($templates as $template) {
                if (count($claimed) >= $globalCap) {
                    break;
                }

                $claimed = [
                    ...$claimed,
                    ...$this->claimTemplateOccurrences(
                        $template,
                        $asOf,
                        min($perTemplateCap, $globalCap - count($claimed)),
                    ),
                ];
            }
        }, 1);

        return $claimed;
    }

    public function inFlightRuns(int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Recurring in-flight run limit must be positive.');
        }

        return DB::table('finance_recurring_invoice_runs')
            ->whereIn('status', ['pending', 'creating_draft', 'draft_created', 'finalizing', 'finalized', 'sending'])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'user_id', 'uuid'])
            ->map(static fn (object $row): array => [
                'run_id' => (int) $row->id,
                'owner_id' => (int) $row->user_id,
                'uuid' => (string) $row->uuid,
            ])
            ->all();
    }

    public function transitionRun(
        RecurringRunId $id,
        string $toStatus,
        ?string $completedStep,
        ?int $invoiceId,
        ?int $deliveryId,
        ?string $errorCode,
        ?string $errorDetail,
    ): array {
        return DB::transaction(function () use (
            $id,
            $toStatus,
            $completedStep,
            $invoiceId,
            $deliveryId,
            $errorCode,
            $errorDetail,
        ): array {
            $ownerId = $this->ownerId();
            $locator = $this->ownedRun($id);
            RecurringInvoiceTemplateRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($locator->template_id)
                ->lockForUpdate()
                ->firstOrFail(['id']);
            $run = RecurringInvoiceRunRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = (string) $run->status;
            $fromStep = $run->last_completed_step === null ? null : (string) $run->last_completed_step;
            $this->assertRunTransition($fromStatus, $fromStep, $toStatus);

            $timestamp = $this->timestamp($this->clock->now());
            $attempts = (int) $run->attempts;
            if ($fromStatus === 'pending' && in_array($toStatus, ['creating_draft', 'finalizing', 'sending'], true)) {
                $attempts++;
            }

            $updated = DB::table('finance_recurring_invoice_runs')
                ->where('user_id', $ownerId)
                ->where('id', $id->value)
                ->where('status', $fromStatus)
                ->update([
                    'status' => $toStatus,
                    'last_completed_step' => $completedStep ?? $run->last_completed_step,
                    'invoice_id' => $invoiceId ?? $run->invoice_id,
                    'delivery_id' => $deliveryId ?? $run->delivery_id,
                    'attempts' => $attempts,
                    'last_error_code' => $toStatus === 'failed' ? $errorCode : null,
                    'last_error_detail' => $toStatus === 'failed' ? $errorDetail : null,
                    'next_retry_at' => $toStatus === 'failed' ? $timestamp : null,
                    'updated_at' => $timestamp,
                ]);
            if ($updated !== 1) {
                throw new LogicException('recurring_run_transition_conflict');
            }

            return $this->runData($this->ownedRun($id));
        }, 1);
    }

    /** @return list<array{run_id: int, owner_id: int, uuid: string}> */
    private function claimTemplateOccurrences(object $template, DateTimeImmutable $asOf, int $cap): array
    {
        if ($cap < 1) {
            return [];
        }

        $ownerId = (int) $template->user_id;
        $schedule = $this->scheduleFromTemplateRow($template);
        $cursor = $this->parseTimestamp((string) $template->next_run_at);
        $claimed = [];
        $completed = false;

        while (count($claimed) < $cap && $cursor <= $asOf) {
            $version = $this->versionForOccurrenceRow((int) $template->id, $ownerId, $cursor);
            $uuid = strtolower((string) Str::uuid());
            $timestamp = $this->timestamp($this->clock->now());
            $runId = (int) DB::table('finance_recurring_invoice_runs')->insertGetId([
                'user_id' => $ownerId,
                'uuid' => $uuid,
                'template_id' => (int) $template->id,
                'template_version_id' => $version['id'],
                'scheduled_for' => $this->timestamp($cursor),
                'scheduled_local_date' => $cursor->setTimezone($schedule->start()->getTimezone())->format('Y-m-d'),
                'status' => 'pending',
                'attempts' => 0,
                'idempotency_key_hash' => hash('sha256', 'recurring.run.claim:'.$template->id.':'.$cursor->format(DATE_ATOM)),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $claimed[] = ['run_id' => $runId, 'owner_id' => $ownerId, 'uuid' => $uuid];

            $next = $schedule->nextAfter($cursor);
            if ($next === null) {
                $completed = true;
                break;
            }
            $cursor = $next;
        }

        if ($claimed === []) {
            return [];
        }

        $timestamp = $this->timestamp($this->clock->now());
        DB::table('finance_recurring_invoice_templates')
            ->where('user_id', $ownerId)
            ->where('id', $template->id)
            ->update($completed
                ? ['status' => 'completed', 'paused_at' => null, 'next_run_at' => null, 'updated_at' => $timestamp]
                : ['next_run_at' => $this->timestamp($cursor), 'updated_at' => $timestamp]);

        return $claimed;
    }

    /** @return array{id: int, effective_from: string} */
    private function versionForOccurrenceRow(int $templateId, int $ownerId, DateTimeImmutable $occurrence): array
    {
        $version = RecurringInvoiceTemplateVersionRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('template_id', $templateId)
            ->whereDate('effective_from', '<=', $occurrence->format('Y-m-d'))
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number')
            ->firstOrFail(['id', 'effective_from']);

        return ['id' => (int) $version->id, 'effective_from' => $this->dateValue($version->getAttribute('effective_from'))];
    }

    private function scheduleFromTemplateRow(object $template): RecurrenceSchedule
    {
        return RecurrenceSchedule::fromLocal(
            RecurrenceInterval::from((string) $template->interval),
            $this->dateValue($template->start_date),
            (string) $template->run_time,
            (string) $template->timezone,
            $template->end_date === null ? null : $this->dateValue($template->end_date),
        );
    }

    private function parseTimestamp(string $value): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'))
            ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        if ($parsed === false) {
            throw new LogicException('Recurring template next run timestamp is invalid.');
        }

        return $parsed;
    }

    private function assertRunTransition(string $from, ?string $completedStep, string $to): void
    {
        $allowed = match (true) {
            $from === 'pending' && $completedStep === null => ['creating_draft', 'failed'],
            $from === 'pending' && $completedStep === 'draft_created' => ['finalizing', 'failed'],
            $from === 'pending' && in_array($completedStep, ['finalized', 'delivery_staged'], true) => ['sending', 'failed'],
            $from === 'creating_draft' => ['draft_created', 'failed'],
            $from === 'draft_created' => ['finalizing', 'failed'],
            $from === 'finalizing' => ['finalized', 'failed'],
            $from === 'finalized' => ['sending', 'failed'],
            $from === 'sending' => ['sent', 'failed'],
            $from === 'failed' => ['pending'],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new DomainException('recurring_run_transition_invalid');
        }
    }

    private function ownedTemplate(RecurringTemplateId $id): RecurringInvoiceTemplateRecord
    {
        return RecurringInvoiceTemplateRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id->value);
    }

    private function lockedTemplate(RecurringTemplateId $id): RecurringInvoiceTemplateRecord
    {
        return RecurringInvoiceTemplateRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->whereKey($id->value)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ownedRun(RecurringRunId $id): RecurringInvoiceRunRecord
    {
        return RecurringInvoiceRunRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id->value);
    }

    private function ownerId(): int
    {
        $ownerId = Auth::id();

        if (! is_numeric($ownerId) || (int) $ownerId < 1) {
            throw new LogicException('Recurring invoice persistence requires an authenticated owner.');
        }

        return (int) $ownerId;
    }

    private function transitionTemplate(
        RecurringTemplateId $id,
        int $expectedVersion,
        IdempotencyKey $key,
        string $action,
    ): RecurringTemplateView {
        $requestHash = $this->requestHash([
            'template_id' => $id->value,
            'expected_version' => $expectedVersion,
            'action' => $action,
        ]);

        return DB::transaction(function () use ($id, $expectedVersion, $key, $action, $requestHash): RecurringTemplateView {
            $reservation = $this->idempotency->reserve('recurring.template.'.$action, $key, $requestHash);
            if ($reservation['status'] === 'replay') {
                return $this->replayedView($reservation['response_payload']);
            }
            $this->assertNewReservation($reservation['status']);

            $template = $this->lockedTemplate($id);
            $current = $this->view($template);
            if ((int) $template->version !== $expectedVersion) {
                throw new RecurringTemplateVersionConflict($current);
            }
            $status = (string) $template->status;
            if ($status === 'completed') {
                throw new DomainException('recurring_template_completed');
            }
            if ($action === 'pause' && $status !== 'active') {
                throw new DomainException('recurring_template_already_paused');
            }
            if ($action === 'resume' && $status !== 'paused') {
                throw new DomainException('recurring_template_not_paused');
            }

            $timestamp = $this->timestamp($this->clock->now());
            $updated = DB::table('finance_recurring_invoice_templates')
                ->where('user_id', $this->ownerId())
                ->where('id', $id->value)
                ->where('version', $expectedVersion)
                ->update([
                    'status' => $action === 'pause' ? 'paused' : 'active',
                    'paused_at' => $action === 'pause' ? $timestamp : null,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $timestamp,
                ]);
            if ($updated !== 1) {
                throw new RecurringTemplateVersionConflict($this->view($this->lockedTemplate($id)));
            }

            $view = $this->view($this->lockedTemplate($id));
            $this->idempotency->complete($reservation['record_id'], 200, $view->toArray());

            return $view;
        }, 1);
    }

    private function assertOwnedReferences(int $ownerId, RecurringTemplateVersionData $data): void
    {
        $draft = $data->draft;
        if ($draft->partnerId !== null && ! DB::table('finance_partners')
            ->where('id', $draft->partnerId)
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->exists()) {
            throw (new ModelNotFoundException)->setModel(FinancePartner::class, [$draft->partnerId]);
        }
        if ($draft->projectId !== null && ! DB::table('finance_projects')
            ->where('id', $draft->projectId)
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->exists()) {
            throw (new ModelNotFoundException)->setModel(FinanceProject::class, [$draft->projectId]);
        }

        $productIds = array_values(array_unique(array_filter(
            array_map(static fn ($line): ?int => $line->productId, $draft->lines),
            static fn (?int $productId): bool => $productId !== null,
        )));
        if ($productIds !== [] && DB::table('finance_products')
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->whereIn('id', $productIds)
            ->count() !== count($productIds)) {
            throw (new ModelNotFoundException)->setModel(FinanceProduct::class, $productIds);
        }
    }

    private function insertVersion(
        int $ownerId,
        int $templateId,
        int $versionNumber,
        RecurringTemplateVersionData $data,
        string $timestamp,
    ): int {
        return (int) DB::table('finance_recurring_invoice_template_versions')->insertGetId([
            'user_id' => $ownerId,
            'template_id' => $templateId,
            'version_number' => $versionNumber,
            'effective_from' => $data->effectiveLocalDate(),
            'draft_snapshot' => json_encode($data->snapshot, JSON_THROW_ON_ERROR),
            'snapshot_sha256' => $data->snapshotSha256,
            'created_by' => $ownerId,
            'created_at' => $timestamp,
        ]);
    }

    /** @param array<string, mixed>|null $payload */
    private function replayedView(?array $payload): RecurringTemplateView
    {
        if ($payload === null) {
            throw new DomainException('operation_in_progress');
        }

        return RecurringTemplateView::fromArray($payload);
    }

    private function assertNewReservation(string $status): void
    {
        if ($status !== 'new') {
            throw new DomainException('operation_in_progress');
        }
    }

    private function view(RecurringInvoiceTemplateRecord $template): RecurringTemplateView
    {
        $versionId = $template->getAttribute('current_version_id');
        if (! is_int($versionId) || $versionId < 1) {
            throw new LogicException('Recurring template current version is missing.');
        }
        $version = RecurringInvoiceTemplateVersionRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->where('template_id', $template->id)
            ->whereKey($versionId)
            ->firstOrFail();
        $nextRunAt = $template->next_run_at;
        if (! $nextRunAt instanceof DateTimeImmutable) {
            throw new LogicException('Recurring template next run is missing.');
        }
        $pausedAt = $template->paused_at;
        if ($pausedAt !== null && ! $pausedAt instanceof DateTimeImmutable) {
            throw new LogicException('Recurring template pause timestamp is invalid.');
        }

        return new RecurringTemplateView(
            new RecurringTemplateId((int) $template->id),
            (string) $template->uuid,
            (string) $template->mode,
            (string) $template->interval,
            (string) $template->timezone,
            $this->dateValue($template->getAttribute('start_date')),
            $this->nullableDateValue($template->getAttribute('end_date')),
            (string) $template->run_time,
            (int) $template->anchor_day,
            (bool) $template->month_end_anchor,
            $nextRunAt,
            (string) $template->status,
            $pausedAt,
            $versionId,
            (int) $version->version_number,
            $this->dateValue($version->getAttribute('effective_from')),
            (string) $version->snapshot_sha256,
            (int) $template->version,
        );
    }

    /** @param array<string, mixed> $payload */
    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function timestamp(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}/D', $value) === 1) {
            return substr($value, 0, 10);
        }

        throw new LogicException('Recurring template local date is invalid.');
    }

    private function nullableDateValue(mixed $value): ?string
    {
        return $value === null ? null : $this->dateValue($value);
    }

    /** @return array<string, mixed> */
    private function objectArray(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LogicException('Recurring template version snapshot is invalid.');
        }
        $object = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LogicException('Recurring template version snapshot must be an object.');
            }
            $object[$key] = $item;
        }

        return $object;
    }

    private function assertPagination(int $page, int $perPage): void
    {
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Recurring invoice pagination is invalid.');
        }
    }

    /** @return array<string, mixed> */
    private function templateData(RecurringInvoiceTemplateRecord $template): array
    {
        return $template->only([
            'id', 'uuid', 'mode', 'interval', 'timezone', 'start_date', 'end_date',
            'run_time', 'anchor_day', 'month_end_anchor', 'next_run_at', 'status',
            'paused_at', 'current_version_id', 'version', 'created_at', 'updated_at',
        ]);
    }

    /** @return array<string, mixed> */
    private function runData(RecurringInvoiceRunRecord $run): array
    {
        return $run->only([
            'id', 'uuid', 'template_id', 'template_version_id', 'scheduled_for',
            'scheduled_local_date', 'status', 'last_completed_step', 'invoice_id',
            'delivery_id', 'attempts', 'claimed_at', 'claim_expires_at', 'next_retry_at',
            'last_error_code', 'last_error_detail', 'created_at', 'updated_at',
        ]);
    }
}
