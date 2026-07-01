<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the income_entries table (manual, non-time income).
 *
 * Income entries are global (not team-scoped). Amounts are integer cents. An
 * entry may link to a customer and/or project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->boolean('billed')->default(false);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index('customer_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_entries');
    }
};
