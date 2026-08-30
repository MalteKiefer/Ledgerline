<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnsUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One movement of goods: signed quantity, why, and what caused it.
 *
 * Append-only. There is no update path and no soft delete, because a stock
 * figure is only trustworthy if the steps that produced it cannot be rewritten;
 * a mistake is corrected by booking the opposite movement, which leaves both
 * the error and the correction visible.
 *
 * `ref_type`/`ref_id` are a loose reference rather than a foreign key so that
 * deleting a document cannot erase the fact that goods left the shelf.
 *
 * @property int $id
 * @property int $user_id
 * @property int $finance_product_id
 * @property string $qty signed: in positive, out negative
 * @property string $reason purchase|sale|correction|return|initial
 * @property string|null $ref_type invoice|quote|manual
 * @property string|null $ref_id
 * @property string|null $note
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
class FinanceStockMovement extends Model
{
    use OwnsUserData;

    /** Append-only: rows are written once, so there is nothing to update. */
    public const UPDATED_AT = null;

    /** The reasons a movement can have. A free-text reason would not group. */
    public const REASONS = ['purchase', 'sale', 'correction', 'return', 'initial'];

    protected $fillable = [
        'finance_product_id', 'qty', 'reason', 'ref_type', 'ref_id', 'note', 'occurred_at',
    ];

    protected $casts = [
        'finance_product_id' => 'integer',
        'qty' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<FinanceProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(FinanceProduct::class, 'finance_product_id');
    }
}
