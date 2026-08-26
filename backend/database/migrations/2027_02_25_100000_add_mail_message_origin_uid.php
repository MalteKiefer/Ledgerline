<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which message on the origin server a row came from.
 *
 * Everything the archive can do today it does locally: a star, a read mark and
 * a folder move exist only here. To carry any of that back to the mailbox we
 * have to be able to name the message, and IMAP names it by UID within a
 * folder — a UID alone means nothing, because it is only valid for as long as
 * the folder's UIDVALIDITY is unchanged. Both, or neither.
 *
 * mbsync already writes the UID into the Maildir filename (`,U=<uid>`) and the
 * ingestor already reads it there; it was simply dropped on the floor. The
 * UIDVALIDITY comes from the folder's `.uidvalidity` file that isync keeps.
 *
 * Nullable on purpose: a message we appended ourselves, or one imported before
 * this migration, has no origin UID, and a write-back must skip it rather than
 * guess at one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('uid')->nullable()->after('folder');
            $table->unsignedBigInteger('uidvalidity')->nullable()->after('uid');
            // The lookup a write-back does: this account's folder, this
            // generation of it, this message.
            $table->index(['account_id', 'folder', 'uidvalidity', 'uid'], 'mail_messages_origin_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropIndex('mail_messages_origin_idx');
            $table->dropColumn(['uid', 'uidvalidity']);
        });
    }
};
