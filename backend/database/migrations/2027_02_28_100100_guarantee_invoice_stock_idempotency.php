<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertStockTablesExist();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_qty TYPE NUMERIC(16, 4)');
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_min TYPE NUMERIC(16, 4)');
            DB::statement('ALTER TABLE finance_stock_movements ALTER COLUMN qty TYPE NUMERIC(16, 4)');
        } elseif (DB::getDriverName() === 'sqlite') {
            $this->dropSqliteProductSkuIndex();
            Schema::table('finance_products', static function (Blueprint $table): void {
                $table->text('stock_qty')->default('0.0000')->change();
                $table->text('stock_min')->nullable()->change();
            });
            $this->createSqliteProductSkuIndex();
            DB::statement('DROP INDEX IF EXISTS finance_stock_movements_invoice_sale_unique');
            Schema::table('finance_stock_movements', static function (Blueprint $table): void {
                $table->text('qty')->change();
            });
        } else {
            throw new LogicException('Invoice stock hardening supports only SQLite and PostgreSQL.');
        }
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS finance_stock_movements_invoice_sale_unique
            ON finance_stock_movements (user_id, finance_product_id, ref_type, ref_id)
            WHERE reason = 'sale' AND ref_type = 'finance_invoice' AND ref_id IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('finance_products') || ! Schema::hasTable('finance_stock_movements')) {
            return;
        }

        $this->assertCanNarrow();
        DB::statement('DROP INDEX IF EXISTS finance_stock_movements_invoice_sale_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_qty TYPE NUMERIC(12, 3)');
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_min TYPE NUMERIC(12, 3)');
            DB::statement('ALTER TABLE finance_stock_movements ALTER COLUMN qty TYPE NUMERIC(12, 3)');

            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            $this->dropSqliteProductSkuIndex();
            Schema::table('finance_products', static function (Blueprint $table): void {
                $table->decimal('stock_qty', 12, 3)->default(0)->change();
                $table->decimal('stock_min', 12, 3)->nullable()->change();
            });
            $this->createSqliteProductSkuIndex();
            Schema::table('finance_stock_movements', static function (Blueprint $table): void {
                $table->decimal('qty', 12, 3)->change();
            });

            return;
        }

        throw new LogicException('Invoice stock hardening supports only SQLite and PostgreSQL.');
    }

    private function assertCanNarrow(): void
    {
        $invalidProduct = DB::table('finance_products')
            ->selectRaw('CAST(stock_qty AS TEXT) AS stock_qty_exact')
            ->selectRaw('CAST(stock_min AS TEXT) AS stock_min_exact')
            ->cursor()
            ->contains(fn (object $row): bool => ! $this->isScaleThree($row->stock_qty_exact ?? null)
                || (($row->stock_min_exact ?? null) !== null && ! $this->isScaleThree($row->stock_min_exact)));
        $invalidMovement = DB::table('finance_stock_movements')
            ->selectRaw('CAST(qty AS TEXT) AS qty_exact')
            ->cursor()
            ->contains(fn (object $row): bool => ! $this->isScaleThree($row->qty_exact ?? null));
        if ($invalidProduct || $invalidMovement) {
            throw new LogicException('Invoice stock quantities cannot be safely narrowed to scale 3.');
        }
    }

    private function isScaleThree(mixed $value): bool
    {
        if (! is_string($value)
            || preg_match('/\A-?\d+(?:\.(\d{1,4}))?\z/D', $value, $matches) !== 1) {
            return false;
        }

        return str_pad($matches[1] ?? '', 4, '0')[3] === '0';
    }

    private function dropSqliteProductSkuIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS finance_products_sku_unique');
    }

    private function createSqliteProductSkuIndex(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX finance_products_sku_unique
            ON finance_products (user_id, sku)
            WHERE sku IS NOT NULL AND deleted_at IS NULL
            SQL);
    }

    private function assertStockTablesExist(): void
    {
        if (! Schema::hasTable('finance_products') || ! Schema::hasTable('finance_stock_movements')) {
            throw new LogicException('Invoice stock hardening must run after the product ledger migration.');
        }
    }
};
