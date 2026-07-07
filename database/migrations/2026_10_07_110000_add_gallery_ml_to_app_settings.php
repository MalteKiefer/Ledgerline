<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin-tunable gallery ML / face-recognition options + tool paths
// (null = fall back to config/env default). Idempotent: only adds missing cols.
return new class extends Migration
{
    public function up(): void
    {
        $cols = [
            'gallery_ml_enabled' => fn (Blueprint $t) => $t->boolean('gallery_ml_enabled')->nullable(),
            'gallery_ml_url' => fn (Blueprint $t) => $t->string('gallery_ml_url')->nullable(),
            'gallery_ml_clip_model' => fn (Blueprint $t) => $t->string('gallery_ml_clip_model')->nullable(),
            'gallery_face_enabled' => fn (Blueprint $t) => $t->boolean('gallery_face_enabled')->nullable(),
            'gallery_face_model' => fn (Blueprint $t) => $t->string('gallery_face_model')->nullable(),
            'gallery_ffmpeg_path' => fn (Blueprint $t) => $t->string('gallery_ffmpeg_path')->nullable(),
            'gallery_exiftool_path' => fn (Blueprint $t) => $t->string('gallery_exiftool_path')->nullable(),
            'gallery_duplicate_threshold' => fn (Blueprint $t) => $t->float('gallery_duplicate_threshold')->nullable(),
            'gallery_phash_max_distance' => fn (Blueprint $t) => $t->unsignedInteger('gallery_phash_max_distance')->nullable(),
            'gallery_face_min_score' => fn (Blueprint $t) => $t->float('gallery_face_min_score')->nullable(),
            'gallery_face_min_size' => fn (Blueprint $t) => $t->unsignedInteger('gallery_face_min_size')->nullable(),
            'gallery_face_cluster_threshold' => fn (Blueprint $t) => $t->float('gallery_face_cluster_threshold')->nullable(),
            'gallery_face_min_per_person' => fn (Blueprint $t) => $t->unsignedInteger('gallery_face_min_per_person')->nullable(),
            'gallery_geocode_interval_ms' => fn (Blueprint $t) => $t->unsignedInteger('gallery_geocode_interval_ms')->nullable(),
        ];
        Schema::table('app_settings', function (Blueprint $table) use ($cols) {
            foreach ($cols as $name => $add) {
                if (! Schema::hasColumn('app_settings', $name)) {
                    $add($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'gallery_ml_enabled', 'gallery_ml_url', 'gallery_ml_clip_model',
                'gallery_face_enabled', 'gallery_face_model', 'gallery_ffmpeg_path',
                'gallery_exiftool_path', 'gallery_duplicate_threshold', 'gallery_phash_max_distance',
                'gallery_face_min_score', 'gallery_face_min_size', 'gallery_face_cluster_threshold',
                'gallery_face_min_per_person', 'gallery_geocode_interval_ms',
            ]);
        });
    }
};
