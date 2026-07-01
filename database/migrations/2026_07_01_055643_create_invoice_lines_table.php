<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the invoice_lines table.
 *
 * Unit prices are net (cents). line_net_cents and line_tax_cents are the
 * computed per-line totals. A line may optionally reference the time entry or
 * expense it was pulled from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit')->nullable();
            $table->bigInteger('unit_price_cents')->default(0);
            $table->unsignedSmallInteger('tax_rate')->default(0);
            $table->bigInteger('line_net_cents')->default(0);
            $table->bigInteger('line_tax_cents')->default(0);
            $table->nullableMorphs('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
