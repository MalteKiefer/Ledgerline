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
    'unit_distance',
    'unit_elevation',
    'unit_weight',
    'unit_temp',
    'unit_glucose',
    'time_format',
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
        'unit_distance' => 'km',
        'unit_elevation' => 'm',
        'unit_weight' => 'kg',
        'unit_temp' => 'c',
        'unit_glucose' => 'mgdl',
        'time_format' => '24h',
    ];

    /**
     * The non-secret display preferences as a flat map for injection into the page
     * and the API (window.LLPrefs / GET /me). Presentation only — never data.
     *
     * @return array{distance:string, elevation:string, weight:string, temp:string, glucose:string, time_format:string}
     */
    public function displayPrefs(): array
    {
        return [
            'distance' => (string) ($this->unit_distance ?? 'km'),
            'elevation' => (string) ($this->unit_elevation ?? 'm'),
            'weight' => (string) ($this->unit_weight ?? 'kg'),
            'temp' => (string) ($this->unit_temp ?? 'c'),
            'glucose' => (string) ($this->unit_glucose ?? 'mgdl'),
            'time_format' => (string) ($this->time_format ?? '24h'),
        ];
    }

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

        $setting = app($key);

        return $setting instanceof self ? $setting : static::query()->firstOrCreate(['user_id' => $userId]);
    }
}
