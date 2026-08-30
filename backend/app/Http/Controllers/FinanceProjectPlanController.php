<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OptimisticUpdates;
use App\Models\FinanceProject;
use App\Models\FinanceProjectTask;
use App\Models\FinanceQuote;
use App\Models\FinanceTimeEntry;
use App\Models\UserSetting;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectPlanInvoiceSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Project planning: tasks, hours, and the two conversions that make the chain a
 * chain — a quote becomes a project, and worked hours become invoice lines.
 *
 * Its own controller because FinanceController already carries invoices,
 * transactions, receipts, partners, projects and categories.
 */
class FinanceProjectPlanController extends Controller
{
    use OptimisticUpdates;

    /** How many rows one plan request returns. A plan longer than this is not a plan. */
    private const LIMIT = 1000;

    /**
     * Everything hanging off one project: its tasks, its hours, and the figures
     * that only make sense together (estimated against worked, billable still
     * waiting).
     */
    public function plan(FinanceProject $project): JsonResponse
    {
        $tasks = FinanceProjectTask::query()
            ->where('finance_project_id', $project->getKey())
            ->orderBy('sort')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $entries = FinanceTimeEntry::query()
            ->where('finance_project_id', $project->getKey())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $estimated = 0.0;
        $done = 0;
        foreach ($tasks as $task) {
            $estimated += (float) ($task->estimate_hours ?? 0);
            if ($task->status === 'done') {
                $done++;
            }
        }

        $worked = 0.0;
        $unbilled = 0.0;
        $unbilledValue = 0.0;
        foreach ($entries as $entry) {
            $worked += (float) $entry->hours;
            if ($entry->isBillable()) {
                $unbilled += (float) $entry->hours;
                $unbilledValue += (float) $entry->hours * (float) ($entry->hourly_rate ?? 0);
            }
        }

        return response()->json([
            'tasks' => $tasks,
            'entries' => $entries,
            'totals' => [
                // Progress is derived from the tasks, not stored: a stored
                // percentage is wrong the moment a task changes.
                'tasks' => $tasks->count(),
                'tasks_done' => $done,
                'estimate_hours' => round($estimated, 2),
                'worked_hours' => round($worked, 2),
                'unbilled_hours' => round($unbilled, 2),
                'unbilled_value' => round($unbilledValue, 2),
            ],
        ]);
    }

    // ---- Tasks ----

