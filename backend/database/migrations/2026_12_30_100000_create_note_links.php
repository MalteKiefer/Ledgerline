<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wikilink edges between notes. `[[Title]]` in a note's body creates one row per
 * distinct target title. target_note_id is the resolved note (nullable = the
 * title has no matching note yet); target_title keeps the raw link so it can
 * resolve later when a note with that title is created. Feeds the backlink panel
 * (and a later graph view).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('target_note_id')->nullable()->constrained('notes')->cascadeOnDelete();
            $table->string('target_title', 500);
            $table->timestamps();

            $table->unique(['source_note_id', 'target_title']);
            $table->index(['user_id', 'target_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};
