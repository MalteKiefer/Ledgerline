<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured reverse-geocoded address parts (road, city, state, postcode,
 * country, …) for a nicer location breakdown next to the display name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->json('place_details')->nullable()->after('place');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn('place_details');
        });
    }
};
