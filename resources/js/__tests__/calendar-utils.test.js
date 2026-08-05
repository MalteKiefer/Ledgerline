import { describe, it, expect } from 'vitest';
import { ymd, monthMatrix, overlapsDay, eventsOnDay, timeLabel } from '../shared/calendar-utils.js';

describe('calendar-utils', () => {
    it('ymd formats local date parts', () => {
        expect(ymd(new Date(2026, 7, 5))).toBe('2026-08-05');
        expect(ymd(new Date(2026, 0, 1))).toBe('2026-01-01');
    });

    it('monthMatrix is 6×7 and Monday-aligned', () => {
        const m = monthMatrix(2026, 7, '2026-08-05'); // August 2026
        expect(m).toHaveLength(6);
        expect(m[0]).toHaveLength(7);
        // Aug 1 2026 is a Saturday; the Monday-aligned grid starts on Jul 27.
        expect(m[0][0].iso).toBe('2026-07-27');
        expect(m[0][0].inMonth).toBe(false);
        const today = m.flat().find((c) => c.isToday);
        expect(today.iso).toBe('2026-08-05');
    });

    it('overlapsDay handles timed and all-day', () => {
        const timed = { start: '2026-08-05T10:00', end: '2026-08-05T11:00' };
        expect(overlapsDay(timed, '2026-08-05')).toBe(true);
        expect(overlapsDay(timed, '2026-08-06')).toBe(false);

        const allDay = { allDay: true, start: '2026-08-05', end: '2026-08-06' };
        expect(overlapsDay(allDay, '2026-08-05')).toBe(true);
        expect(overlapsDay(allDay, '2026-08-06')).toBe(true);
        expect(overlapsDay(allDay, '2026-08-07')).toBe(false);
    });

    it('eventsOnDay sorts all-day first then by time, skips cancelled', () => {
        const events = [
            { id: 'a', start: '2026-08-05T14:00', end: '2026-08-05T15:00' },
            { id: 'b', allDay: true, start: '2026-08-05', end: '2026-08-05' },
            { id: 'c', start: '2026-08-05T09:00', end: '2026-08-05T10:00' },
            { id: 'd', start: '2026-08-05T08:00', end: '2026-08-05T09:00', status: 'cancelled' },
        ];
        const on = eventsOnDay(events, '2026-08-05').map((e) => e.id);
        expect(on).toEqual(['b', 'c', 'a']);
    });

    it('timeLabel', () => {
        expect(timeLabel({ allDay: true, start: '2026-08-05' })).toBe('');
        expect(timeLabel({ start: '2026-08-05T09:05' })).toBe('09:05');
    });
});
