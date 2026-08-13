<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive — Phase 2 (attachments). One row per extracted MIME attachment
 * part (real + inline/cid), the decoded bytes stored plaintext on the files disk
 * at `mail/att/{blob}` via BlobStore. The raw .eml blob (mail/{id}) still holds
 * the authoritative copy; these rows exist so the reader can list/view/save
 * attachments and inline cid: images without re-parsing on every open.
 *
 * `mail_blobs` gains a `kind` column (message|attachment) so the ownership
 * ledger — which now covers BOTH the raw .eml (mail/{id}) and each attachment
 * (mail/att/{blob}) — knows which on-disk prefix a row backs, for the orphan
 * sweep and the GDPR purge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('blob');                            // bytes at mail/att/{blob}
            $table->string('filename', 500)->nullable();
            $table->string('content_type', 255)->nullable();
            $table->string('content_id', 512)->nullable();   // cid ref (inline images)
            $table->boolean('inline')->default(false);
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->foreign('message_id')->references('id')->on('mail_messages')->cascadeOnDelete();
            $table->index(['user_id', 'message_id']);
            $table->index('blob');
        });

        Schema::table('mail_blobs', function (Blueprint $table): void {
            // message = raw .eml (mail/{blob}); attachment = decoded part (mail/att/{blob}).
            $table->string('kind', 16)->default('message')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('mail_blobs', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
        Schema::dropIfExists('mail_attachments');
    }
};
