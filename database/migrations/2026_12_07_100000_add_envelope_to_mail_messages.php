<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-message sealed ENVELOPE (headers only: from/to/subject/date/attachment).
 * A tiny blob sealed to the user's identity public keys — the SAME format as
 * the body (hybrid-wrapped per-message key + framed secretstream), so it opens
 * with the same client primitive. Nullable: built lazily CLIENT-side the first
 * time a message is processed (the server cannot read the already-sealed body,
 * so it can't build these itself) and stored here durably so other devices /
 * a cleared cache never re-decrypt the full body just to list.
 *
 * This is the scalable list/search index: the client decrypts only these tiny
 * envelopes (cached in IndexedDB), never the full bodies, so a 12k-message
 * mailbox lists fast and a sync only processes the new messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->text('envelope')->nullable();      // base64 framed secretstream blob
            $table->text('envelope_key')->nullable();  // hybrid-wrap suite envelope (JSON)
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropColumn(['envelope', 'envelope_key']);
        });
    }
};
