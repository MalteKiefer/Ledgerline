<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the contact_phones table.
 *
 * A contact person can have any number of phone numbers, each with a free
 * label (suggested values are offered in the UI but custom labels are allowed).
 * Rows cascade-delete with their contact.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('phone');
            $table->timestamps();

            $table->index('contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_phones');
    }
};
