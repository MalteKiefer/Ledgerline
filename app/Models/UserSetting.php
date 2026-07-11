<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's personal preferences (Paperless, gallery, files, theme).
 * One row per user; use for() to fetch (or lazily create) the current user's
 * row. Infra/workspace settings live on AppSettings instead.
 */
#[Fillable([
    'user_id',
    'paperless_enabled',
    'paperless_url',
    'paperless_token',
    'paperless_synced_at',
    'gallery_columns',
    'file_max_versions',
    'theme',
    'contact_birthday_channels',
    'contact_anniversary_channels',
])]
class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /** In-memory defaults so a freshly-created row reads correctly without a reload. */
    protected $attributes = [
        'paperless_enabled' => false,
        'gallery_columns' => 6,
        'file_max_versions' => 10,
        'theme' => 'system',
    ];

    protected function casts(): array
    {
        return [
            'paperless_enabled' => 'boolean',
            'paperless_url' => 'encrypted',
            'paperless_token' => 'encrypted',
            'paperless_synced_at' => 'datetime',
            'gallery_columns' => 'integer',
            'file_max_versions' => 'integer',
            'contact_birthday_channels' => 'array',
            'contact_anniversary_channels' => 'array',
        ];
    }

    /** The settings row for a user, creating defaults on first use. Memoised in
     *  the container (per-request in prod, reset between tests) since the layout
     *  and nav read the same row several times per page; update() mutates the
     *  cached instance in place. */
    public static function for(int $userId): self
    {
        $key = 'memo.user_setting.'.$userId;
        if (! app()->bound($key)) {
            app()->instance($key, static::query()->firstOrCreate(['user_id' => $userId]));
        }

        return app($key);
    }
}
