<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's personal preferences (Paperless, reminders, gallery, files, theme).
 * One row per user; use for() to fetch (or lazily create) the current user's
 * row. Infra/workspace settings live on AppSettings instead.
 */
#[Fillable([
    'user_id',
    'paperless_enabled',
    'paperless_url',
    'paperless_token',
    'paperless_synced_at',
    'reminder_channels',
    'gallery_columns',
    'file_max_versions',
    'theme',
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
            'reminder_channels' => 'array',
            'gallery_columns' => 'integer',
            'file_max_versions' => 'integer',
        ];
    }

    /** The settings row for a user, creating defaults on first use. */
    public static function for(int $userId): self
    {
        return static::query()->firstOrCreate(['user_id' => $userId]);
    }
}
