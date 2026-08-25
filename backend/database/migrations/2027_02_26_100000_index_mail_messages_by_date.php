<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order the mailbox by the message's own date.
 *
 * The list has always ordered by `created_at`, which is the archive timestamp —
 * and the ingestor snaps it to the hour. So everything imported within one hour
 * shared a key and came back in whatever order the database chose, and a
 * backfilled mail from 2019 sorted above one that arrived yesterday. The `date`
 * column (the message's own Date header) has been there all along with no index
 * on it.
 *
 * Indexed together with user_id because every query is owner-scoped, and that is
 * the only shape this index is ever asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->index(['user_id', 'date'], 'mail_messages_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropIndex('mail_messages_user_date_idx');
        });
    }
};
