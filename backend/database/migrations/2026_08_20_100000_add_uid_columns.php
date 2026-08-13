<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised UID columns for exact, indexed dedup on import — replacing the
 * fragile `... LIKE '%UID:x%'` substring match (which mis-matched 'foo' against
 * 'foobar' and let a crafted UID overwrite the wrong object).
 *
 * (The original model-based backfill was dropped when the Calendar/Contacts
 * modules were removed; these tables are dropped by a later migration anyway.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts') && ! Schema::hasColumn('contacts', 'uid')) {
            Schema::table('contacts', function (Blueprint $table): void {
                $table->string('uid')->nullable()->index()->after('etag');
            });
        }
        if (Schema::hasTable('calendar_objects') && ! Schema::hasColumn('calendar_objects', 'uid')) {
            Schema::table('calendar_objects', function (Blueprint $table): void {
                $table->string('uid')->nullable()->index()->after('etag');
            });
        }
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('uid'));
        Schema::table('calendar_objects', fn (Blueprint $table) => $table->dropColumn('uid'));
    }
};
