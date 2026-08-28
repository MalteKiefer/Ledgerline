<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceRunRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\RecurringInvoiceTemplateRecord;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class EloquentRecurringInvoiceRepository implements RecurringInvoiceRepository
{
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

    private function ownedTemplate(RecurringTemplateId $id): RecurringInvoiceTemplateRecord
    {
        return RecurringInvoiceTemplateRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id->value);
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
