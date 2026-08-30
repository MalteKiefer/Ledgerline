<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Hours worked, against a project and optionally a task.
 *
 * `hourly_rate` is frozen on the entry rather than read from the partner when
 * invoicing: a rate change next year must not rewrite what last year's work was
 * worth.
 *
 * `invoiced_invoice_id` is set once and never cleared. That is the whole
 * protection against billing the same hour twice — an entry that has been billed
 * stops being available, and deleting the invoice leaves the entry flagged
 * rather than quietly returning it to the pool.
 *
 * @property int $id
 * @property int $user_id
 * @property int $finance_project_id
 * @property int|null $finance_project_task_id
 * @property Carbon $date
 * @property string $hours
 * @property string|null $description
 * @property bool $billable
 * @property string|null $hourly_rate
 * @property int|null $invoiced_invoice_id
 * @property int|null $invoiced_finance_invoice_id
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinanceTimeEntry extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = [
        'finance_project_id', 'finance_project_task_id', 'date', 'hours',
        'description', 'billable', 'hourly_rate',
    ];

    protected $casts = [
        'finance_project_id' => 'integer',
        'finance_project_task_id' => 'integer',
        'invoiced_invoice_id' => 'integer',
        'invoiced_finance_invoice_id' => 'integer',
        'date' => 'date',
        'hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'billable' => 'boolean',
        'version' => 'integer',
    ];

    /** @return BelongsTo<FinanceProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(FinanceProject::class, 'finance_project_id');
    }

    /** @return BelongsTo<FinanceProjectTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(FinanceProjectTask::class, 'finance_project_task_id');
    }

    /** Whether this hour is still waiting to go on an invoice. */
    public function isBillable(): bool
    {
        return $this->billable && ! $this->isInvoiced();
    }

    /** Whether this hour has already been billed, on either the legacy or the finance-v2 invoice. */
    public function isInvoiced(): bool
    {
        return $this->invoiced_invoice_id !== null || $this->invoiced_finance_invoice_id !== null;
    }
}
