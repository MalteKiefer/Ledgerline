<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the branches table (customer locations / Niederlassungen).
 *
 * A customer can have any number of branch offices, each with its own address.
 * The country is stored as an ISO 3166-1 alpha-2 code. Branches cascade-delete
 * with their customer.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            // Optional branch manager (Niederlassungsleiter): one of the
            // customer's contacts. Set null if that contact is removed.
            $table->foreignId('manager_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
