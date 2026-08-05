// Public-holiday computation (client-side, no dependency). Easter via the
// Meeus/Jones/Butcher algorithm; fixed + Easter-relative + nth-weekday dates per
// country. Pure + Vitest. Returns [{ date:'yyyy-mm-dd', name }] for a year.
export const HOLIDAY_COUNTRIES = ['DE', 'AT', 'CH', 'GB', 'US'];

function pad(n) { return String(n).padStart(2, '0'); }
function iso(y, m, d) { return `${y}-${pad(m)}-${pad(d)}`; }

// Gregorian Easter Sunday for a year → { m, d }.
export function easterSunday(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;
    return { m: month, d: day };
}

// A date offset `days` from Easter Sunday, as yyyy-mm-dd.
function easterOffset(year, days) {
    const e = easterSunday(year);
    const base = new Date(year, e.m - 1, e.d);
    base.setDate(base.getDate() + days);
    return iso(base.getFullYear(), base.getMonth() + 1, base.getDate());
}

// The nth (1-based; -1 = last) `weekday` (0=Sun..6=Sat) of month `m` in `year`.
function nthWeekday(year, m, weekday, nth) {
    if (nth === -1) {
        const last = new Date(year, m, 0); // last day of month m
        const back = (last.getDay() - weekday + 7) % 7;
        last.setDate(last.getDate() - back);
        return iso(last.getFullYear(), last.getMonth() + 1, last.getDate());
    }
    const first = new Date(year, m - 1, 1);
    const fwd = (weekday - first.getDay() + 7) % 7;
    const day = 1 + fwd + (nth - 1) * 7;
    return iso(year, m, day);
}

const NEW_YEAR = { m: 1, d: 1 };

function build(year, defs) {
    return defs.map((def) => {
        let date;
        if (def.fixed) date = iso(year, def.fixed[0], def.fixed[1]);
        else if (typeof def.easter === 'number') date = easterOffset(year, def.easter);
        else if (def.nth) date = nthWeekday(year, def.nth[0], def.nth[1], def.nth[2]);
        return { date, name: def.name };
    }).filter((h) => h.date);
}

const CATALOG = {
    DE: [
        { fixed: [NEW_YEAR.m, NEW_YEAR.d], name: 'Neujahr' },
        { easter: -2, name: 'Karfreitag' },
        { easter: 1, name: 'Ostermontag' },
        { fixed: [5, 1], name: 'Tag der Arbeit' },
        { easter: 39, name: 'Christi Himmelfahrt' },
        { easter: 50, name: 'Pfingstmontag' },
        { fixed: [10, 3], name: 'Tag der Deutschen Einheit' },
        { fixed: [12, 25], name: '1. Weihnachtstag' },
        { fixed: [12, 26], name: '2. Weihnachtstag' },
    ],
    AT: [
        { fixed: [1, 1], name: 'Neujahr' },
        { fixed: [1, 6], name: 'Heilige Drei Könige' },
        { easter: 1, name: 'Ostermontag' },
        { fixed: [5, 1], name: 'Staatsfeiertag' },
        { easter: 39, name: 'Christi Himmelfahrt' },
        { easter: 50, name: 'Pfingstmontag' },
        { easter: 60, name: 'Fronleichnam' },
        { fixed: [8, 15], name: 'Mariä Himmelfahrt' },
        { fixed: [10, 26], name: 'Nationalfeiertag' },
        { fixed: [11, 1], name: 'Allerheiligen' },
        { fixed: [12, 8], name: 'Mariä Empfängnis' },
        { fixed: [12, 25], name: 'Christtag' },
        { fixed: [12, 26], name: 'Stefanitag' },
    ],
    CH: [
        { fixed: [1, 1], name: 'Neujahr' },
        { easter: -2, name: 'Karfreitag' },
        { easter: 1, name: 'Ostermontag' },
        { easter: 39, name: 'Auffahrt' },
        { easter: 50, name: 'Pfingstmontag' },
        { fixed: [8, 1], name: 'Bundesfeier' },
        { fixed: [12, 25], name: 'Weihnachten' },
    ],
    GB: [
        { fixed: [1, 1], name: "New Year's Day" },
        { easter: -2, name: 'Good Friday' },
        { easter: 1, name: 'Easter Monday' },
        { fixed: [12, 25], name: 'Christmas Day' },
        { fixed: [12, 26], name: 'Boxing Day' },
    ],
    US: [
        { fixed: [1, 1], name: "New Year's Day" },
        { nth: [1, 1, 3], name: 'Martin Luther King Jr. Day' },
        { nth: [5, 1, -1], name: 'Memorial Day' },
        { fixed: [7, 4], name: 'Independence Day' },
        { nth: [9, 1, 1], name: 'Labor Day' },
        { nth: [11, 4, 4], name: 'Thanksgiving' },
        { fixed: [12, 25], name: 'Christmas Day' },
    ],
};

export function computeHolidays(country, year) {
    const defs = CATALOG[country];
    return defs ? build(year, defs) : [];
}
