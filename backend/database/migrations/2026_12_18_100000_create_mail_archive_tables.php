<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive — Phase 1 (plaintext-relational rebuild of the removed ZK mail
 * module). Mirrors the contacts/files/calendar modules: one owner-scoped row
 * per record, plaintext bytes on the files disk (via BlobStore, prefix
 * `mail/{uuid}`), server-side full-text search. The ONLY encrypted-at-rest
 * value is the IMAP password (Laravel `encrypted` cast, APP_KEY) — never a
 * message body or header.
 *
 *   mail_accounts    — per-user IMAP account config (password encrypted).
 *   mail_sync_state  — reserved per-folder CONDSTORE cursor (unused today;
 *                      live resume anchors are mbsync's on-disk state + the
 *                      (user_id, content_hash) dedup).
 *   mail_messages    — archived message: denormalised envelope + text body +
 *                      server-sanitised HTML + auth/spam signals + search text.
 *                      IMMUTABLE — never edited, only seen/trashed toggles.
 *   mail_blobs       — ownership ledger for the raw .eml bytes (mail/{id});
 *                      drives quota + orphan reclaim.
 *   mail_logs        — per-account sync/ingest diagnostic trail (metadata only).
 *
 * Deleting an ACCOUNT keeps its archived mail (account_id nullOnDelete); only a
 * full USER delete cascades everything (GDPR). pgsql gets a GIN full-text index
 * over search_text (driver-guarded; sqlite falls back to LIKE in the controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            $table->string('username', 255);
            $table->text('password');                       // encrypted cast (APP_KEY)
            $table->string('encryption', 16);               // ssl|tls|starttls|none
            $table->json('folders')->nullable();            // allow-list; null = all
            $table->date('backfill_since')->nullable();
            $table->boolean('delete_after_import')->default(false);
            $table->boolean('skip_spam')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sync_interval_minutes')->nullable();
            $table->string('status', 16)->default('idle');  // idle|syncing|error
            $table->string('sync_batch_id')->nullable();    // Bus::batch id of an in-flight sync
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('mail_sync_state', function (Blueprint $table): void {
            $table->foreignId('account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->string('folder');
            $table->unsignedBigInteger('uidvalidity')->nullable();
            $table->unsignedBigInteger('highest_uid')->default(0);
            $table->unsignedBigInteger('highmodseq')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['account_id', 'folder']);
        });

        Schema::create('mail_messages', function (Blueprint $table): void {
            // id doubles as the raw-blob key mail/{id} (see MaildirIngestor).
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Deleting the mailbox keeps the archived mail; only user delete cascades.
            $table->foreignId('account_id')->nullable()->constrained('mail_accounts')->nullOnDelete();
            $table->string('folder');

            // Dedup / integrity.
            $table->string('content_hash');                 // sha256 of the raw message
            $table->unsignedBigInteger('size');             // raw byte size

            // Denormalised envelope (the raw .eml blob remains authoritative).
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('thread_id')->nullable();        // computed later (threading phase)
            $table->text('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->json('to_json')->nullable();            // [{name,email}]
            $table->json('cc_json')->nullable();
            $table->string('reply_to')->nullable();
            $table->timestamp('date')->nullable();          // the message's own Date header
            $table->boolean('has_attachment')->default(false);
            $table->unsignedSmallInteger('attachment_count')->default(0);

            // Body cache (plaintext, for reading + search).
            $table->longText('text_body')->nullable();
            $table->longText('html_sanitized')->nullable(); // server-sanitised HTML, or null

            // Security signals.
            $table->boolean('spam')->default(false);
            $table->string('spf', 16)->nullable();
            $table->string('dkim', 16)->nullable();
            $table->string('dmarc', 16)->nullable();
            $table->string('encrypted_type', 8)->nullable();  // null|pgp|smime (decrypt phase)
            $table->string('decrypt_status', 8)->nullable();  // null|ok|nokey|fail

            // State.
            $table->boolean('seen')->default(false);
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('created_at')->nullable();      // archived-at, hour-snapped

            // Search.
            $table->longText('search_text')->nullable();
            $table->timestamp('indexed_at')->nullable();

            $table->unique(['user_id', 'content_hash']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'account_id', 'folder']);
            $table->index(['user_id', 'thread_id']);
            $table->index(['user_id', 'trashed_at']);
        });

        Schema::create('mail_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('mail_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level', 8)->default('info');    // info|warn|error
            $table->string('event', 64);
            $table->string('folder', 255)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['account_id', 'id']);
            $table->index('created_at');
        });

        // Full-text index (pgsql only; sqlite falls back to LIKE in the
        // controller). Guarded so a non-pgsql connection is a no-op.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS mail_messages_search_text_gin ON mail_messages USING gin (to_tsvector('simple', coalesce(search_text, '')))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS mail_messages_search_text_gin');
        }
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('mail_blobs');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_sync_state');
        Schema::dropIfExists('mail_accounts');
    }
};
