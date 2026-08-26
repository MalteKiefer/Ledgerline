<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets deleting here mean deleting on the server, without ever losing the mail.
 *
 * Deleting in a mail client is expected to reach the mailbox — otherwise the
 * phone still shows what was just thrown away. But this archive's promise is
 * that a message it holds is never unrecoverable, and those two only fit
 * together if the row survives its own deletion: the copy on the server goes,
 * the archived copy stays and says so.
 *
 * - removed_from_server_at: the origin copy is gone, this is now the only one.
 * - restore_folder: where a message was before it went to the trash folder, so
 *   putting it back is not a guess at INBOX.
 * - trash_folder: where "throw away" puts a message on that server. Servers
 *   disagree (Trash, Deleted Items, INBOX.Trash), so it is per account rather
 *   than a name we assume.
 * - write_back_deletes: its own switch, separate from the flag write-back,
 *   because this one destroys something on the far side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->timestamp('removed_from_server_at')->nullable()->after('trashed_at');
            $table->string('restore_folder', 255)->nullable()->after('removed_from_server_at');
        });

        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->string('trash_folder', 255)->nullable()->after('write_back_flags');
            $table->boolean('write_back_deletes')->default(true)->after('trash_folder');
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropColumn(['removed_from_server_at', 'restore_folder']);
        });
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn(['trash_folder', 'write_back_deletes']);
        });
    }
};
