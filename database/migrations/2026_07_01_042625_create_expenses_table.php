<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the expenses table (money out).
 *
 * Expenses are global (company-wide, not team-scoped). Amounts are stored as
 * integer minor units (cents). amount_cents is the gross total paid; tax_cents
 * is the VAT portion derived from the tax rate. An expense may optionally be
 * linked to a customer and/or project, or neither (general company expense).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->string('vendor')->nullable();
            $table->string('category');
            $table->string('category_custom')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->unsignedSmallInteger('tax_rate')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->string('payment_status')->default('OPEN');
            $table->date('paid_on')->nullable();
            $table->boolean('billable')->default(false);
            $table->boolean('billed')->default(false);
            $table->json('labels')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index('category');
            $table->index('customer_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