    /**
     * @return array<string, mixed>
     */
    private function taskRules(bool $creating): array
    {
        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['nullable', Rule::in(FinanceProjectTask::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'is_milestone' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'version' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPatch(Request $request): array
    {
        $patch = [];
        foreach (['title', 'description', 'status'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $patch[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }
        // Neither may be blank: a task with no title cannot be read, and a task
        // with no status is not in any column.
        foreach (['title', 'status'] as $required) {
            if (array_key_exists($required, $patch) && $patch[$required] === null) {
                unset($patch[$required]);
            }
        }
        foreach (['starts_on', 'due_on'] as $field) {
            if ($request->has($field)) {
                $raw = $request->input($field);
                $patch[$field] = is_string($raw) && $raw !== '' ? $raw : null;
            }
        }
        if ($request->has('estimate_hours')) {
            $raw = $request->input('estimate_hours');
            $patch['estimate_hours'] = is_numeric($raw) ? (float) $raw : null;
        }
        if ($request->has('is_milestone')) {
            $patch['is_milestone'] = $request->boolean('is_milestone');
        }
        if ($request->has('sort')) {
            $patch['sort'] = $request->integer('sort');
        }

        return $patch;
    }

    public function storeTask(Request $request, FinanceProject $project): JsonResponse
    {
        $request->validate($this->taskRules(true));

        $task = new FinanceProjectTask;
        $task->fill($this->taskPatch($request) + ['finance_project_id' => (int) $project->id]);
        if ($task->status === null) {
            $task->status = 'open';
        }
        if (! $request->has('sort')) {
            // Append: a new task belongs at the end until someone moves it.
            $max = FinanceProjectTask::query()->where('finance_project_id', $project->getKey())->max('sort');
            $task->sort = (is_numeric($max) ? (int) $max : 0) + 1;
        }
        $task->save();

        return response()->json(['task' => $task->fresh()], 201);
    }

    public function updateTask(Request $request, FinanceProjectTask $task): JsonResponse
    {
        $request->validate($this->taskRules(false));
        $id = (int) $task->id;
        $result = $this->optimistic(
            FinanceProjectTask::class,
            $id,
            $this->taskPatch($request),
            $request->has('version') ? $request->integer('version') : null,
        );

        return $this->optimisticJson($result, FinanceProjectTask::class, $id, 'task');
    }

    public function destroyTask(FinanceProjectTask $task): JsonResponse
    {
        // Work that was done stays logged; it simply loses the task it was filed
        // under. The detach has to be explicit: the task is soft-deleted, so the
        // column's nullOnDelete never fires and the hours would otherwise keep
        // pointing at a task nobody can see.
        DB::transaction(function () use ($task): void {
            FinanceTimeEntry::query()
                ->where('finance_project_task_id', $task->getKey())
                ->update(['finance_project_task_id' => null]);
            $task->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Reorder the tasks of a project in one call.
     *
     * One call rather than one per task, because a drag reorders many rows at
     * once and half an applied order is worse than none.
     */
    public function reorderTasks(Request $request, FinanceProject $project): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'max:'.self::LIMIT],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_filter((array) $request->input('ids'), 'is_numeric'));
        DB::transaction(function () use ($ids, $project): void {
            foreach ($ids as $position => $id) {
                // Scoped to the project as well as the owner: an id from another
                // project must not be pulled in by reordering.
                FinanceProjectTask::query()
                    ->where('finance_project_id', $project->getKey())
                    ->whereKey((int) $id)
                    ->update(['sort' => $position + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    // ---- Time entries ----

    public function storeTime(Request $request, FinanceProject $project): JsonResponse
    {
        $request->validate([
            'finance_project_task_id' => [
                'nullable', 'integer',
                Rule::exists('finance_project_tasks', 'id')->where('finance_project_id', $project->id),
            ],
            'date' => ['nullable', 'date'],
            'hours' => ['required', 'numeric', 'not_in:0', 'min:-24', 'max:24'],
            'description' => ['nullable', 'string', 'max:20000'],
            'billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $taskId = $request->input('finance_project_task_id');
        $date = $request->input('date');
        $entry = new FinanceTimeEntry;
        $entry->fill([
            'finance_project_id' => (int) $project->id,
            'finance_project_task_id' => is_numeric($taskId) ? (int) $taskId : null,
            'date' => is_string($date) && $date !== '' ? $date : Carbon::today()->toDateString(),
            'hours' => (float) $request->float('hours'),
            'description' => $request->string('description')->value() ?: null,
            'billable' => ! $request->has('billable') || $request->boolean('billable'),
            // Freeze the rate as it stands now: the customer's rate, else the
            // company default. Reading it at invoicing time would let a later
            // change rewrite what past work was worth.
            'hourly_rate' => $request->has('hourly_rate') && $request->filled('hourly_rate')
                ? $request->float('hourly_rate')
                : $this->rateFor($project),
        ]);
        $entry->save();

        return response()->json(['entry' => $entry->fresh()], 201);
    }

    public function updateTime(Request $request, FinanceTimeEntry $entry): JsonResponse
    {
        if ($entry->isInvoiced()) {
            // Already on an invoice. Editing the hours behind a document someone
            // has been sent would make the two disagree.
            return response()->json(['error' => 'time_invoiced'], 422);
        }

        $request->validate([
            'date' => ['sometimes', 'date'],
            'hours' => ['sometimes', 'numeric', 'not_in:0', 'min:-24', 'max:24'],
            'description' => ['nullable', 'string', 'max:20000'],
            'billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'finance_project_task_id' => [
                'nullable', 'integer',
                Rule::exists('finance_project_tasks', 'id')->where('finance_project_id', $entry->finance_project_id),
            ],
            'version' => ['nullable', 'integer'],
        ]);

        $patch = [];
        if ($request->has('date')) {
            $patch['date'] = $request->string('date')->value();
        }
        if ($request->has('hours')) {
            $patch['hours'] = (float) $request->float('hours');
        }
        if ($request->has('description')) {
            $patch['description'] = $request->string('description')->value() ?: null;
        }
        if ($request->has('billable')) {
            $patch['billable'] = $request->boolean('billable');
        }
        if ($request->has('hourly_rate')) {
            $patch['hourly_rate'] = $request->filled('hourly_rate') ? $request->float('hourly_rate') : null;
        }
        if ($request->has('finance_project_task_id')) {
            $raw = $request->input('finance_project_task_id');
            $patch['finance_project_task_id'] = is_numeric($raw) ? (int) $raw : null;
        }

        $id = (int) $entry->id;
        $result = $this->optimistic(
            FinanceTimeEntry::class,
            $id,
            $patch,
            $request->has('version') ? $request->integer('version') : null,
        );

        return $this->optimisticJson($result, FinanceTimeEntry::class, $id, 'entry');
    }

    public function destroyTime(FinanceTimeEntry $entry): JsonResponse
    {
        if ($entry->isInvoiced()) {
            return response()->json(['error' => 'time_invoiced'], 422);
        }
        $entry->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * The hourly rate to freeze onto a new entry: the customer's, else the
     * company default, else nothing.
     */
    private function rateFor(FinanceProject $project): ?float
    {
        if ($project->partner_id !== null) {
            $partner = $project->partner()->first();
            if ($partner !== null && $partner->hourly_rate !== null) {
                return (float) $partner->hourly_rate;
            }
        }

        return null;
    }

    // ---- The two conversions ----

    /**
     * Turn a quote into a project.
     *
     * Only the SERVICE lines become tasks, each carrying the hours it was quoted
     * at: hardware is something you deliver, not something you plan a day
     * around, and it is already accounted for on the invoice.
     */
    public function projectFromQuote(Request $request, FinanceQuote $quote): JsonResponse
    {
        if ($quote->converted_project_id !== null) {
            $existing = FinanceProject::query()->find($quote->converted_project_id);
            if ($existing instanceof FinanceProject) {
                // Idempotent: a second click opens the project it already made.
                return response()->json(['project' => $existing, 'already' => true]);
            }
        }

        $project = DB::transaction(function () use ($quote): FinanceProject {
            $project = new FinanceProject;
            $project->fill([
                'name' => $quote->title ?? ($quote->number ?? __('invoices.project')),
                'kind' => 'business',
                'status' => 'planned',
                'starts_on' => Carbon::today()->toDateString(),
                'budget_net' => $quote->net,
                'partner_id' => $quote->partner_id,
                'note' => $quote->note,
            ]);
            $project->forceFill(['quote_id' => $quote->getKey()]);
            $project->save();

            $sort = 0;
            foreach ((array) ($quote->lines ?? []) as $line) {
                if (! is_array($line) || ($line['kind'] ?? null) !== 'service') {
                    continue;
                }
                $desc = is_string($line['desc'] ?? null) ? trim($line['desc']) : '';
                if ($desc === '') {
                    continue;
                }
                // The first line of the description is the title; the rest is the
                // detail, because a quote line often carries both.
                $parts = explode("\n", $desc, 2);
                $task = new FinanceProjectTask;
                $task->fill([
                    'finance_project_id' => $project->getKey(),
                    'title' => mb_substr($parts[0], 0, 300),
                    'description' => $parts[1] ?? null,
                    'status' => 'open',
                    // The quoted quantity IS the estimate, which is the point of
                    // carrying it across.
                    'estimate_hours' => is_numeric($line['qty'] ?? null) ? (float) $line['qty'] : null,
                    'sort' => ++$sort,
                    'finance_product_id' => is_numeric($line['productId'] ?? null) ? (int) $line['productId'] : null,
                ]);
                $task->save();
            }

            $quote->forceFill([
                'converted_project_id' => $project->getKey(),
                'version' => (int) $quote->version + 1,
            ])->save();

            return $project;
        });

        return response()->json(['project' => $project->fresh()], 201);
    }

    /**
     * Bill the hours worked on a project.
     *
     * One invoice line per rate, not per entry: a customer wants "18.5 hours at
     * 120" and not eleven lines of the same rate. Entries with no rate are
     * grouped as a zero-rate line rather than dropped, because silently leaving
     * work off an invoice is the worse failure.
     *
     * Every entry taken is stamped with the invoice inside the same transaction,
     * which is what stops the same hour going out twice.
     */
    public function invoiceTime(Request $request, FinanceProject $project): JsonResponse
    {
        $request->validate([
            'until' => ['nullable', 'date'],
        ]);

        $until = $request->input('until');
        $query = FinanceTimeEntry::query()
            ->where('finance_project_id', $project->getKey())
            ->where('billable', true)
            ->whereNull('invoiced_invoice_id')
            ->whereNull('invoiced_finance_invoice_id');
        if (is_string($until) && $until !== '') {
            $query->whereDate('date', '<=', $until);
        }
        $entries = $query->orderBy('date')->get();

        if ($entries->isEmpty()) {
            return response()->json(['error' => 'nothing_to_invoice'], 422);
        }

        $uid = (int) $this->requireUser($request)->id;
        $settings = UserSetting::for($uid);
        $termsRaw = $settings->invoice_payment_terms_days;
        $terms = is_numeric($termsRaw) && (int) $termsRaw > 0 ? (int) $termsRaw : 14;
        $vatRaw = $settings->invoice_default_vat_rate;
        $vatRate = is_numeric($vatRaw) ? (float) $vatRaw : 19.0;

        /** @var array<string, array{hours: float, rate: float}> $byRate */
        $byRate = [];
        foreach ($entries as $entry) {
            $rate = (float) ($entry->hourly_rate ?? 0);
            $key = number_format($rate, 2, '.', '');
            $byRate[$key] ??= ['hours' => 0.0, 'rate' => $rate];
            $byRate[$key]['hours'] += (float) $entry->hours;
        }
        ksort($byRate);

        $partner = $project->partner_id !== null ? $project->partner()->first() : null;
        $lines = [];
        $net = 0.0;
        foreach ($byRate as $group) {
            $hours = round($group['hours'], 2);
            $lineNet = round($hours * $group['rate'], 2);
            $net += $lineNet;
            $lines[] = [
                'desc' => __('invoices.time_line', ['project' => (string) $project->name]),
                'qty' => $hours,
                'unit' => __('invoices.product_unit_hour_short'),
                'unitPrice' => $group['rate'],
                'vatRate' => $vatRate,
                'kind' => 'service',
                'productId' => null,
            ];
        }
        $net = round($net, 2);
        $vat = round($net * $vatRate / 100, 2);
        $customer = $partner !== null ? [
            'name' => $partner->name,
            'address' => $partner->address,
            'email' => $partner->invoice_email ?? $partner->email,
            'vatId' => $partner->vat_id,
            'partnerId' => $partner->id,
        ] : ['name' => (string) $project->name];

        $idempotencyKey = 'entries:'.$entries->pluck('id')->sort()->implode(',');

        $projectId = $project->id;
        if (! is_int($projectId)) {
            throw new \LogicException('Project identifier is invalid.');
        }

        $view = DB::transaction(function () use ($project, $customer, $lines, $terms, $entries, $idempotencyKey, $projectId): InvoiceView {
            $view = app(LegacyProjectPlanInvoiceSource::class)->convert(
                $projectId,
                $lines,
                $customer,
                $project->partner_id,
                'EUR',
                $terms,
                $idempotencyKey,
            );

            // Stamp inside the transaction: an entry that is on an invoice must
            // never be available to a second one.
            foreach ($entries as $entry) {
                $entry->forceFill([
                    'invoiced_finance_invoice_id' => $view->id->value,
                    'version' => (int) $entry->version + 1,
                ])->save();
            }

            return $view;
        });

        return response()->json([
            'invoice' => $this->invoiceJson($view),
            'entries' => $entries->count(),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function invoiceJson(InvoiceView $view): array
    {
        $snapshot = $view->snapshot;
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $rawLines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        $lines = array_map(static function (mixed $line): array {
            $line = is_array($line) ? $line : [];

            return [
                'desc' => is_string($line['description'] ?? null) ? $line['description'] : '',
                'qty' => is_numeric($line['quantity'] ?? null) ? (float) $line['quantity'] : 0.0,
                'unit' => is_string($line['unit'] ?? null) ? $line['unit'] : null,
                'unitPrice' => is_int($line['unit_price_minor'] ?? null) ? $line['unit_price_minor'] / 100 : 0.0,
                'vatRate' => is_int($line['tax_rate_basis_points'] ?? null) ? $line['tax_rate_basis_points'] / 100 : 0.0,
                'kind' => is_string($line['kind'] ?? null) ? $line['kind'] : null,
                'productId' => is_int($line['product_id'] ?? null) ? $line['product_id'] : null,
            ];
        }, $rawLines);

        return [
            'id' => $view->id->value,
            'number' => $view->number,
            'status' => $view->status === 'draft' ? 'draft' : ($view->status === 'finalized' ? 'final' : $view->status),
            'type' => 'invoice',
            'issue_date' => $view->issueDate->format('Y-m-d'),
            'due_date' => $view->dueDate->format('Y-m-d'),
            'currency' => $view->currency,
            'partner_id' => $view->partnerId,
            'customer' => $customer,
            'lines' => $lines,
            'net' => $this->minorDecimal($view->netMinor),
            'vat' => $this->minorDecimal($view->vatMinor),
            'gross' => $this->minorDecimal($view->grossMinor),
            'version' => $view->version,
            'created_at' => $view->createdAt->format(DATE_ATOM),
        ];
    }

    private function minorDecimal(int $minor): string
    {
        $negative = $minor < 0;
        $digits = str_pad(ltrim((string) abs($minor), '-'), 3, '0', STR_PAD_LEFT);
        $decimal = substr($digits, 0, -2).'.'.substr($digits, -2);

        return $negative ? '-'.$decimal : $decimal;
    }
}
