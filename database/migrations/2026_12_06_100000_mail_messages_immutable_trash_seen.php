<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archived mail is IMMUTABLE and never hard-deleted:
 *  - "delete" in the UI only hides a message (trashed_at) — a soft archive/hide.
 *  - Deleting the IMAP ACCOUNT must NOT delete its archived mail: the
 *    account_id FK changes from cascadeOnDelete to nullOnDelete, so the
 *    messages survive (detached) when the account row is removed. (Full USER
 *    deletion still purges everything via the user_id cascade — that is the
 *    GDPR path, separate from removing a mailbox config.)
 *  - `seen` records whether the message was already read on the origin (from
 *    its Maildir flags at ingest) so push-back can restore the \Seen flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->timestamp('trashed_at')->nullable()->after('created_at');
            $table->boolean('seen')->default(false)->after('folder');
        });

        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropForeign(['account_id']);
        });

        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('account_id')->nullable()->change();
            $table->foreign('account_id')->references('id')->on('mail_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['trashed_at', 'seen']);
        });
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
        });
    }
};
