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
 * A finance project (plaintext-relational pivot). Rows are private per user via
 * OwnsUserData; nested through a self-referential parent_id. `expenses` is a
 * plaintext JSON array of manual hand-entered spend rows.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $kind
 * @property string|null $note
 * @property array<int, array<string, mixed>>|null $expenses
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinanceProject extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    /** planned → active → done; on_hold and cancelled are neither. */
    public const STATUSES = ['planned', 'active', 'on_hold', 'done', 'cancelled'];

    protected $fillable = [
        'parent_id', 'name', 'kind', 'note', 'expenses',
        'status', 'starts_on', 'due_on', 'budget_net', 'partner_id',
    ];

    protected $casts = [
        'expenses' => 'array',
        'starts_on' => 'date',
        'due_on' => 'date',
        'budget_net' => 'decimal:2',
        'partner_id' => 'integer',
        'quote_id' => 'integer',
        'version' => 'integer',
    ];

    /** @return BelongsTo<FinancePartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancePartner::class, 'partner_id');
    }

    /** @return HasMany<FinanceProjectTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(FinanceProjectTask::class, 'finance_project_id');
    }

    /** @return HasMany<FinanceTimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(FinanceTimeEntry::class, 'finance_project_id');
    }
}
