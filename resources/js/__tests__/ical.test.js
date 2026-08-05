import { describe, it, expect } from 'vitest';
import { parseIcs, buildIcs } from '../shared/ical.js';

const SAMPLE = [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN',
    'BEGIN:VEVENT', 'UID:abc@x', 'SUMMARY:Team sync', 'LOCATION:Berlin',
    'DESCRIPTION:Weekly\\, all hands', 'DTSTART:20260805T090000', 'DTEND:20260805T093000',
    'RRULE:FREQ=WEEKLY;BYDAY=WE;COUNT=4', 'EXDATE;VALUE=DATE:20260812',
    'BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER:-PT15M', 'END:VALARM',
    'END:VEVENT',
    'BEGIN:VEVENT', 'UID:d@x', 'SUMMARY:Holiday', 'DTSTART;VALUE=DATE:20261225', 'DTEND;VALUE=DATE:20261226', 'END:VEVENT',
    'END:VCALENDAR',
].join('\r\n');

describe('ical', () => {
    it('parses VEVENTs including rrule, exdate, alarm, all-day', () => {
        const evs = parseIcs(SAMPLE);
        expect(evs).toHaveLength(2);
        const [a, b] = evs;
        expect(a.title).toBe('Team sync');
        expect(a.location.label).toBe('Berlin');
        expect(a.description).toBe('Weekly, all hands');
        expect(a.start).toBe('2026-08-05T09:00');
        expect(a.end).toBe('2026-08-05T09:30');
        expect(a.rrule).toBe('FREQ=WEEKLY;BYDAY=WE;COUNT=4');
        expect(a.exdates).toEqual(['2026-08-12']);
        expect(a.reminders).toEqual([{ minutesBefore: 15, method: 'local' }]);
        expect(b.allDay).toBe(true);
        expect(b.start).toBe('2026-12-25');
    });

    it('converts a UTC (Z) DTSTART to local wall-clock', () => {
        const evs = parseIcs('BEGIN:VEVENT\r\nSUMMARY:X\r\nDTSTART:20260805T070000Z\r\nEND:VEVENT');
        // The parsed local time depends on the test TZ, but the format must be timed.
        expect(evs[0].allDay).toBeFalsy();
        expect(evs[0].start).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
    });

    it('unfolds folded lines', () => {
        const folded = 'BEGIN:VEVENT\r\nSUMMARY:Hello \r\n World\r\nDTSTART:20260805T090000\r\nEND:VEVENT';
        expect(parseIcs(folded)[0].title).toBe('Hello World');
    });

    it('round-trips build → parse', () => {
        const ev = { id: 'e1', title: 'Lunch, again', start: '2026-08-05T12:00', end: '2026-08-05T13:00', allDay: false, rrule: 'FREQ=DAILY;COUNT=3', exdates: ['2026-08-06'], location: { label: 'Cafe' }, description: 'yum', reminders: [{ minutesBefore: 10 }] };
        const ics = buildIcs([ev], 'Mine');
        expect(ics).toContain('X-WR-CALNAME:Mine');
        const back = parseIcs(ics)[0];
        expect(back.title).toBe('Lunch, again');
        expect(back.start).toBe('2026-08-05T12:00');
        expect(back.rrule).toBe('FREQ=DAILY;COUNT=3');
        expect(back.exdates).toEqual(['2026-08-06']);
        expect(back.location.label).toBe('Cafe');
        expect(back.reminders).toEqual([{ minutesBefore: 10, method: 'local' }]);
    });

    it('exports all-day with VALUE=DATE', () => {
        const ics = buildIcs([{ id: 'h', title: 'Hol', start: '2026-12-25', end: '2026-12-26', allDay: true }]);
        expect(ics).toContain('DTSTART;VALUE=DATE:20261225');
    });
});
