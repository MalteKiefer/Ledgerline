<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * The single, global workspace settings row: mail and integration options.
 *
 * There is only ever one row; use current() to fetch (or lazily create) it.
 */
#[Fillable([
    'allow_registration',
    'max_connected_devices',
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
    'export_files_max_zip_mb',
    'export_notify_desktop',
    'export_notify_ntfy',
    'export_notify_mail',
    'export_notify_webhook',
    'files_max_upload_mb',
    'files_blob_orphan_grace_hours',
    'ml_enabled',
    'ml_face_enabled',
    'ml_url',
    'ml_clip_model',
    'ml_face_model',
    'ml_search_distance',
    'ml_dup_distance',
    'ml_face_min_score',
    'ml_face_match_distance',
])]
// Defense-in-depth: keep the operative-secret columns (SMTP / ntfy / webhook creds)
// out of any wholesale toArray()/toJson() as well as the encrypted-cast at-rest layer.
#[Hidden([
    'smtp_host', 'smtp_username', 'smtp_password', 'smtp_from_address', 'smtp_from_name',
    'ntfy_url', 'ntfy_topic', 'ntfy_token', 'webhook_url', 'webhook_secret',
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
            'max_connected_devices' => 'integer',
            // Notification/mail credentials: usable in the clear at runtime but
            // encrypted at rest (so they are not readable in a database backup).
            'allow_registration' => 'boolean',
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
            'export_files_max_zip_mb' => 'integer',
            'export_notify_desktop' => 'boolean',
            'export_notify_ntfy' => 'boolean',
            'export_notify_mail' => 'boolean',
            'export_notify_webhook' => 'boolean',
            'files_max_upload_mb' => 'integer',
            'files_blob_orphan_grace_hours' => 'integer',
            'ml_enabled' => 'boolean',
            'ml_face_enabled' => 'boolean',
            'ml_search_distance' => 'float',
            'ml_dup_distance' => 'float',
            'ml_face_min_score' => 'float',
            'ml_face_match_distance' => 'float',
        ];
    }

    /**
     * The settings row, creating an empty one on first use.
     */
    /** Request-scoped memo of the single global settings row (read on many pages).
     *  Held in the container, not a static, so it is per-request in prod (fresh
     *  app per FPM request) and reset between tests. */
    private const MEMO_KEY = 'memo.app_settings.current';

    public static function current(): self
    {
        if (! app()->bound(self::MEMO_KEY)) {
            app()->instance(self::MEMO_KEY, static::query()->firstOr(fn (): self => static::create()));
        }

        $settings = app(self::MEMO_KEY);

        return $settings instanceof self ? $settings : static::query()->firstOr(fn (): self => static::create());
    }
}
