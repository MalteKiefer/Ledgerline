<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support background processing of photos and store their location.
 *
 * On upload only the original is saved; a queued job then generates the
 * renditions and reads EXIF (date, GPS, camera). status tracks that lifecycle,
 * and latitude/longitude hold the capture location for the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->string('status')->default('ready')->after('name');
            $table->timestamp('processed_at')->nullable()->after('taken_at');
            $table->decimal('latitude', 10, 7)->nullable()->after('height');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('camera')->nullable()->after('longitude');

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn(['status', 'processed_at', 'latitude', 'longitude', 'camera']);
        });
    }
};
