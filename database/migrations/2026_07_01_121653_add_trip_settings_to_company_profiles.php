<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thresholds for grouping geotagged photos into trips: a new trip starts when
 * the gap between consecutive photos exceeds the day threshold or the location
 * jumps further than the radius (km).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('gallery_trip_gap_days')->default(2)->after('paper_size');
            $table->unsignedSmallInteger('gallery_trip_radius_km')->default(100)->after('gallery_trip_gap_days');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn(['gallery_trip_gap_days', 'gallery_trip_radius_km']);
        });
    }
};
