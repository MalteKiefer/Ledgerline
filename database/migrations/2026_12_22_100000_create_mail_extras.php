<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive — Phase 6 (extras): user-metadata labels, ingest rules and saved
 * searches. Threading uses the mail_messages.thread_id column + index already
 * shipped in Phase 1 (computed at ingest, no schema change here). Labels are
 * mutable user metadata on the otherwise-immutable archive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 16)->default('#6750a4');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('mail_label_message', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_label_id')->constrained('mail_labels')->cascadeOnDelete();
            $table->uuid('mail_message_id');
            $table->foreign('mail_message_id')->references('id')->on('mail_messages')->cascadeOnDelete();
            $table->unique(['mail_label_id', 'mail_message_id']);
            $table->index('mail_message_id');
        });

        Schema::create('mail_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->boolean('enabled')->default(true);
            $table->integer('priority')->default(0);
            $table->json('match_json');    // {from?,to?,subject?,folder?,has_attachment?}
            $table->json('action_json');   // {add_label?:id, mark_read?:bool, trash?:bool, skip?:bool}
            $table->timestamps();
            $table->index(['user_id', 'enabled', 'priority']);
        });

        Schema::create('mail_saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->json('filters_json'); // {q?,account_id?,folder?,seen?,spam?,label?,from?,to?}
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_saved_searches');
        Schema::dropIfExists('mail_rules');
        Schema::dropIfExists('mail_label_message');
        Schema::dropIfExists('mail_labels');
    }
};
