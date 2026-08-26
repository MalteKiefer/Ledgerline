<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A piece of work under a project.
 *
 * A milestone is the same row with `is_milestone` set: a date that matters with
 * no work in it. Modelling it separately would mean two lists to keep in order
 * for something the plan reads as one sequence.
 *
 * @property int $id
 * @property int $user_id
 * @property int $finance_project_id
 * @property string $title
 * @property string|null $description
 * @property string $status open|in_progress|done
 * @property Carbon|null $starts_on
 * @property Carbon|null $due_on
 * @property string|null $estimate_hours
 * @property bool $is_milestone
 * @property int $sort
 * @property int|null $finance_product_id
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinanceProjectTask extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** open → in_progress → done. Nothing else is a state of work. */
    public const STATUSES = ['open', 'in_progress', 'done'];

    protected $fillable = [
        'finance_project_id', 'title', 'description', 'status',
        'starts_on', 'due_on', 'estimate_hours', 'is_milestone', 'sort', 'finance_product_id',
    ];

    protected $casts = [
        'finance_project_id' => 'integer',
        'finance_product_id' => 'integer',
        'starts_on' => 'date',
        'due_on' => 'date',
        'estimate_hours' => 'decimal:2',
        'is_milestone' => 'boolean',
        'sort' => 'integer',
        'version' => 'integer',
    ];

    /** @return BelongsTo<FinanceProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(FinanceProject::class, 'finance_project_id');
    }

    /** @return HasMany<FinanceTimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(FinanceTimeEntry::class, 'finance_project_task_id');
    }

    /**
     * Whether the date has passed on work that is not finished.
     *
     * A done task is never overdue, however old its date: red on work already
     * delivered is noise, and noise is what makes a warning ignorable.
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'done' || $this->due_on === null) {
            return false;
        }

        return $this->due_on->endOfDay()->isPast();
    }
}
