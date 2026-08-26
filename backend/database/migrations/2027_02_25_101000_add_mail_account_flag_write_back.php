<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether read marks and stars set here are carried back to the mailbox.
 *
 * Without it the read state of a mailbox has two different answers depending on
 * where you look: a hundred newsletters marked read in the archive stay bold in
 * the phone's mail app. So the default is on — that is what a mail client is
 * expected to do.
 *
 * It is a switch and not a constant because writing to the origin is the thing
 * this archive otherwise refuses to do: everything else is pull-only, and the
 * two existing write paths (push-back, delete-from-origin) are single explicit
 * actions. Someone treating a mailbox as a read-only source of truth can turn
 * this off and keep that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->boolean('write_back_flags')->default(true)->after('delete_after_import');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('write_back_flags');
        });
    }
};
