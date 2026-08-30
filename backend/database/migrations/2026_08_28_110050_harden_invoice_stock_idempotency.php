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

        $this->assertCanNarrow();
        DB::statement('DROP INDEX IF EXISTS finance_stock_movements_invoice_sale_unique');
        $this->narrowQuantities();
    }

    private function widenQuantities(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_qty TYPE NUMERIC(16, 4)');
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_min TYPE NUMERIC(16, 4)');
            DB::statement('ALTER TABLE finance_stock_movements ALTER COLUMN qty TYPE NUMERIC(16, 4)');

            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            $this->changeSqliteQuantityDeclarations('numeric', 'text');
            DB::statement('DROP INDEX IF EXISTS finance_stock_movements_invoice_sale_unique');

            return;
        }

        throw new LogicException('Invoice stock hardening supports only SQLite and PostgreSQL.');
    }

    private function narrowQuantities(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_qty TYPE NUMERIC(12, 3)');
            DB::statement('ALTER TABLE finance_products ALTER COLUMN stock_min TYPE NUMERIC(12, 3)');
            DB::statement('ALTER TABLE finance_stock_movements ALTER COLUMN qty TYPE NUMERIC(12, 3)');

            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            $this->changeSqliteQuantityDeclarations('text', 'numeric');

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
            || preg_match('/\A-?(\d+)(?:\.(\d{1,4}))?\z/D', $value, $matches) !== 1) {
            return false;
        }

        $fraction = str_pad($matches[2] ?? '', 4, '0');
        $integer = ltrim($matches[1], '0') ?: '0';

        return $fraction[3] === '0'
            && (strlen($integer) < 9 || (strlen($integer) === 9 && strcmp($integer, '999999999') <= 0));
    }

    private function changeSqliteQuantityDeclarations(string $from, string $to): void
    {
        // A Laravel SQLite table rebuild drops the parent table and fires its
        // cascades. Only the affinity declarations change here, so update the
        // validated schema text in place and leave ledger rows/FKs untouched.
        $updates = [];
        foreach ([
            'finance_products' => ['stock_qty', 'stock_min'],
            'finance_stock_movements' => ['qty'],
        ] as $table => $columns) {
            $sql = DB::table('sqlite_master')
                ->where('type', 'table')
                ->where('name', $table)
                ->value('sql');
            if (! is_string($sql)) {
                throw new LogicException("SQLite schema for {$table} is unavailable.");
            }

            foreach ($columns as $column) {
                $prefix = '/("'.preg_quote($column, '/').'"\s+)';
                $fromPattern = $prefix.preg_quote($from, '/').'(?=\s|,|\))/i';
                $sql = preg_replace($fromPattern, '$1'.$to, $sql, 1, $count);
                if ($sql === null) {
                    throw new LogicException("SQLite {$table}.{$column} declaration is invalid.");
                }
                if ($count === 0
                    && preg_match($prefix.preg_quote($to, '/').'(?=\s|,|\))/i', $sql) !== 1) {
                    throw new LogicException("SQLite {$table}.{$column} is neither {$from} nor {$to}.");
                }
            }

            $updates[$table] = $sql;
        }

        DB::statement('PRAGMA writable_schema = ON');

        try {
            foreach ($updates as $table => $sql) {
                DB::table('sqlite_master')
                    ->where('type', 'table')
                    ->where('name', $table)
                    ->update(['sql' => $sql]);
            }

            $versionRow = DB::selectOne('PRAGMA schema_version');
            if (! is_object($versionRow) || ! property_exists($versionRow, 'schema_version')) {
                throw new LogicException('SQLite schema version is unavailable.');
            }
            $versionValue = $versionRow->schema_version;
            if (! is_int($versionValue) && (! is_string($versionValue) || ! ctype_digit($versionValue))) {
                throw new LogicException('SQLite schema version is invalid.');
            }
            $version = (int) $versionValue;
            DB::statement('PRAGMA schema_version = '.($version + 1));
        } finally {
            DB::statement('PRAGMA writable_schema = OFF');
        }
    }

    private function stockTablesExist(): bool
    {
        return Schema::hasTable('finance_products') && Schema::hasTable('finance_stock_movements');
    }
};
