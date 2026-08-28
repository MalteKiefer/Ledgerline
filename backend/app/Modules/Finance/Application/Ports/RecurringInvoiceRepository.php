<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use Closure;

interface RecurringInvoiceRepository
{
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
