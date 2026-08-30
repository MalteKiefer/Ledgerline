<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumable per-owner migration checkpoints for the legacy-to-finance-v2
 * invoice/payment slice (Task 16). This table is a performance/observability
 * aid, not a correctness dependency: every write it gates
 * (ImportLegacyInvoice, RecordPayment, AllocatePayment) is already
 * idempotent via `source_type`/`source_key`, so re-running the migration
 * command from scratch for an owner is always safe — this table just lets a
 * later run skip legacy rows it has already processed instead of
 * re-scanning them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_invoice_migration_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 32)->default('invoices');
            $table->unsignedBigInteger('last_invoice_id')->nullable();
            $table->unsignedBigInteger('last_bank_transaction_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('error_code', 191)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->unique(['user_id'], 'finance_invoice_migration_checkpoints_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoice_migration_checkpoints');
    }
};
