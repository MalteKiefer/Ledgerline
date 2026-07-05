<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's personal preferences (calendar display + generated calendars). One
 * row per user; use for() to fetch (or lazily create) the current user's row.
 * Infra/workspace settings live on AppSettings instead.
 */
#[Fillable([
    'user_id',
    'calendar_week_start',
    'calendar_week_numbers',
    'calendar_default_event_minutes',
    'calendar_timezone',
    'calendar_birthdays_enabled',
    'calendar_anniversaries_enabled',
    'calendar_holiday_countries',
    'contact_sort',
    'contact_display_format',
    'paperless_enabled',
    'paperless_url',
    'paperless_token',
    'paperless_synced_at',
    'reminder_channels',
    'gallery_columns',
])]
class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /** In-memory defaults so a freshly-created row reads correctly without a reload. */
    protected $attributes = [
        'calendar_week_start' => 'monday',
        'calendar_week_numbers' => false,
        'calendar_default_event_minutes' => 60,
        'calendar_birthdays_enabled' => false,
        'calendar_anniversaries_enabled' => false,
        'contact_sort' => 'first_name',
        'contact_display_format' => 'first_last',
        'paperless_enabled' => false,
        'gallery_columns' => 6,
    ];

    protected function casts(): array
    {
        return [
            'calendar_week_numbers' => 'boolean',
            'calendar_default_event_minutes' => 'integer',
            'calendar_birthdays_enabled' => 'boolean',
            'calendar_anniversaries_enabled' => 'boolean',
            'calendar_holiday_countries' => 'array',
            'paperless_enabled' => 'boolean',
            'paperless_url' => 'encrypted',
            'paperless_token' => 'encrypted',
            'paperless_synced_at' => 'datetime',
            'reminder_channels' => 'array',
            'gallery_columns' => 'integer',
        ];
    }

    /** The settings row for a user, creating defaults on first use. */
    public static function for(int $userId): self
    {
        return static::query()->firstOrCreate(['user_id' => $userId]);
    }
}
