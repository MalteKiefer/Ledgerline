<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the Gallery module. Drops the gallery blob ledger + sealed store, the
 * per-user/per-group gallery quota, the user-settings gallery display column, and
 * every gallery/ML/face workspace setting on app_settings (the ML sidecar is gone).
 * One-way (feature removal): down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gallery_blobs');
        Schema::dropIfExists('gallery_store');

        $this->dropColumns('storage_snapshots', ['gallery_bytes']);
        $this->dropColumns('users', ['gallery_quota_mb']);
        $this->dropColumns('groups', ['gallery_quota_mb']);
        $this->dropColumns('user_settings', ['gallery_columns']);
        $this->dropColumns('app_settings', [
            'gallery_trip_gap_days', 'gallery_trip_radius_km', 'gallery_filename_template',
            'gallery_map_zoom', 'gallery_max_upload_mb', 'gallery_video_frame',
            'gallery_geocode_grid_km', 'export_gallery_max_zip_mb',
            'gallery_ml_enabled', 'gallery_ml_url', 'gallery_ml_clip_model',
            'gallery_ffmpeg_path', 'gallery_exiftool_path',
            'gallery_face_enabled', 'gallery_face_model', 'gallery_face_min_score',
            'gallery_face_min_size', 'gallery_face_cluster_threshold', 'gallery_face_min_per_person',
            'gallery_duplicate_threshold', 'gallery_phash_max_distance', 'gallery_geocode_interval_ms',
        ]);
    }

    /** Drop only the columns that actually exist (safe across environments). */
    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $present = array_values(array_filter($columns, fn (string $c): bool => Schema::hasColumn($table, $c)));
        if ($present === []) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($present): void {
            $t->dropColumn($present);
        });
    }

    public function down(): void
    {
        // One-way: the Gallery module was removed; no restore.
    }
};
