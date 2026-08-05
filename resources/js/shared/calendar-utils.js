// Pure calendar helpers (ZK client-side). Month-grid layout + day/event overlap.
// Slice 1: single (non-recurring) events. Recurrence expansion is layered on in a
// later slice (shared/calendar-rrule.js) and feeds occurrences through the same
// eventsOnDay overlap test.

// yyyy-mm-dd for a Date (local time).
export function ymd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

// Local Date at 00:00 for a yyyy-mm-dd string.
export function dayStart(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d, 0, 0, 0, 0);
}

// Month matrix: 6 weeks × 7 days covering `month` (0-based) of `year`, aligned so
// each week starts on weekStartsOn (1 = Monday). Each cell = { iso, day, inMonth,
// isToday }. todayIso lets callers inject "today" (tests are deterministic; the
// runtime passes ymd(new Date())).
export function monthMatrix(year, month, todayIso, weekStartsOn = 1) {
    const first = new Date(year, month, 1);
    // Days to step back so the grid starts on weekStartsOn.
    const offset = (first.getDay() - weekStartsOn + 7) % 7;
    const start = new Date(year, month, 1 - offset);
    const weeks = [];
    const cur = new Date(start);
    for (let w = 0; w < 6; w++) {
        const week = [];
        for (let i = 0; i < 7; i++) {
            const iso = ymd(cur);
            week.push({ iso, day: cur.getDate(), inMonth: cur.getMonth() === month, isToday: iso === todayIso });
            cur.setDate(cur.getDate() + 1);
        }
        weeks.push(week);
    }
    return weeks;
}

// Does an event intersect the local day [dayStart, dayEnd)? Works for all-day and
// timed events. `ev.start`/`ev.end` are ISO strings (allDay uses yyyy-mm-dd; timed
// uses a full datetime). end is exclusive-ish but we treat an event touching the
// day as present.
export function overlapsDay(ev, iso) {
    const ds = dayStart(iso).getTime();
    const de = ds + 86_400_000;
    const s = eventStartMs(ev);
    const e = eventEndMs(ev);
    return s < de && e > ds;
}

export function eventStartMs(ev) {
    if (ev.allDay) return dayStart((ev.start || '').slice(0, 10)).getTime();
    return Date.parse(ev.start) || 0;
}

export function eventEndMs(ev) {
    if (ev.allDay) {
        const endIso = (ev.end || ev.start || '').slice(0, 10);
        // All-day end is inclusive of that date → extend to end of that day.
        return dayStart(endIso).getTime() + 86_400_000;
    }
    return Date.parse(ev.end || ev.start) || (Date.parse(ev.start) || 0);
}

// Events that intersect a given day, sorted: all-day first, then by start time.
export function eventsOnDay(events, iso) {
    return (events || [])
        .filter((ev) => ev && ev.status !== 'cancelled' && overlapsDay(ev, iso))
        .sort((a, b) => {
            if (!!a.allDay !== !!b.allDay) return a.allDay ? -1 : 1;
            return eventStartMs(a) - eventStartMs(b);
        });
}

// Short HH:MM label for a timed event's start (local). '' for all-day.
export function timeLabel(ev) {
    if (ev.allDay) return '';
    const d = new Date(ev.start);
    if (Number.isNaN(d.getTime())) return '';
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

export const MONTH_KEYS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
export const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

// A palette of calendar colours (accent-friendly, distinct hues).
export const CALENDAR_COLORS = ['#7066f5', '#3b9fd6', '#59ad6b', '#e2915a', '#d9a441', '#3fae9f', '#9e70fa', '#d1607e', '#6b7280'];
