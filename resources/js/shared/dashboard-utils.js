// Pure widget logic for the dashboard — no DOM, no store access, fully testable.
// Dates are handled as calendar Y-M-D strings to avoid timezone drift.

function ymd(d) { return typeof d === 'string' ? d.slice(0, 10) : ''; }
function md(d) { const s = ymd(d); return s ? s.slice(5) : ''; } // 'MM-DD'

// Days from today (YYYY-MM-DD) until the next occurrence of monthDay ('MM-DD').
export function daysUntil(monthDay, todayYmd) {
    if (!/^\d{2}-\d{2}$/.test(monthDay || '')) return null;
    const [ty, tm, td] = todayYmd.split('-').map(Number);
    const [mm, dd] = monthDay.split('-').map(Number);
    const isLeap = (y) => (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;
    const clamp = (y) => (mm === 2 && dd === 29 && !isLeap(y)) ? 28 : dd;
    const today = Date.UTC(ty, tm - 1, td);
    let next = Date.UTC(ty, mm - 1, clamp(ty));
    if (next < today) next = Date.UTC(ty + 1, mm - 1, clamp(ty + 1));
    return Math.round((next - today) / 86400000);
}

function nextOccurrenceYear(monthDay, todayYmd) {
    const [ty, tm, td] = todayYmd.split('-').map(Number);
    const [mm, dd] = monthDay.split('-').map(Number);
    const today = Date.UTC(ty, tm - 1, td);
    const thisYear = Date.UTC(ty, mm - 1, dd);
    return thisYear < today ? ty + 1 : ty;
}

// Age turned on the NEXT occurrence of the birthday. Null if no 4-digit year.
export function computeAge(bday, todayYmd) {
    const s = ymd(bday);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return null;
    const birthYear = Number(s.slice(0, 4));
    return nextOccurrenceYear(s.slice(5), todayYmd) - birthYear;
}

export function upcomingBirthdays(contacts, todayYmd, withinDays) {
    const out = [];
    for (const c of contacts || []) {
        for (const [field, kind] of [['bday', 'birthday'], ['anniversary', 'anniversary']]) {
            const date = c[field];
            const m = md(date);
            if (!/^\d{2}-\d{2}$/.test(m)) continue;
            const inDays = daysUntil(m, todayYmd);
            if (inDays == null || inDays > withinDays) continue;
            out.push({
                id: c.id,
                name: c.displayName || c.name || c.fn || '',
                kind, date, in: inDays,
                turning: computeAge(date, todayYmd),
            });
        }
    }
    return out.sort((a, b) => a.in - b.in);
}

export function yearsAgoPhotos(photos, todayYmd) {
    const tm = todayYmd.slice(5); // 'MM-DD'
    const ty = Number(todayYmd.slice(0, 4));
    const groups = new Map(); // yearsAgo -> [photos]
    for (const p of photos || []) {
        const d = ymd(p.taken_at || p.created);
        if (!d || d.slice(5) !== tm) continue;
        const y = Number(d.slice(0, 4));
        if (!y || y >= ty) continue;
        const ago = ty - y;
        if (!groups.has(ago)) groups.set(ago, []);
        groups.get(ago).push(p);
    }
    return [...groups.entries()].sort((a, b) => a[0] - b[0]).map(([yearsAgo, ps]) => ({ yearsAgo, photos: ps }));
}

export function sortTodos(todos, nowYmd) {
    const open = (todos || []).filter((t) => !t.done);
    const key = (t) => {
        const due = ymd(t.due);
        if (!due) return [2, '￿'];        // undated last
        return [due < nowYmd ? 0 : 1, due];   // overdue(0) then upcoming(1), by date
    };
    return open.map((t, i) => [t, i]).sort((a, b) => {
        const ka = key(a[0]), kb = key(b[0]);
        return ka[0] - kb[0] || (ka[1] < kb[1] ? -1 : ka[1] > kb[1] ? 1 : a[1] - b[1]);
    }).map(([t]) => t);
}
