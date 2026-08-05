import { describe, it, expect } from 'vitest';
import { computeHolidays, easterSunday } from '../shared/holidays.js';
import { birthdayEvents, holidayEvents, yearsInRange } from '../shared/calendar-feeds.js';

describe('holidays', () => {
    it('computes Easter Sunday (known values)', () => {
        expect(easterSunday(2026)).toEqual({ m: 4, d: 5 });
        expect(easterSunday(2024)).toEqual({ m: 3, d: 31 });
    });

    it('DE public holidays include fixed + Easter-relative', () => {
        const h = computeHolidays('DE', 2026);
        const byName = Object.fromEntries(h.map((x) => [x.name, x.date]));
        expect(byName['Neujahr']).toBe('2026-01-01');
        expect(byName['Tag der Deutschen Einheit']).toBe('2026-10-03');
        expect(byName['Karfreitag']).toBe('2026-04-03'); // Easter -2
        expect(byName['Ostermontag']).toBe('2026-04-06'); // Easter +1
    });

    it('US uses nth-weekday rules', () => {
        const byName = Object.fromEntries(computeHolidays('US', 2026).map((x) => [x.name, x.date]));
        expect(byName['Thanksgiving']).toBe('2026-11-26'); // 4th Thursday
        expect(byName['Memorial Day']).toBe('2026-05-25'); // last Monday
    });
});

describe('calendar-feeds', () => {
    it('yearsInRange', () => {
        expect(yearsInRange('2026-12-20', '2027-01-05')).toEqual({ start: 2026, end: 2027 });
    });

    it('birthday events per year: title is the name, age on the event', () => {
        const contacts = [{ id: 'c1', name: 'Ada', bday: '1990-08-05' }, { id: 'c2', fn: 'No date' }];
        const evs = birthdayEvents(contacts, 2026, 2026);
        expect(evs).toHaveLength(1);
        expect(evs[0]).toMatchObject({ start: '2026-08-05', allDay: true, virtual: true, feed: 'birthdays', title: 'Ada', age: 36, contactId: 'c1', kind: 'birthday' });
    });

    it('holiday events across a range', () => {
        const evs = holidayEvents('DE', 2026, 2026);
        expect(evs.every((e) => e.virtual && e.feed === 'holidays' && e.allDay)).toBe(true);
        expect(evs.find((e) => e.title === 'Neujahr').start).toBe('2026-01-01');
    });
});
