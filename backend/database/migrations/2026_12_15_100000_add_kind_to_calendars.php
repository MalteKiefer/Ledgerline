<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish special (generated, read-only) calendars from normal ones. A
 * `kind` of `holidays` or `birthdays` marks a calendar whose events are produced
 * server-side (German national public holidays / contact birthdays) and are not
 * user-editable; `normal` (the default) is the ordinary editable/imported
 * calendar. Additive + guarded so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendars', 'kind')) {
                $table->string('kind', 20)->default('normal')->after('color'); // normal | holidays | birthdays
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table): void {
            if (Schema::hasColumn('calendars', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
