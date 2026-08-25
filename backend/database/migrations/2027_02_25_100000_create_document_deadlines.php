<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deadlines found in documents: contract ends, notice periods, warranties,
 * expiry dates.
 *
 * Four tables already hold extracted text (files.search_text,
 * gallery_photos.ocr_text, mail_messages.search_text, finance_receipts.ocr) and
 * that text has only ever been searched, never read. The dates in it are the
 * kind where forgetting costs real money.
 *
 * A row here is a FINDING, not a fact: `confirmed_at` is null until a human
 * agrees. A pattern matcher will misread something eventually, and a wrong
 * deadline asserted as true is worse than none — so an unconfirmed finding is
 * shown as a suggestion and never fires a reminder on its own.
 *
 * Polymorphic on purpose (source_type + source_id, no FK): the four sources live
 * in four tables with nothing in common, and a fifth is plausible. Deletion is
 * handled by the owning module, and an orphaned finding is harmless — it is
 * derived data that can always be found again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_deadlines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 24);   // file | photo | mail | receipt
            $table->unsignedBigInteger('source_id');
            $table->date('due_on');
            $table->string('kind', 32);          // contract_end | notice | warranty | expiry | other
            // The sentence it was read from: without it nobody can judge whether
            // the finding is right, and judging is the whole point.
            $table->string('evidence', 500)->nullable();
            $table->string('label', 300)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'due_on']);
            // One finding per date per document: re-scanning a document must
            // update what is there instead of piling up copies of it.
            $table->unique(['user_id', 'source_type', 'source_id', 'due_on'], 'document_deadlines_unique_finding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_deadlines');
    }
};
