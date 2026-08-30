<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\BankTransaction;
use App\Models\FileEntry;
use App\Models\FinanceProject;
use App\Models\FinanceReceipt;
use App\Models\GalleryPhoto;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use App\Modules\Finance\Domain\Projects\WorkItemStatus;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Compatibility\Exception\LegacyProjectExpenseMalformed;

/**
 * Produces a deterministic, per-project mapping plan plus diagnostics from
 * the legacy `finance_projects` aggregate (project, tasks, time entries,
 * expenses, and cross-module evidence pointers) into the shape the v2
 * schema expects.
 *
 * This class only describes what the global migration plan should write —
 * it never touches `finance_project_*` tables itself, mutates the legacy
 * row, or performs I/O beyond reading the legacy aggregate and its
 * cross-module evidence pointers. Idempotency comes from the deterministic
 * `source_type=legacy.finance_project` / legacy id pairing: mapping the same
 * project twice always yields the same plan.
 *
 * Anything that cannot be represented exactly (malformed expense JSON, a
 * currency mismatch, a cross-owner evidence pointer, an unparseable
 * quantity) becomes a blocking {@see LegacyProjectDiagnostic} attached to the
 * result rather than a silently skipped row or a coerced value.
 *
 * @phpstan-type MappingResult array{
 *     source_type: string,
 *     source_id: int,
 *     owner_id: int,
 *     project: array<string, mixed>,
 *     note: array<string, mixed>|null,
 *     work_items: list<array<string, mixed>>,
 *     time_entries: list<array<string, mixed>>,
 *     ledger_entries: list<array<string, mixed>>,
 *     document_links: list<array<string, mixed>>,
 *     diagnostics: list<LegacyProjectDiagnostic>,
 * }
 */
final class LegacyProjectMapper
{
    public function __construct(private readonly LegacyProjectExpenseParser $expenseParser = new LegacyProjectExpenseParser) {}

    /**
     * @return MappingResult
     */
    public function map(FinanceProject $project, string $defaultCurrency = 'EUR'): array
    {
        $diagnostics = [];
        $ownerId = (int) $project->user_id;
        $currency = strtoupper($defaultCurrency);

        $status = ProjectStatus::tryFrom((string) $project->status);
        if ($status === null) {
            $diagnostics[] = new LegacyProjectDiagnostic('project_status_unknown', true, "Legacy project #{$project->id} has an unmapped status.", ['status' => $project->status]);
        }
        $kind = ProjectKind::tryFrom((string) $project->kind);
        if ($kind === null) {
            $diagnostics[] = new LegacyProjectDiagnostic('project_kind_unknown', true, "Legacy project #{$project->id} has an unmapped kind.", ['kind' => $project->kind]);
        }

        $budgetMinor = null;
        $rawBudget = $this->rawText($project->getRawOriginal('budget_net'));
        if ($rawBudget !== null && $rawBudget !== '') {
            try {
                $budgetMinor = Money::fromDecimal($rawBudget, $currency)->minor();
            } catch (InvalidMoney $exception) {
                $diagnostics[] = new LegacyProjectDiagnostic('project_budget_invalid', true, "Legacy project #{$project->id} budget is not exact: {$exception->getMessage()}.");
            }
        }

        $partnerReference = $project->partner_id !== null ? "legacy-partner:{$project->partner_id}" : null;
        $quoteReference = $project->quote_id !== null ? "legacy-quote-unresolved:{$project->quote_id}" : null;
        if ($quoteReference !== null) {
            $diagnostics[] = new LegacyProjectDiagnostic('project_quote_unresolved', false, "Legacy project #{$project->id} references quote #{$project->quote_id}; it stays an unresolved pinned source until quote migration resolves the revision.", ['quote_id' => $project->quote_id]);
        }

        $projectPlan = [
            'name' => (string) $project->name,
            'kind' => $kind?->value ?? (string) $project->kind,
            'status' => $status?->value ?? (string) $project->status,
            'starts_on' => $project->starts_on?->toDateString(),
            'due_on' => $project->due_on?->toDateString(),
            'budget_minor' => $budgetMinor,
            'currency' => $currency,
            'partner_reference' => $partnerReference,
            'parent_source_id' => $project->parent_id,
            'quote_reference' => $quoteReference,
            'archived' => $project->trashed(),
        ];

        $note = null;
        $rawNote = trim((string) ($project->note ?? ''));
        if ($rawNote !== '') {
            $note = ['type' => 'note', 'visibility' => 'internal', 'body' => $rawNote];
        }

        [$workItems, $workDiagnostics] = $this->mapWorkItems($project, $currency);
        [$timeEntries, $timeDiagnostics] = $this->mapTimeEntries($project, $currency);
        [$ledgerEntries, $ledgerDiagnostics] = $this->mapLedgerEntries($project, $currency);
        [$documentLinks, $documentDiagnostics] = $this->mapDocumentLinks($project);

        return [
            'source_type' => 'legacy.finance_project',
            'source_id' => (int) $project->id,
            'owner_id' => $ownerId,
            'project' => $projectPlan,
            'note' => $note,
            'work_items' => $workItems,
            'time_entries' => $timeEntries,
            'ledger_entries' => $ledgerEntries,
            'document_links' => $documentLinks,
            'diagnostics' => [...$diagnostics, ...$workDiagnostics, ...$timeDiagnostics, ...$ledgerDiagnostics, ...$documentDiagnostics],
        ];
    }

