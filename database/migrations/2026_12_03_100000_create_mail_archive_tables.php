<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail archive data model: per-user IMAP account config, per-folder sync
 * cursors, and the message ledger. Message CONTENT (the sealed RFC822 bytes)
 * lives as a content-addressed blob on the files disk (mail/{blob}, ledgered
 * in mail_blobs — mirrors contact_blobs); this table set only holds the
 * account config, sync bookkeeping and per-message metadata + the sealed
 * per-message content key (sealed_key). The IMAP account password is the one
 * plaintext secret the server must hold to run the sync — kept `encrypted`
 * at rest via the model cast (APP_KEY), same exception class as backup-job
 * passphrases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('username');
            $table->text('password'); // encrypted cast on the model
            $table->string('encryption', 16); // ssl | tls | starttls | none
            $table->json('folders')->nullable();
            $table->date('backfill_since')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 16)->default('idle'); // idle | syncing | error
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // CURRENTLY UNUSED / reserved scaffolding: no code path reads or writes
        // this table yet (see App\Models\MailSyncState's docblock). The live
        // resume anchors are mbsync's own UID/UIDVALIDITY state files on disk
        // plus mail_messages' (user_id, content_hash) dedup — not these
        // columns. Kept for a future explicit server-side cursor.
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
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->string('folder');
            $table->string('content_hash');
            $table->unsignedBigInteger('size');
            $table->text('sealed_key');
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'content_hash']);
            $table->index(['user_id', 'created_at']);
        });

        // Content-blob ownership ledger for the sealed RFC822 message bytes
        // (mail/{blob}) — quota + orphan reclaim, mirrors contact_blobs exactly.
        Schema::create('mail_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // FK-safe order: dependents first.
        Schema::dropIfExists('mail_blobs');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_sync_state');
        Schema::dropIfExists('mail_accounts');
    }
};
