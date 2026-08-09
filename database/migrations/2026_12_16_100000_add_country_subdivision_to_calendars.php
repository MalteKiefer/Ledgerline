<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember the region a special (holidays / school_holidays) calendar was
 * generated for, so a later regenerate can re-query OpenHolidays with the same
 * scope. `country` is an ISO 3166-1 alpha-2 code (e.g. DE); `subdivision` is an
 * OpenHolidays subdivision code (e.g. DE-BY for the Bundesland Bayern) or null
 * for the national set. Both nullable + guarded so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendars', 'country')) {
                $table->string('country', 8)->nullable()->after('kind');
            }
            if (! Schema::hasColumn('calendars', 'subdivision')) {
                $table->string('subdivision', 16)->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table): void {
            foreach (['country', 'subdivision'] as $column) {
                if (Schema::hasColumn('calendars', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
