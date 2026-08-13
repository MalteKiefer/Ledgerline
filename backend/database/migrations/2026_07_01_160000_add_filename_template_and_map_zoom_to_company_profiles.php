<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery: a filename template applied to photo display names on import, and the
 * default zoom level used by the map elements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->string('gallery_filename_template')->nullable()->after('gallery_trip_radius_km');
            $table->unsignedSmallInteger('gallery_map_zoom')->default(13)->after('gallery_filename_template');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn(['gallery_filename_template', 'gallery_map_zoom']);
        });
    }
};
