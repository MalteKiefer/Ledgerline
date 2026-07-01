<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the invoices table.
 *
 * Invoices are global (not team-scoped). Amounts are integer cents. The number
 * is assigned on finalisation (gapless per prefix/year); drafts have none.
 * Finalised invoices are immutable except for status and payment fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('INVOICE');
            $table->string('status')->default('DRAFT');
            $table->string('number')->nullable()->unique();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('language', 5)->default('de');
            $table->string('currency', 3)->default('EUR');
            $table->string('tax_mode')->default('STANDARD');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->text('intro_text')->nullable();
            $table->text('closing_text')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->bigInteger('net_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('gross_cents')->default(0);
            $table->bigInteger('paid_cents')->default(0);
            $table->date('paid_on')->nullable();
            $table->foreignId('parent_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
