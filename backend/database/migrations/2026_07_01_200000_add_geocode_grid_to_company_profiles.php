<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery: the reverse-geocoding grid size (km). Coordinates within one grid
 * cell share a single place lookup, saving requests to OpenStreetMap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->decimal('gallery_geocode_grid_km', 6, 3)->default(0.5)->after('gallery_video_frame');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn('gallery_geocode_grid_km');
        });
    }
};
