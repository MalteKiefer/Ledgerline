<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist a picked location's coordinates on an event so the SPA can re-show a
 * mini-map marker. The GEO coordinate is denormalised from the VEVENT GEO
 * property (the ICS stays authoritative); LOCATION remains the human address.
 * Additive + nullable — existing events keep working with no GEO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendar_events', 'geo_lat')) {
                $table->decimal('geo_lat', 10, 7)->nullable();  // -90..90
            }
            if (! Schema::hasColumn('calendar_events', 'geo_lon')) {
                $table->decimal('geo_lon', 10, 7)->nullable();  // -180..180
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            if (Schema::hasColumn('calendar_events', 'geo_lat')) {
                $table->dropColumn(['geo_lat', 'geo_lon']);
            }
        });
    }
};
