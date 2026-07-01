<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery: the maximum upload size and the ffmpeg settings used to generate
 * video thumbnails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('gallery_max_upload_mb')->default(200)->after('gallery_map_zoom');
            $table->string('gallery_ffmpeg_path')->nullable()->after('gallery_max_upload_mb');
            $table->unsignedSmallInteger('gallery_video_frame')->default(1)->after('gallery_ffmpeg_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table): void {
            $table->dropColumn(['gallery_max_upload_mb', 'gallery_ffmpeg_path', 'gallery_video_frame']);
        });
    }
};