    /**
     * @param  MappingResult  $result
     */
    public static function isBlocking(array $result): bool
    {
        foreach ($result['diagnostics'] as $diagnostic) {
            if ($diagnostic->blocking) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<LegacyProjectDiagnostic>}
     */
    private function mapWorkItems(FinanceProject $project, string $currency): array
    {
        $items = [];
        $diagnostics = [];
        foreach ($project->tasks as $task) {
            $status = WorkItemStatus::tryFrom((string) $task->status);
            if ($status === null) {
                $diagnostics[] = new LegacyProjectDiagnostic('task_status_unknown', true, "Legacy task #{$task->id} has an unmapped status.", ['status' => $task->status]);

                continue;
            }

            $estimateScaled = null;
            $rawEstimate = $this->rawText($task->getRawOriginal('estimate_hours'));
            if ($rawEstimate !== null && $rawEstimate !== '') {
                if ($task->is_milestone) {
                    $diagnostics[] = new LegacyProjectDiagnostic('task_milestone_with_estimate', true, "Legacy milestone task #{$task->id} carries an estimate; a milestone accepts no estimated quantity.");
                } else {
                    try {
                        $estimateScaled = DecimalQuantity::fromString($rawEstimate)->scaled();
                    } catch (InvalidQuantity $exception) {
                        $diagnostics[] = new LegacyProjectDiagnostic('task_estimate_invalid', true, "Legacy task #{$task->id} estimate is not exact: {$exception->getMessage()}.");
                    }
                }
            }

            $items[] = [
                'source_id' => $task->id,
                'title' => (string) $task->title,
                'description' => $task->description,
                'status' => $status->value,
                'starts_on' => $task->starts_on?->toDateString(),
                'due_on' => $task->due_on?->toDateString(),
                'estimate_quantity_scaled' => $estimateScaled,
                'is_milestone' => (bool) $task->is_milestone,
                'sort' => (int) $task->sort,
                'product_reference' => $task->finance_product_id !== null ? "legacy-product:{$task->finance_product_id}" : null,
                'currency' => $currency,
                'deleted' => $task->trashed(),
            ];
        }

        return [$items, $diagnostics];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<LegacyProjectDiagnostic>}
     */
    private function mapTimeEntries(FinanceProject $project, string $currency): array
    {
        $entries = [];
        $diagnostics = [];
        foreach ($project->timeEntries as $entry) {
            $rawHours = $this->rawText($entry->getRawOriginal('hours')) ?? '';
            try {
                $quantityScaled = DecimalQuantity::fromString($rawHours)->scaled();
            } catch (InvalidQuantity $exception) {
                $diagnostics[] = new LegacyProjectDiagnostic('time_entry_hours_invalid', true, "Legacy time entry #{$entry->id} hours are not exact: {$exception->getMessage()}.");

                continue;
            }
            if ($quantityScaled === 0) {
                $diagnostics[] = new LegacyProjectDiagnostic('time_entry_hours_zero', true, "Legacy time entry #{$entry->id} has zero hours.");

                continue;
            }

            $rateMinor = null;
            $rawRate = $this->rawText($entry->getRawOriginal('hourly_rate'));
            if ($rawRate !== null && $rawRate !== '') {
                try {
                    $rateMinor = Money::fromDecimal($rawRate, $currency)->minor();
                } catch (InvalidMoney $exception) {
                    $diagnostics[] = new LegacyProjectDiagnostic('time_entry_rate_invalid', true, "Legacy time entry #{$entry->id} rate is not exact: {$exception->getMessage()}.");
                }
            } else {
                $diagnostics[] = new LegacyProjectDiagnostic('time_entry_rate_missing', false, "Legacy time entry #{$entry->id} has no frozen rate; the migrated row keeps an unset rate.");
            }

            $invoiceTargetReference = null;
            if ($entry->invoiced_invoice_id !== null) {
                $invoiceTargetReference = "legacy-invoice:{$entry->invoiced_invoice_id}";
                $diagnostics[] = new LegacyProjectDiagnostic('time_entry_invoice_unresolved', false, "Legacy time entry #{$entry->id} was invoiced under legacy invoice #{$entry->invoiced_invoice_id}; the migrated row keeps an opaque unresolved invoice target.", ['invoice_id' => $entry->invoiced_invoice_id]);
            }

            $entries[] = [
                'source_id' => $entry->id,
                'work_item_source_id' => $entry->finance_project_task_id,
                'worked_on' => $entry->date?->toDateString(),
                'quantity_scaled' => $quantityScaled,
                'description' => $entry->description,
                'billable' => (bool) $entry->billable,
                'hourly_rate_minor' => $rateMinor,
                'currency' => $currency,
                'invoice_target_reference' => $invoiceTargetReference,
                'invoiced' => $entry->invoiced_invoice_id !== null,
                'deleted' => $entry->trashed(),
            ];
        }

        return [$entries, $diagnostics];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<LegacyProjectDiagnostic>}
     */
    private function mapLedgerEntries(FinanceProject $project, string $currency): array
    {
        $raw = $this->rawText($project->getRawOriginal('expenses'));
        if ($raw === null || trim($raw) === '') {
            return [[], []];
        }

        try {
            $rows = $this->expenseParser->parse($raw, $currency);
        } catch (LegacyProjectExpenseMalformed $exception) {
            return [[], [new LegacyProjectDiagnostic($exception->errorCode, true, "Legacy project #{$project->id} expenses: {$exception->getMessage()}")]];
        }

        $entries = array_map(static fn (LegacyProjectExpenseRow $row): array => [
            'direction' => $row->direction,
            'amount_minor' => $row->amountMinor,
            'currency' => $row->currency,
            'occurred_on' => $row->occurredOn,
            'title' => $row->title,
            'note' => $row->note,
            'category_reference' => $row->categoryReference,
            'payment_method_reference' => $row->paymentMethodReference,
            'legacy_metadata' => $row->legacyMetadata,
        ], $rows);

        return [$entries, []];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<LegacyProjectDiagnostic>}
     */
    private function mapDocumentLinks(FinanceProject $project): array
    {
        $ownerId = (int) $project->user_id;
        $links = [];
        $diagnostics = [];

        $sources = [
            'file' => FileEntry::query()->withoutGlobalScope('owner')->where('finance_project_id', $project->id),
            'gallery_photo' => GalleryPhoto::query()->withoutGlobalScope('owner')->where('finance_project_id', $project->id),
            'finance_receipt' => FinanceReceipt::query()->withoutGlobalScope('owner')->where('finance_project_id', $project->id),
            'bank_transaction' => BankTransaction::query()->withoutGlobalScope('owner')->where('finance_project_id', $project->id),
        ];

        foreach ($sources as $kind => $query) {
            foreach ($query->get() as $record) {
                if ((int) $record->user_id !== $ownerId) {
                    $diagnostics[] = new LegacyProjectDiagnostic('document_link_cross_owner', true, "Legacy project #{$project->id} has a {$kind} evidence pointer owned by a different user.", ['record_id' => $record->id]);

                    continue;
                }

                $links[] = [
                    'source_type' => $kind,
                    'source_reference' => (string) $record->id,
                    'role' => match ($kind) {
                        'gallery_photo' => 'photo',
                        'finance_receipt' => 'receipt',
                        default => 'file',
                    },
                    'sha256' => $record->sha256 ?? null,
                    'available' => true,
                ];
            }
        }

        return [$links, $diagnostics];
    }

    /**
     * `getRawOriginal()` returns `mixed`. Every legacy decimal column this
     * mapper reads (`budget_net`, `estimate_hours`, `hours`, `hourly_rate`)
     * is `decimal(_, 2)`, so on drivers with a real DECIMAL type (production
     * PostgreSQL/MySQL) the raw value is already exact source text; SQLite
     * (used only in tests) has no DECIMAL storage class and hydrates the
     * same column as a PHP float instead. Formatting that float back to
     * exactly two fraction digits recovers the original exact value — the
     * column's own declared scale — rather than trusting the float's full,
     * imprecise binary expansion.
     */
    private function rawText(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_float($value) => number_format($value, 2, '.', ''),
            default => null,
        };
    }
}
