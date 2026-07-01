<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destructive photo edits. Rotation (clockwise degrees) and a horizontal
 * flip are stored and applied when renditions are (re)generated — the original
 * file is never modified. meta_locked marks a photo whose date/location have
 * been edited by hand, so a re-scan does not overwrite them from EXIF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rotation')->default(0)->after('camera');
            $table->boolean('flipped')->default(false)->after('rotation');
            $table->boolean('meta_locked')->default(false)->after('flipped');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn(['rotation', 'flipped', 'meta_locked']);
        });
    }
};
