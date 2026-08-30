<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use Closure;
use DateTimeImmutable;

interface RecurringInvoiceRepository
{
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

    /** @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int} */
    public function templates(int $page = 1, int $perPage = 25): array;

    /** @return array{items: list<array<string, mixed>>, page: int, per_page: int, total: int} */
    public function runs(int $page = 1, int $perPage = 25): array;

    public function withLockedTemplate(RecurringTemplateId $id, Closure $callback): mixed;

    public function withLockedRun(RecurringRunId $id, Closure $callback): mixed;
}
