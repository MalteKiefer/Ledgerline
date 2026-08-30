<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\FinanceProduct;
use App\Models\FinanceStockMovement;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

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
    private const int MAX_SCALED = 9_999_999_999_999_999;

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
        int|float|string $qty,
        string $reason,
        ?string $refType = null,
        ?string $refId = null,
        ?string $note = null,
        ?Carbon $at = null,
    ): FinanceStockMovement {
        $quantityScaled = self::parseQuantity($qty);
        if ($quantityScaled === 0) {
            throw new DomainException('stock_quantity_zero');
        }

        return DB::transaction(function () use ($product, $quantityScaled, $reason, $refType, $refId, $note, $at): FinanceStockMovement {
            // Lock the article, not the ledger: two movements on the same
            // article must serialise, two on different articles need not.
            $fresh = FinanceProduct::query()->lockForUpdate()->find($product->getKey());
            if (! $fresh instanceof FinanceProduct) {
                // The article went away between the caller reading it and us
                // locking it. Recording a movement against nothing would leave
                // an orphan that no figure explains.
                throw new \RuntimeException('The article no longer exists.');
            }

            $nextStock = null;
            if ($fresh->track_stock) {
                $nextStock = self::checkedAdd(
                    self::parseQuantity((string) $fresh->stock_qty),
                    $quantityScaled,
                );
            }

            $movement = new FinanceStockMovement;
            $movement->forceFill([
                'user_id' => $fresh->user_id,
                'finance_product_id' => $fresh->getKey(),
                'qty' => self::formatQuantity($quantityScaled),
                'reason' => in_array($reason, FinanceStockMovement::REASONS, true) ? $reason : 'correction',
                'ref_type' => $refType,
                'ref_id' => $refId,
                'note' => $note,
                'occurred_at' => $at ?? Carbon::now(),
            ])->save();

            if ($nextStock !== null) {
                $fresh->forceFill(['stock_qty' => self::formatQuantity($nextStock)])->save();
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
    public static function recompute(FinanceProduct $product): string
    {
        return DB::transaction(function () use ($product): string {
            $fresh = FinanceProduct::query()->lockForUpdate()->find($product->getKey());
            if (! $fresh instanceof FinanceProduct) {
                return '0.0000';
            }

            $sum = 0;
            $movements = DB::table('finance_stock_movements')
                ->where('finance_product_id', $fresh->getKey())
                ->orderBy('id')
                ->selectRaw('CAST(qty AS TEXT) AS qty_exact')
                ->cursor();
            foreach ($movements as $movement) {
                if (! is_string($movement->qty_exact ?? null)) {
                    throw new DomainException('stock_quantity_invalid');
                }
                $sum = self::checkedAdd($sum, self::parseQuantity($movement->qty_exact));
            }

            $quantity = self::formatQuantity($sum);
            $fresh->forceFill(['stock_qty' => $quantity])->save();

            return $quantity;
        });
    }

    private static function parseQuantity(int|float|string $quantity): int
    {
        if (is_float($quantity)) {
            if (! is_finite($quantity)) {
                throw new DomainException('stock_quantity_invalid');
            }
            try {
                $encoded = json_encode($quantity, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new DomainException('stock_quantity_invalid', previous: $exception);
            }
            if (! is_string($encoded)) {
                throw new DomainException('stock_quantity_invalid');
            }
            $quantity = $encoded;
        }

        $scaled = DecimalQuantity::fromString(is_int($quantity) ? (string) $quantity : trim($quantity))->scaled();
        if ($scaled > self::MAX_SCALED || $scaled < -self::MAX_SCALED) {
            throw new DomainException('stock_quantity_overflow');
        }

        return $scaled;
    }

    private static function checkedAdd(int $current, int $change): int
    {
        if (($change > 0 && $current > self::MAX_SCALED - $change)
            || ($change < 0 && $current < -self::MAX_SCALED - $change)) {
            throw new DomainException('stock_quantity_overflow');
        }

        return $current + $change;
    }

    private static function formatQuantity(int $scaled): string
    {
        $negative = $scaled < 0;
        $digits = str_pad(ltrim((string) $scaled, '-'), 5, '0', STR_PAD_LEFT);
        $quantity = substr($digits, 0, -4).'.'.substr($digits, -4);

        return $negative ? '-'.$quantity : $quantity;
    }
}
