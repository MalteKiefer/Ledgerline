<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The single, global workspace settings row: gallery, vault and mail options.
 *
 * There is only ever one row; use current() to fetch (or lazily create) it.
 */
#[Fillable([
    'gallery_trip_gap_days',
    'gallery_trip_radius_km',
    'gallery_filename_template',
    'gallery_map_zoom',
    'gallery_max_upload_mb',
    'gallery_ffmpeg_path',
    'gallery_video_frame',
    'gallery_geocode_grid_km',
    'vault_idle_minutes',
    'mail_sync_minutes',
    'mail_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_encryption',
    'smtp_username',
    'smtp_password',
    'smtp_from_address',
    'smtp_from_name',
    'ntfy_enabled',
    'ntfy_url',
    'ntfy_topic',
    'ntfy_token',
    'webhook_enabled',
    'webhook_url',
    'webhook_secret',
])]
class AppSettings extends Model
{
    protected $table = 'app_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery_trip_gap_days' => 'integer',
            'gallery_trip_radius_km' => 'integer',
            'gallery_map_zoom' => 'integer',
            'gallery_max_upload_mb' => 'integer',
            'gallery_video_frame' => 'integer',
            'gallery_geocode_grid_km' => 'float',
            'vault_idle_minutes' => 'integer',
            'mail_sync_minutes' => 'integer',
            // Notification/mail credentials: usable in the clear at runtime but
            // encrypted at rest (so they are not readable in a database backup).
            'mail_enabled' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_host' => 'encrypted',
            'smtp_username' => 'encrypted',
            'smtp_password' => 'encrypted',
            'smtp_from_address' => 'encrypted',
            'smtp_from_name' => 'encrypted',
            'ntfy_enabled' => 'boolean',
            'ntfy_url' => 'encrypted',
            'ntfy_topic' => 'encrypted',
            'ntfy_token' => 'encrypted',
            'webhook_enabled' => 'boolean',
            'webhook_url' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }

    /**
     * The settings row, creating an empty one on first use.
     */
    public static function current(): self
    {
        return static::query()->firstOr(fn (): self => static::create());
    }
}
