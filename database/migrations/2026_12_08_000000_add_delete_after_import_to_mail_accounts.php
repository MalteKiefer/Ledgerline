<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            // When true, a message is deleted from the ORIGIN IMAP mailbox
            // immediately after it has been durably archived (UID STORE \Deleted
            // + EXPUNGE). Off by default — this is a destructive write-to-origin.
            $table->boolean('delete_after_import')->default(false)->after('backfill_since');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('delete_after_import');
        });
    }
};
