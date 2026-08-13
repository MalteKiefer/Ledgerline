<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery phase 3: non-invasive light edits. rotation (0/90/180/270, clockwise)
 * + flip_h are display/edit-only transforms — the original bytes are never
 * rewritten; they are baked only into the thumbnail and the "edited" download
 * variant. place is a human location label (from OSM autocomplete) alongside
 * the existing lat/lng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rotation')->default(0)->after('height');
            $table->boolean('flip_h')->default(false)->after('rotation');
            $table->string('place', 500)->nullable()->after('camera');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropColumn(['rotation', 'flip_h', 'place']);
        });
    }
};
