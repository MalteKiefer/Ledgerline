<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two flags every mail client has and this archive never had: starred and
 * answered.
 *
 * `seen` was modelled from the start, these two were not — so a message could be
 * found but not set aside, and nothing recorded that it had been replied to. Both
 * default false, which is what every existing row means.
 *
 * `answered` is set automatically when a reply goes out (the sender knows which
 * message it answered), `flagged` by hand. Local for now; once the mailbox is
 * written back to, both become the local half of the IMAP flags of the same name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('mail_messages', 'flagged')) {
                $table->boolean('flagged')->default(false)->after('seen_at');
            }
            if (! Schema::hasColumn('mail_messages', 'answered')) {
                $table->boolean('answered')->default(false)->after('flagged');
            }
        });
        // Starred mail is looked up as a shortlist ("what did I set aside"), so it
        // gets its own partial-shaped index rather than riding the date one.
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->index(['user_id', 'flagged'], 'mail_messages_user_flagged_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropIndex('mail_messages_user_flagged_idx');
            $table->dropColumn(['flagged', 'answered']);
        });
    }
};
