<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the projects table.
 *
 * Each project belongs to exactly one customer and has a lifecycle status
 * stored as the ProjectStatus enum's backing string. Deleting a customer
 * cascades to its projects. The optional reference is a human-facing project
 * number and is unique when present.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('reference')->nullable()->unique();
            // Stores the ProjectStatus enum value (e.g. "ACTIVE").
            $table->string('status');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->timestamps();

            // Speeds up listing a customer's projects and filtering by status.
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
