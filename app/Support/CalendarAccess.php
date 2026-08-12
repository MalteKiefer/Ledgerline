<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Calendar;
use App\Models\CalendarShare;

/**
 * Resolves which calendars a user may read/write: their own (owner) plus
 * calendars shared TO them (viewer/editor). Calendar ids are UUID strings.
 * Central so the controller and the scheduling/free-busy code share one access
 * model.
 */
final class CalendarAccess
{
    /**
     * calendar_id (uuid) => role ('owner' | 'editor' | 'viewer'). Keys are the
     * UUID pks; PHP types array keys as int|string (numeric-string coercion).
     *
     * @return array<int|string, string>
     */
    public static function roles(int $uid): array
    {
        $roles = [];
        foreach (Calendar::query()->withoutGlobalScopes()->where('user_id', $uid)->pluck('id') as $id) {
            if (is_string($id) || is_int($id)) {
                $roles[(string) $id] = 'owner';
            }
        }
        foreach (CalendarShare::query()->withoutGlobalScopes()->where('recipient_id', $uid)->get(['calendar_id', 'role']) as $s) {
            $cid = (string) $s->calendar_id;
            if (! isset($roles[$cid])) { // a share never downgrades ownership
                $roles[$cid] = $s->role === 'editor' ? 'editor' : 'viewer';
            }
        }

        return $roles;
    }

    /** @return list<int|string> */
    public static function readableIds(int $uid): array
    {
        return array_keys(self::roles($uid));
    }

    /** @return list<int|string> */
    public static function writableIds(int $uid): array
    {
        $out = [];
        foreach (self::roles($uid) as $id => $role) {
            if ($role === 'owner' || $role === 'editor') {
                $out[] = $id;
            }
        }

        return $out;
    }

    public static function canRead(int $uid, string $calendarId): bool
    {
        return isset(self::roles($uid)[$calendarId]);
    }

    public static function canWrite(int $uid, string $calendarId): bool
    {
        $role = self::roles($uid)[$calendarId] ?? null;

        return $role === 'owner' || $role === 'editor';
    }
}
