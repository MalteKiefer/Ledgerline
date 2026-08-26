<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The article catalogue and its stock ledger.
 *
 * Two kinds live in one table because a quote line does not care: an hour of
 * work and a switch are both something you sell at a price, and splitting them
 * would mean two of every list, filter and picker for one differing field.
 * Only hardware carries stock, and only when asked to (`track_stock`).
 *
 * Stock is kept twice on purpose. `finance_stock_movements` is the truth — an
 * append-only ledger, because "how did it get to seven" is the question stock
 * actually raises, and an editable number cannot answer it. `stock_qty` on the
 * article is the fast read for lists, written in the SAME transaction as the
 * movement so the two cannot drift.
 *
 * Additive: no existing finance table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // service | hardware. Drives whether stock and a purchase price are
            // meaningful, nothing else.
            $table->string('kind', 16)->default('service');
            $table->string('sku', 64)->nullable();
            $table->string('name', 300);
            $table->text('description')->nullable();
            // Hour, piece, day, flat rate — free text, because every trade names
            // its units differently and a fixed list would be wrong somewhere.
            $table->string('unit', 32)->nullable();

            $table->decimal('price_net', 12, 2)->default(0);
            // What it cost us. Optional, and only used to show a margin — never
            // printed on a document that leaves the house.
            $table->decimal('purchase_price', 12, 2)->nullable();
            // null = fall back to the company default rate, so changing that
            // default does not require touching every article.
            $table->decimal('vat_rate', 5, 2)->nullable();

            $table->foreignId('supplier_id')->nullable()
                ->constrained('finance_partners')->nullOnDelete();
            $table->string('category', 160)->nullable();

            $table->boolean('active')->default(true);
            $table->boolean('track_stock')->default(false);
            // Fractional on purpose: 2.5 hours, 0.5 metres of cable.
            $table->decimal('stock_qty', 12, 3)->default(0);
            // Reorder level. null = never warn.
            $table->decimal('stock_min', 12, 3)->nullable();

            $table->text('note')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'kind']);
            $table->index(['user_id', 'name']);
        });

        // An article number must be unique among the live articles of one owner,
        // but a deleted one must not block re-using its number, and most
        // articles have no number at all. Postgres and SQLite both support a
        // partial index; MySQL does not, and this app runs on the first two.
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX finance_products_sku_unique ON finance_products (user_id, sku) '
                .'WHERE sku IS NOT NULL AND deleted_at IS NULL'
            );
        }

        Schema::create('finance_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_product_id')->constrained('finance_products')->cascadeOnDelete();

            // Signed: goods in are positive, goods out negative. A correction is
            // a new movement, never an edit of an old one — that is what makes
            // the ledger answer "how did it get to seven".
            $table->decimal('qty', 12, 3);
            $table->string('reason', 24)->default('correction');

            // What caused it, when something did: an invoice, a quote, or a hand
            // entry. Kept as a loose reference rather than a foreign key so a
            // deleted document cannot erase the fact that goods moved.
            $table->string('ref_type', 24)->nullable();
            $table->string('ref_id', 64)->nullable();

            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            // Append-only: no updated_at, no version, no soft delete.
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'finance_product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_stock_movements');
        Schema::dropIfExists('finance_products');
    }
};
