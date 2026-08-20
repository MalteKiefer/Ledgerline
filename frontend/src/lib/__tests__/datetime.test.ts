// @vitest-environment jsdom
/**
 * Every date the SPA shows goes through this module, and the round trip
 * wall-clock -> UTC -> wall-clock is what calendar and task editors write back
 * to the server. A wrong offset here silently moves appointments, so the DST
 * boundaries and the civil-date rule are pinned.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    effectiveTz, fmtDate, fmtDateTime, fmtTime, fmtYmd, hoursInTz,
    setDateTimePrefs, timezoneList, todayYmd, utcToZonedInput, zonedInputToUtc,
} from '../datetime';

afterEach(() => setDateTimePrefs(null));

describe('preference resolution', () => {
    it('prefers the hard timezone over the browser', () => {
        setDateTimePrefs({ timezone: 'Asia/Tokyo' });
        expect(effectiveTz()).toBe('Asia/Tokyo');
    });

    it('falls back to the browser timezone when none is set', () => {
        setDateTimePrefs({ timezone: null });
        expect(effectiveTz()).toBe(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
    });
});

describe('date formats', () => {
    it.each([
        ['dmy', '05/03/2026'],
        ['dmy_dot', '05.03.2026'],
        ['mdy', '03/05/2026'],
        ['ymd', '2026-03-05'],
    ])('renders the %s preset', (fmt, expected) => {
        setDateTimePrefs({ date_format: fmt });
        expect(fmtYmd(2026, 3, 5)).toBe(expected);
    });

    it('treats a bare YYYY-MM-DD as a civil date and never shifts it', () => {
        // A birthday must not move a day because the viewer sits in Tokyo.
        setDateTimePrefs({ date_format: 'ymd', timezone: 'Pacific/Auckland' });
        expect(fmtDate('2026-03-05')).toBe('2026-03-05');
        setDateTimePrefs({ date_format: 'ymd', timezone: 'America/Los_Angeles' });
        expect(fmtDate('2026-03-05')).toBe('2026-03-05');
    });

    it('does shift a real instant into the effective timezone', () => {
        setDateTimePrefs({ date_format: 'ymd', timezone: 'Asia/Tokyo' });
        // 23:30 UTC is already the next day in Tokyo (+09:00).
        expect(fmtDate('2026-03-05T23:30:00Z')).toBe('2026-03-06');
    });

    it('returns an empty string for empty input and echoes unparseable input', () => {
        expect(fmtDate(null)).toBe('');
        expect(fmtDate('')).toBe('');
        expect(fmtDate('not a date')).toBe('not a date');
    });
});

describe('time formats', () => {
    it('renders 24h and 12h per preference', () => {
        setDateTimePrefs({ time_format: '24h', timezone: 'UTC' });
        expect(fmtTime('2026-03-05T13:05:00Z')).toBe('13:05');
        setDateTimePrefs({ time_format: '12h', timezone: 'UTC' });
        expect(fmtTime('2026-03-05T13:05:00Z')).toMatch(/1:05\s?PM/i);
    });

    it('joins date and time', () => {
        setDateTimePrefs({ date_format: 'ymd', time_format: '24h', timezone: 'UTC' });
        expect(fmtDateTime('2026-03-05T13:05:00Z')).toBe('2026-03-05, 13:05');
    });
});

describe('wall clock <-> UTC round trip', () => {
    it.each([
        ['Europe/Berlin', '2026-03-05T09:00', '2026-03-05T08:00:00.000Z'], // CET, +1
        ['Europe/Berlin', '2026-07-05T09:00', '2026-07-05T07:00:00.000Z'], // CEST, +2
        ['UTC', '2026-07-05T09:00', '2026-07-05T09:00:00.000Z'],
        ['Asia/Tokyo', '2026-07-05T09:00', '2026-07-05T00:00:00.000Z'], // +9, no DST
        ['America/New_York', '2026-01-15T12:00', '2026-01-15T17:00:00.000Z'], // EST, -5
        ['America/New_York', '2026-07-15T12:00', '2026-07-15T16:00:00.000Z'], // EDT, -4
    ])('%s %s -> %s', (tz, wall, utc) => {
        expect(zonedInputToUtc(wall, tz)).toBe(utc);
    });

    it.each(['Europe/Berlin', 'Asia/Tokyo', 'America/New_York', 'UTC', 'Pacific/Auckland'])(
        'round trips through %s without drift',
        (tz) => {
            for (const wall of ['2026-01-15T08:30', '2026-07-15T23:45', '2026-11-02T01:15']) {
                expect(utcToZonedInput(zonedInputToUtc(wall, tz), tz)).toBe(wall);
            }
        },
    );

    it('formats an all-day value as a bare date', () => {
        expect(utcToZonedInput('2026-03-05T23:00:00Z', 'Europe/Berlin', true)).toBe('2026-03-06');
    });

    it('defaults a missing time part to midnight', () => {
        expect(zonedInputToUtc('2026-03-05', 'UTC')).toBe('2026-03-05T00:00:00.000Z');
    });
});

describe('todayYmd', () => {
    it('reports the civil date of the effective timezone, not of UTC', () => {
        // 22:30 UTC is already tomorrow in Auckland and still today in New York;
        // the default date on a new invoice must follow the user, not UTC.
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-03-05T22:30:00Z'));
        try {
            expect(todayYmd('Pacific/Auckland')).toBe('2026-03-06');
            expect(todayYmd('America/New_York')).toBe('2026-03-05');
            expect(todayYmd('UTC')).toBe('2026-03-05');
        } finally {
            vi.useRealTimers();
        }
    });
});

describe('grid helpers', () => {
    it('reports the fractional hour an instant shows in a timezone', () => {
        expect(hoursInTz('2026-07-05T07:30:00Z', 'Europe/Berlin')).toBeCloseTo(9.5, 5);
        expect(hoursInTz('2026-07-05T22:00:00Z', 'UTC')).toBeCloseTo(22, 5);
    });

    it('maps midnight to 0, not 24', () => {
        expect(hoursInTz('2026-07-05T22:00:00Z', 'Europe/Berlin')).toBeCloseTo(0, 5);
    });

    it('offers a deduped timezone list that starts with the browser zone', () => {
        const list = timezoneList();
        expect(list.length).toBeGreaterThan(10);
        expect(new Set(list).size).toBe(list.length);
        expect(list[0]).toBe(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
    });
});
