<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices graduate to a sharded sealed store (merge-safety spec §3b), like
 * files/gallery/notes/passwords. Additive: the old single-blob module_stores invoices
 * row stays readable until the client re-shards on first save (dual-read migration).
 * Invoice numbering safety is unaffected — it is derived client-side from the actual
 * invoices (each stores its seq) plus the company floor, not the store scalar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices_blobs');
        Schema::dropIfExists('invoices_store');
    }
};
