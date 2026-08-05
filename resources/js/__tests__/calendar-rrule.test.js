import { describe, it, expect } from 'vitest';
import { expandEvent, buildRRuleString, parseRRuleString } from '../shared/calendar-rrule.js';

describe('calendar-rrule', () => {
    it('expands a weekly timed event, preserving wall-clock', () => {
        const ev = { id: 'e1', start: '2026-08-05T09:00', end: '2026-08-05T10:00', rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=4' };
        const occ = expandEvent(ev, '2026-08-01', '2026-08-31');
        expect(occ.map((o) => o.start)).toEqual([
            '2026-08-05T09:00', '2026-08-12T09:00', '2026-08-19T09:00', '2026-08-26T09:00',
        ]);
        expect(occ[0].end).toBe('2026-08-05T10:00');
        expect(occ[0]._base).toBe('e1');
        expect(occ[0].recurrenceId).toBe('2026-08-05');
    });

    it('honours EXDATE', () => {
        const ev = { id: 'e2', start: '2026-08-05T09:00', end: '2026-08-05T10:00', rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=4', exdates: ['2026-08-12'] };
        const days = expandEvent(ev, '2026-08-01', '2026-08-31').map((o) => o.start.slice(0, 10));
        expect(days).toEqual(['2026-08-05', '2026-08-19', '2026-08-26']);
    });

    it('expands an all-day daily event to date-only strings', () => {
        const ev = { id: 'e3', allDay: true, start: '2026-08-05', end: '2026-08-05', rrule: 'FREQ=DAILY;COUNT=3' };
        const occ = expandEvent(ev, '2026-08-01', '2026-08-31');
        expect(occ.map((o) => o.start)).toEqual(['2026-08-05', '2026-08-06', '2026-08-07']);
        expect(occ[0].end).toBe('2026-08-06'); // all-day inclusive end = next day
    });

    it('non-recurring events expand to nothing', () => {
        expect(expandEvent({ id: 'x', start: '2026-08-05T09:00' }, '2026-08-01', '2026-08-31')).toEqual([]);
    });

    it('builds and parses RRULE strings round-trip', () => {
        const s = buildRRuleString({ freq: 'WEEKLY', interval: 2, byday: ['MO', 'WE'], ends: 'count', count: 10 });
        expect(s).toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=10');
        const o = parseRRuleString(s);
        expect(o).toMatchObject({ freq: 'WEEKLY', interval: 2, byday: ['MO', 'WE'], ends: 'count', count: 10 });

        const u = buildRRuleString({ freq: 'MONTHLY', ends: 'until', until: '2027-01-31' });
        expect(u).toBe('FREQ=MONTHLY;UNTIL=20270131T235959Z');
        expect(parseRRuleString(u)).toMatchObject({ freq: 'MONTHLY', ends: 'until', until: '2027-01-31' });
    });
});
