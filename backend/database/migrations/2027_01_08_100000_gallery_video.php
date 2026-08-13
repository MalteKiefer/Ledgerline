<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery: video support. Uploaded videos (any format) are processed on the
 * worker like thumbnails — ffprobe for metadata, a poster frame for the grid,
 * and a web-friendly MP4 rendition when the source is not directly playable.
 * status drives the "processing" placeholder tile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->string('media_type', 16)->default('image')->after('mime'); // image | video
            $table->string('status', 16)->default('ready')->after('media_type'); // ready | processing | failed
            $table->unsignedInteger('duration')->nullable()->after('height');    // seconds (video)
            $table->string('poster_path')->nullable()->after('motion_path');     // extracted frame (feeds thumb)
            $table->string('playback_path')->nullable()->after('poster_path');   // web MP4 rendition (null = serve original)
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['media_type', 'status', 'duration', 'poster_path', 'playback_path']);
        });
    }
};
