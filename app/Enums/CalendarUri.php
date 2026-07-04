<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reserved calendar URIs the app manages itself. Centralises the coupling
 * between the default calendar, the virtual VTODO "tasks" mirror, and the
 * generated (read-only, contact/holiday-derived) calendars so the guards that
 * depend on them stay in one place.
 */
enum CalendarUri: string
{
    case Default = 'default';
    case Tasks = 'tasks';
    case Birthdays = 'birthdays';
    case Anniversaries = 'anniversaries';
    case Holidays = 'holidays';

    /** Calendars generated from other data (rebuilt, never hand-edited). */
    public static function derived(): array
    {
        return [self::Birthdays->value, self::Anniversaries->value, self::Holidays->value];
    }

    /** Virtual calendars not backed by the calendar_objects table for writes. */
    public static function virtual(): array
    {
        return [self::Tasks->value];
    }

    public static function isReserved(string $uri): bool
    {
        return in_array($uri, array_column(self::cases(), 'value'), true);
    }

    public static function isDerived(string $uri): bool
    {
        return in_array($uri, self::derived(), true);
    }

    public static function isVirtual(string $uri): bool
    {
        return in_array($uri, self::virtual(), true);
    }

    /** Reserved calendars the user must not delete through the UI. */
    public static function isUndeletable(string $uri): bool
    {
        return $uri === self::Default->value || self::isVirtual($uri) || self::isDerived($uri);
    }
}
