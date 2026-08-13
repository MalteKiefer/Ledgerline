<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File/image attachments for a note. Bytes are stored plaintext on the files
 * disk under notes/{uuid}; this row holds the metadata. Deleting a note (hard)
 * cascades the rows; the blobs are unlinked in the controller on detach/force.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('blob_path');
            $table->string('name', 500);
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_attachments');
    }
};
