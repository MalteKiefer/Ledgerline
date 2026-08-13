<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the contacts table.
 *
 * Each contact person belongs to exactly one customer and carries a fixed
 * function/role stored as the ContactFunction enum's backing string. Deleting
 * a customer cascades to its contacts.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Stores the ContactFunction enum value (e.g. "TECHNICAL_CONTACT").
            $table->string('function');
            $table->timestamps();

            // Speeds up listing a customer's contacts and filtering by function.
            $table->index(['customer_id', 'function']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
