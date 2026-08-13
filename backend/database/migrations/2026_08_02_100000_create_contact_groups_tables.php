<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact groups (own table, mirrored to each card's CATEGORIES for DAV
 * interop). Groups are user-level so they can span address books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('contact_group', function (Blueprint $table): void {
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('contact_groups')->cascadeOnDelete();
            $table->primary(['contact_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_group');
        Schema::dropIfExists('contact_groups');
    }
};
