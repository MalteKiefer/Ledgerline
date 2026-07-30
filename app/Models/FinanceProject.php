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

    protected $fillable = ['parent_id', 'name', 'kind', 'note', 'expenses'];

    protected $casts = [
        'expenses' => 'array',
        'version' => 'integer',
    ];

    /**
     * @return BelongsTo<FinanceProject, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FinanceProject::class, 'parent_id');
    }

    /**
     * @return HasMany<FinanceProject, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(FinanceProject::class, 'parent_id');
    }

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
