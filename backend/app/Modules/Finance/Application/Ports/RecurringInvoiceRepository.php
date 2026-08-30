<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunPage;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunView;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplatePage;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use Closure;
use DateTimeImmutable;

interface RecurringInvoiceRepository
{
    /**
     * Resolves the owner-scoped internal ID for a public template UUID.
     * Throws (not-found) when the UUID does not belong to the current owner.
     */
    public function templateIdForUuid(string $uuid): RecurringTemplateId;

    /**
     * Resolves the owner-scoped internal ID for a public run UUID.
     * Throws (not-found) when the UUID does not belong to the current owner.
     */
    public function runIdForUuid(string $uuid): RecurringRunId;

    public function createTemplate(RecurringTemplateData $data, IdempotencyKey $key): RecurringTemplateView;

    public function addTemplateVersion(
        RecurringTemplateId $id,
        RecurringTemplateVersionData $data,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView;

    public function pauseTemplate(
        RecurringTemplateId $id,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView;

    public function resumeTemplate(
        RecurringTemplateId $id,
        int $expectedVersion,
        IdempotencyKey $key,
    ): RecurringTemplateView;

    /** @return array{id: int, version_number: int, effective_from: string, draft_snapshot: array<string, mixed>, snapshot_sha256: string} */
    public function versionForOccurrence(RecurringTemplateId $id, DateTimeImmutable $localDate): array;

    /** @return array<string, mixed> */
    public function template(RecurringTemplateId $id): array;

    /** @return array<string, mixed> */
    public function run(RecurringRunId $id): array;

    public function getView(RecurringTemplateId $id): RecurringTemplateView;

    public function getRunView(RecurringRunId $id): RecurringRunView;

    /** @param array<string, mixed> $filters */
    public function templates(array $filters, int $page, int $perPage): RecurringTemplatePage;

    /** @param array<string, mixed> $filters */
    public function runsForTemplate(RecurringTemplateId $id, array $filters, int $page, int $perPage): RecurringRunPage;

    public function withLockedTemplate(RecurringTemplateId $id, Closure $callback): mixed;

    public function withLockedRun(RecurringRunId $id, Closure $callback): mixed;

    /**
     * Claims due occurrences across every owner in one bounded, atomic tick.
     *
     * Advances each claimed template's `next_run_at` only through the
     * occurrences actually claimed, so a later tick resumes exactly where
     * this one stopped instead of skipping anything.
     *
     * @return list<array{run_id: int, owner_id: int, uuid: string}>
     */
    public function claimDueRuns(DateTimeImmutable $asOf, int $globalCap, int $perTemplateCap): array;

    /**
     * Lists non-terminal runs (neither `sent` nor `failed`) across every
     * owner so the scheduler can safely re-dispatch processing after a
     * crash or an unresolved async mail step, without ever needing to
     * skip an occurrence.
     *
     * @return list<array{run_id: int, owner_id: int, uuid: string}>
     */
    public function inFlightRuns(int $limit): array;

    /**
     * Applies exactly one forward run-state transition under the run's lock.
     * The allowed from/to pairs mirror the database progress-guard trigger
     * exactly, which remains the authoritative, defence-in-depth check.
     *
     * @return array<string, mixed> the persisted run row after the transition
     */
    public function transitionRun(
        RecurringRunId $id,
        string $toStatus,
        ?string $completedStep,
        ?int $invoiceId,
        ?int $deliveryId,
        ?string $errorCode,
        ?string $errorDetail,
    ): array;
}
