// Virtual (read-only) calendar feeds — birthdays from ZK contacts + public
// holidays. These events are GENERATED for the visible range, never stored. Pure +
// Vitest. The component merges them into the grid; they are not editable.
import { computeHolidays } from './holidays.js';

// Extract MM-DD from a 'yyyy-mm-dd' or 'mm-dd' string; '' if not parseable.
function monthDay(date) {
    const s = String(date || '');
    let m = s.match(/^\d{4}-(\d{2})-(\d{2})$/);
    if (m) return `${m[1]}-${m[2]}`;
    m = s.match(/^(\d{2})-(\d{2})$/);
    return m ? `${m[1]}-${m[2]}` : '';
}
function birthYear(date) {
    const m = String(date || '').match(/^(\d{4})-\d{2}-\d{2}$/);
    return m ? Number(m[1]) : null;
}
function contactName(c) { return c.displayName || c.name || c.fn || ''; }

// Birthday + anniversary all-day events for every year in [startYear, endYear].
// The title is just the contact's NAME (no age); details (kind, age, original
// date, contact id) ride on the event for the read-only detail popup.
export function birthdayEvents(contacts, startYear, endYear) {
    const out = [];
    for (const c of contacts || []) {
        for (const [field, kind] of [['bday', 'birthday'], ['anniversary', 'anniversary']]) {
            const md = monthDay(c[field]);
            if (!/^\d{2}-\d{2}$/.test(md)) continue;
            const name = contactName(c);
            if (!name) continue;
            const by = birthYear(c[field]);
            for (let y = startYear; y <= endYear; y++) {
                const date = `${y}-${md}`;
                out.push({
                    id: `bday-${c.id}-${field}-${y}`, calendarId: 'birthdays', title: name,
                    allDay: true, start: date, end: date, virtual: true, feed: 'birthdays',
                    contactId: c.id, kind, name, bday: c[field], age: by ? y - by : null,
                });
            }
        }
    }
    return out;
}

// Public-holiday all-day events for a country across [startYear, endYear].
export function holidayEvents(country, startYear, endYear) {
    const out = [];
    for (let y = startYear; y <= endYear; y++) {
        for (const h of computeHolidays(country, y)) {
            out.push({ id: `hol-${country}-${h.date}`, calendarId: 'holidays', title: h.name, allDay: true, start: h.date, end: h.date, virtual: true, feed: 'holidays' });
        }
    }
    return out;
}

// Year span covering an iso range (inclusive).
export function yearsInRange(startIso, endIso) {
    const a = Number(String(startIso).slice(0, 4));
    const b = Number(String(endIso).slice(0, 4));
    return { start: Math.min(a, b), end: Math.max(a, b) };
}

// Colours for the virtual calendars (used when calColor can't find a real one).
export const FEED_COLORS = { birthdays: '#d1607e', holidays: '#59ad6b' };
export const FEED_ICONS = { birthdays: 'cake', holidays: 'sparkles' };
