<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a picture for a mail sender may come from.
 *
 *   off      — initials only, nothing looked up at all
 *   contacts — the address book (default): your own data, no request leaves here
 *   domain    — the above, then the sender's DOMAIN: BIMI (the standard by which
 *              a company publishes a logo for mail, in DNS) and then favicons
 *
 * Default `contacts`, because that is the setting that sends nothing. The domain
 * rung tells a favicon service which domains write to you — never which address.
 *
 * Gravatar and Libravatar are deliberately absent: they are keyed by a hash of
 * the address itself, so asking them announces "this exact mailbox exists and
 * someone is reading its mail", for every correspondent on every page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('mail_avatars', 16)->default('contacts')->after('mail_signature');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn('mail_avatars');
        });
    }
};
