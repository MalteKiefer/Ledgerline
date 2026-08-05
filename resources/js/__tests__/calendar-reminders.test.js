import { describe, it, expect } from 'vitest';
import { reminderFireMs, reminderKey, collectReminders, isoToMs } from '../shared/calendar-reminders.js';

describe('calendar-reminders', () => {
    it('reminderFireMs subtracts minutes', () => {
        expect(reminderFireMs('2026-08-05T09:00', 15)).toBe(isoToMs('2026-08-05T08:45'));
        expect(reminderFireMs('2026-08-05T09:00', 0)).toBe(isoToMs('2026-08-05T09:00'));
    });

    it('reminderKey is stable', () => {
        expect(reminderKey('e1', '2026-08-05', 10)).toBe('e1:2026-08-05:10');
    });

    it('collects reminders for a single event in range', () => {
        const ev = { id: 'e1', title: 'X', start: '2026-08-05T09:00', end: '2026-08-05T10:00', reminders: [{ minutesBefore: 15, method: 'local' }] };
        const from = isoToMs('2026-08-05T08:00');
        const to = isoToMs('2026-08-05T09:00');
        const r = collectReminders([ev], from, to);
        expect(r).toHaveLength(1);
        expect(r[0].fireMs).toBe(isoToMs('2026-08-05T08:45'));
        expect(r[0].key).toBe('e1:2026-08-05:15');
        expect(r[0].title).toBe('X');
    });

    it('expands recurring reminders and filters by range', () => {
        const ev = { id: 'r1', title: 'Standup', start: '2026-08-05T09:00', end: '2026-08-05T09:15', rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=4', reminders: [{ minutesBefore: 10 }] };
        const from = isoToMs('2026-08-05T00:00');
        const to = isoToMs('2026-08-20T00:00');
        const r = collectReminders([ev], from, to);
        expect(r.map((x) => x.start)).toEqual(['2026-08-05T09:00', '2026-08-12T09:00', '2026-08-19T09:00']);
        expect(r[0].fireMs).toBe(isoToMs('2026-08-05T08:50'));
    });

    it('skips events without reminders and cancelled ones', () => {
        expect(collectReminders([{ id: 'a', start: '2026-08-05T09:00' }], 0, Infinity)).toHaveLength(0);
        expect(collectReminders([{ id: 'b', start: '2026-08-05T09:00', reminders: [{ minutesBefore: 0 }], status: 'cancelled' }], 0, Infinity)).toHaveLength(0);
    });
});
