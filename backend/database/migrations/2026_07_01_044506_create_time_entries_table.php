<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the time_entries table (billable hours).
 *
 * Time entries are global (not team-scoped). Duration is stored in minutes and
 * the resolved hourly rate in cents; the billable amount is derived. An entry
 * may link to a customer and/or project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->unsignedInteger('minutes');
            $table->unsignedBigInteger('rate_cents')->default(0);
            $table->string('currency', 3);
            $table->boolean('billable')->default(true);
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
        Schema::dropIfExists('time_entries');
    }
};
