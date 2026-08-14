<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store thumbnail/preview readiness on the row so the timeline (GET /gallery/data)
 * never stats the disk. Previously row() did two filesystem exists() per photo —
 * ~38k blocking stat calls for a 19k-photo library, which timed out the request.
 * The worker sets these after rendering; the timeline reads the columns (zero I/O).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->boolean('thumb_ready')->default(false)->index();
            $table->boolean('preview_ready')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropColumn(['thumb_ready', 'preview_ready']);
        });
    }
};
