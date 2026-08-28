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
 * A sellable article: an hour of work or a piece of hardware.
 *
 * `stock_qty` is a denormalised read of {@see FinanceStockMovement}; it is only
 * ever written in the same transaction as the movement that changed it, so the
 * ledger stays the answer to "why". `stock_qty` and `stock_min` are NOT
 * fillable: stock moves through a movement, never through a form field, or the
 * ledger would stop explaining the number.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind service|hardware
 * @property string|null $sku
 * @property string $name
 * @property string|null $description
 * @property string|null $unit
 * @property string $price_net
 * @property string|null $purchase_price
 * @property string|null $vat_rate
 * @property int|null $supplier_id
 * @property string|null $category
 * @property bool $active
 * @property bool $track_stock
 * @property string $stock_qty
 * @property string|null $stock_min
 * @property string|null $note
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FinanceProduct extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $fillable = [
        'kind', 'sku', 'name', 'description', 'unit',
        'price_net', 'purchase_price', 'vat_rate',
        'supplier_id', 'category', 'active', 'track_stock', 'note',
    ];

    protected $casts = [
        'price_net' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'stock_qty' => 'decimal:4',
        'stock_min' => 'decimal:4',
        'active' => 'boolean',
        'track_stock' => 'boolean',
        'supplier_id' => 'integer',
        'version' => 'integer',
    ];

    /** @return BelongsTo<FinancePartner, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FinancePartner::class, 'supplier_id');
    }

    /** @return HasMany<FinanceStockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(FinanceStockMovement::class, 'finance_product_id');
    }

    /**
     * Whether this article is at or below its reorder level.
     *
     * Only meaningful for something we actually count: an article that does not
     * track stock is never "low", it is simply not stocked.
     */
    public function isLowOnStock(): bool
    {
        if (! $this->track_stock || $this->stock_min === null) {
            return false;
        }

        return (float) $this->stock_qty <= (float) $this->stock_min;
    }
}
