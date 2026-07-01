<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            // Reverse-geocoded place name for the capture location.
            $table->string('place')->nullable()->after('longitude');
            // Full metadata dump (all EXIF sections) for display and reference.
            $table->json('metadata')->nullable()->after('camera');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn(['place', 'metadata']);
        });
    }
};
