<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->stockTablesExist()) {
            return;
        }

        $this->widenQuantities();
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS finance_stock_movements_invoice_sale_unique
            ON finance_stock_movements (user_id, finance_product_id, ref_type, ref_id)
            WHERE reason = 'sale' AND ref_type = 'finance_invoice' AND ref_id IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        if (! $this->stockTablesExist()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS finance_stock_movements_invoice_sale_unique');
        $this->narrowQuantities();
    }

    private function widenQuantities(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_qty TYPE NUMERIC(16, 4)');
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_min TYPE NUMERIC(16, 4)');
            DB::statement('ALTER TABLE finance_stock_movements ALTER COLUMN qty TYPE NUMERIC(16, 4)');
        }
    }

    private function narrowQuantities(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        if (DB::table('finance_products')
            ->whereRaw('stock_qty <> round(stock_qty, 3) OR (stock_min IS NOT NULL AND stock_min <> round(stock_min, 3))')
            ->exists()
            || DB::table('finance_stock_movements')->whereRaw('qty <> round(qty, 3)')->exists()) {
            throw new LogicException('Invoice stock quantities cannot be safely narrowed to scale 3.');
        }

        DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_qty TYPE NUMERIC(12, 3)');
        DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_min TYPE NUMERIC(12, 3)');
        DB::statement('ALTER TABLE finance_stock_movements ALTER COLUMN qty TYPE NUMERIC(12, 3)');
    }

    private function stockTablesExist(): bool
    {
        return Schema::hasTable('finance_products') && Schema::hasTable('finance_stock_movements');
    }
};
