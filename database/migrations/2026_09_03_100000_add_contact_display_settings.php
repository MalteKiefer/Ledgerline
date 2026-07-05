<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user contacts preferences: which name the list is sorted by (first or last
 * name) and how a contact's display name is formatted (First Last / Last, First).
 * Plus a small table remembering duplicate groups the user has dismissed so they
 * do not reappear on every visit to the duplicate review page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('contact_sort', 16)->default('first_name');
            $table->string('contact_display_format', 16)->default('first_last');
        });

        Schema::create('contact_duplicate_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // sha1 of the group's sorted contact ids — stable identity for a set.
            $table->string('signature', 40);
            $table->timestamps();
            $table->unique(['user_id', 'signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_duplicate_dismissals');
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['contact_sort', 'contact_display_format']);
        });
    }
};
