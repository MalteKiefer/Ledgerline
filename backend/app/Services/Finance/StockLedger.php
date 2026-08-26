<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\FinanceProduct;
use App\Models\FinanceStockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one place stock changes.
 *
 * Every path that moves goods — a hand correction, a delivery, an invoice going
 * out — goes through here, so the ledger and the denormalised figure on the
 * article can never disagree: they are written in the same transaction, with the
 * article locked while it happens.
 *
 * Nothing here decides *whether* goods should move. That belongs to the caller;
 * this only records that they did.
 */
class StockLedger
{
    /**
     * Record a movement and carry the article's figure with it.
     *
     * Positive quantity is goods in, negative is goods out. An article that does
     * not track stock still gets its movement recorded — switching tracking on
     * later then has a history instead of starting from nothing — but its figure
     * is left alone, because an untracked article has no meaningful count.
     */
    public static function move(
        FinanceProduct $product,
        float $qty,
        string $reason,
        ?string $refType = null,
        ?string $refId = null,
        ?string $note = null,
        ?Carbon $at = null,
    ): FinanceStockMovement {
        return DB::transaction(function () use ($product, $qty, $reason, $refType, $refId, $note, $at): FinanceStockMovement {
            // Lock the article, not the ledger: two movements on the same
            // article must serialise, two on different articles need not.
            $fresh = FinanceProduct::query()->lockForUpdate()->find($product->getKey());
            if (! $fresh instanceof FinanceProduct) {
                // The article went away between the caller reading it and us
                // locking it. Recording a movement against nothing would leave
                // an orphan that no figure explains.
                throw new \RuntimeException('The article no longer exists.');
            }

            $movement = new FinanceStockMovement;
            $movement->forceFill([
                'user_id' => $fresh->user_id,
                'finance_product_id' => $fresh->getKey(),
                'qty' => $qty,
                'reason' => in_array($reason, FinanceStockMovement::REASONS, true) ? $reason : 'correction',
                'ref_type' => $refType,
                'ref_id' => $refId,
                'note' => $note,
                'occurred_at' => $at ?? Carbon::now(),
            ])->save();

            if ($fresh->track_stock) {
                $fresh->forceFill(['stock_qty' => (float) $fresh->stock_qty + $qty])->save();
            }

            return $movement;
        });
    }

    /**
     * Rebuild an article's figure from its movements.
     *
     * The repair path: if the denormalised number ever disagrees with the
     * ledger, the ledger wins, because it is the part that cannot be edited.
     * Returns the figure it wrote.
     */
    public static function recompute(FinanceProduct $product): float
    {
        return (float) DB::transaction(function () use ($product): float {
            $fresh = FinanceProduct::query()->lockForUpdate()->find($product->getKey());
            if (! $fresh instanceof FinanceProduct) {
                return 0.0;
            }

            $sum = (float) FinanceStockMovement::query()
                ->where('finance_product_id', $fresh->getKey())
                ->sum('qty');

            $fresh->forceFill(['stock_qty' => $sum])->save();

            return $sum;
        });
    }
}
