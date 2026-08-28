<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Inventory;

use App\Models\FinanceProduct;
use App\Modules\Finance\Application\Ports\InventoryMovementPort;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class LegacyStockLedgerAdapter implements InventoryMovementPort
{
    public function recordInvoiceSale(
        int $ownerId,
        string $invoiceUuid,
        array $quantityScaledByProduct,
        DateTimeImmutable $occurredAt,
    ): void {
        if ($ownerId < 1 || preg_match('/\A[0-9a-f-]{36}\z/D', $invoiceUuid) !== 1) {
            throw new LogicException('Invoice inventory reference is invalid.');
        }

        ksort($quantityScaledByProduct, SORT_NUMERIC);
        foreach ($quantityScaledByProduct as $productId => $quantityScaled) {
            if (! is_int($productId) || $productId < 1 || ! is_int($quantityScaled) || $quantityScaled === 0) {
                throw new LogicException('Invoice inventory quantities must be non-zero exact scale-4 values.');
            }

            $product = DB::table('finance_products')
                ->select(['id', 'user_id', 'kind', 'track_stock'])
                ->selectRaw('CAST(stock_qty AS TEXT) AS stock_qty_exact')
                ->where('id', $productId)
                ->where('user_id', $ownerId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            if ($product === null || ! is_string($product->kind) || $product->kind !== 'hardware') {
                throw (new ModelNotFoundException)->setModel(FinanceProduct::class, [$productId]);
            }

            $movementScaled = -$quantityScaled;
            $quantity = $this->formatScaled($movementScaled);
            $existing = DB::table('finance_stock_movements')
                ->selectRaw('CAST(qty AS TEXT) AS qty_exact')
                ->where('user_id', $ownerId)
                ->where('finance_product_id', $productId)
                ->where('reason', 'sale')
                ->where('ref_type', 'finance_invoice')
                ->where('ref_id', $invoiceUuid)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if (! is_string($existing->qty_exact)
                    || $this->parseScaled($existing->qty_exact) !== $movementScaled) {
                    throw new DomainException('inventory_reference_conflict');
                }

                continue;
            }

            DB::table('finance_stock_movements')->insert([
                'user_id' => $ownerId,
                'finance_product_id' => $productId,
                'qty' => $quantity,
                'reason' => 'sale',
                'ref_type' => 'finance_invoice',
                'ref_id' => $invoiceUuid,
                'note' => null,
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);

            $tracksStock = $product->track_stock === true
                || $product->track_stock === 1
                || $product->track_stock === '1';
            if ($tracksStock) {
                if (! is_string($product->stock_qty_exact)) {
                    throw new DomainException('inventory_quantity_invalid');
                }
                $stockScaled = $this->parseScaled($product->stock_qty_exact);
                $nextScaled = $stockScaled + $movementScaled;
                if (($movementScaled > 0 && $nextScaled < $stockScaled)
                    || ($movementScaled < 0 && $nextScaled > $stockScaled)) {
                    throw new DomainException('inventory_quantity_overflow');
                }
                DB::table('finance_products')
                    ->where('id', $productId)
                    ->where('user_id', $ownerId)
                    ->update([
                        'stock_qty' => $this->formatScaled($nextScaled),
                        'updated_at' => $occurredAt,
                    ]);
            }
        }
    }

    private function parseScaled(string $quantity): int
    {
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,4}))?\z/D', $quantity, $parts) !== 1) {
            throw new DomainException('inventory_quantity_invalid');
        }
        $digits = ltrim($parts[2].str_pad($parts[3] ?? '', 4, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        if (strlen($digits) > strlen((string) PHP_INT_MAX)) {
            throw new DomainException('inventory_quantity_overflow');
        }
        $scaled = (int) $digits;

        return $parts[1] === '-' ? -$scaled : $scaled;
    }

    private function formatScaled(int $scaled): string
    {
        $negative = $scaled < 0;
        $digits = ltrim((string) $scaled, '-');
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);
        $quantity = substr($digits, 0, -4).'.'.substr($digits, -4);

        return $negative ? '-'.$quantity : $quantity;
    }
}
