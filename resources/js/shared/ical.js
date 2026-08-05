// iCalendar (RFC 5545) import/export for the ZK calendar — pure, client-side, and
// Vitest-tested. The .ics never touches the server; import/export happen entirely
// in the browser. Times are treated as floating wall-clock (matching how events
// are stored/displayed); UTC (Z) values are converted to local wall-clock, TZID is
// taken as-is (wall-clock), which is good enough for round-tripping Google/Apple.

function pad(n) { return String(n).padStart(2, '0'); }

// Unfold RFC 5545 folded lines (a CRLF followed by space/tab continues the line).
function unfold(text) {
    return String(text).replace(/\r\n[ \t]/g, '').replace(/\n[ \t]/g, '');
}

// Split "NAME;PARAM=x:value" into { name, params:{PARAM:'x'}, value }.
function parseLine(line) {
    const colon = line.indexOf(':');
    if (colon < 0) return null;
    const left = line.slice(0, colon);
    const value = line.slice(colon + 1);
    const [name, ...paramParts] = left.split(';');
    const params = {};
    for (const p of paramParts) {
        const eq = p.indexOf('=');
        if (eq > 0) params[p.slice(0, eq).toUpperCase()] = p.slice(eq + 1);
    }
    return { name: name.toUpperCase(), params, value };
}

function unescapeText(v) {
    return String(v).replace(/\\n/gi, '\n').replace(/\\,/g, ',').replace(/\\;/g, ';').replace(/\\\\/g, '\\');
}
function escapeText(v) {
    return String(v == null ? '' : v).replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
}

// A DTSTART/DTEND value + params → { iso, allDay }. iso is yyyy-mm-dd (all-day) or
// yyyy-mm-ddThh:mm (timed, local wall-clock).
function parseDateValue(value, params) {
    const isDate = (params.VALUE || '').toUpperCase() === 'DATE' || /^\d{8}$/.test(value);
    if (isDate) {
        const m = value.match(/^(\d{4})(\d{2})(\d{2})/);
        return m ? { iso: `${m[1]}-${m[2]}-${m[3]}`, allDay: true } : null;
    }
    const m = value.match(/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?(Z)?$/);
    if (!m) return null;
    let [, y, mo, d, hh, mm, , z] = m;
    if (z === 'Z') {
        // UTC → local wall-clock components.
        const dt = new Date(Date.UTC(+y, +mo - 1, +d, +hh, +mm, 0));
        y = dt.getFullYear(); mo = pad(dt.getMonth() + 1); d = pad(dt.getDate());
        hh = pad(dt.getHours()); mm = pad(dt.getMinutes());
    }
    return { iso: `${y}-${mo}-${d}T${hh}:${mm}`, allDay: false };
}

// TRIGGER like -PT15M / -PT1H / -P1D / PT0S → minutesBefore.
function parseTrigger(value) {
    const neg = value.startsWith('-');
    const m = value.match(/P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?/);
    if (!m) return null;
    const days = +(m[1] || 0), hours = +(m[2] || 0), mins = +(m[3] || 0);
    const total = days * 1440 + hours * 60 + mins;
    return neg || total === 0 ? total : total; // "before" is the only sensible case
}

// Parse an .ics string into event records (partial — the importer assigns id +
// calendarId). Returns [{ title, description, location, allDay, start, end, rrule,
// exdates, reminders, uid }].
export function parseIcs(text) {
    const lines = unfold(text).split(/\r?\n/);
    const events = [];
    let cur = null;
    let inAlarm = false;
    let alarmTrigger = null;
    for (const raw of lines) {
        const line = parseLine(raw);
        if (!line) continue;
        if (line.name === 'BEGIN' && line.value === 'VEVENT') { cur = { exdates: [], reminders: [] }; continue; }
        if (line.name === 'END' && line.value === 'VEVENT') {
            if (cur) {
                if (!cur.end) cur.end = cur.start;
                events.push(cur);
            }
            cur = null; continue;
        }
        if (!cur) continue;
        if (line.name === 'BEGIN' && line.value === 'VALARM') { inAlarm = true; alarmTrigger = null; continue; }
        if (line.name === 'END' && line.value === 'VALARM') {
            if (alarmTrigger != null) cur.reminders.push({ minutesBefore: alarmTrigger, method: 'local' });
            inAlarm = false; continue;
        }
        if (inAlarm) {
            if (line.name === 'TRIGGER') alarmTrigger = parseTrigger(line.value);
            continue;
        }
        switch (line.name) {
            case 'SUMMARY': cur.title = unescapeText(line.value); break;
            case 'DESCRIPTION': cur.description = unescapeText(line.value); break;
            case 'LOCATION': { const l = unescapeText(line.value); cur.location = l ? { label: l, lat: null, lng: null } : null; break; }
            case 'UID': cur.uid = line.value; break;
            case 'RRULE': cur.rrule = line.value.trim(); break;
            case 'DTSTART': { const d = parseDateValue(line.value, line.params); if (d) { cur.start = d.iso; cur.allDay = d.allDay; } break; }
            case 'DTEND': { const d = parseDateValue(line.value, line.params); if (d) cur.end = d.iso; break; }
            case 'EXDATE': for (const v of line.value.split(',')) { const d = parseDateValue(v, line.params); if (d) cur.exdates.push(d.iso.slice(0, 10)); } break;
            default: break;
        }
    }
    return events.filter((e) => e.title || e.start);
}

// ---- export ----
function foldLine(line) {
    // RFC 5545: octet-based 75-char fold. Simple char-based fold is fine here.
    if (line.length <= 75) return line;
    let out = line.slice(0, 75);
    let rest = line.slice(75);
    while (rest.length > 74) { out += '\r\n ' + rest.slice(0, 74); rest = rest.slice(74); }
    return out + '\r\n ' + rest;
}

function icsDate(iso, allDay) {
    if (allDay) { const [y, m, d] = iso.slice(0, 10).split('-'); return { prop: ';VALUE=DATE', val: `${y}${m}${d}` }; }
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
    if (!m) { const [y, mo, d] = iso.slice(0, 10).split('-'); return { prop: ';VALUE=DATE', val: `${y}${mo}${d}` }; }
    return { prop: '', val: `${m[1]}${m[2]}${m[3]}T${m[4]}${m[5]}00` };
}

function triggerFor(minutesBefore) {
    const m = Number(minutesBefore) || 0;
    if (m === 0) return 'PT0M';
    if (m % 1440 === 0) return `-P${m / 1440}D`;
    if (m % 60 === 0) return `-PT${m / 60}H`;
    return `-PT${m}M`;
}

// Serialize events to a VCALENDAR string (CRLF line endings). Each event → VEVENT
// with DTSTART/DTEND, optional RRULE/EXDATE/LOCATION/DESCRIPTION and a VALARM per
// reminder.
export function buildIcs(events, calendarName = 'Ledgerline') {
    const out = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Ledgerline//Calendar//EN', 'CALSCALE:GREGORIAN', `X-WR-CALNAME:${escapeText(calendarName)}`];
    for (const ev of events || []) {
        const s = icsDate(ev.start, ev.allDay);
        const e = icsDate(ev.end || ev.start, ev.allDay);
        out.push('BEGIN:VEVENT');
        out.push(`UID:${ev.uid || ev.id || Math.abs(hash(ev.title + ev.start))}@ledgerline`);
        out.push(foldLine(`SUMMARY:${escapeText(ev.title || '')}`));
        out.push(`DTSTART${s.prop}:${s.val}`);
        out.push(`DTEND${e.prop}:${e.val}`);
        if (ev.rrule) out.push(`RRULE:${ev.rrule}`);
        if (Array.isArray(ev.exdates) && ev.exdates.length) {
            out.push(`EXDATE;VALUE=DATE:${ev.exdates.map((d) => d.replace(/-/g, '')).join(',')}`);
        }
        if (ev.location && ev.location.label) out.push(foldLine(`LOCATION:${escapeText(ev.location.label)}`));
        if (ev.description) out.push(foldLine(`DESCRIPTION:${escapeText(ev.description)}`));
        for (const r of (ev.reminders || [])) {
            out.push('BEGIN:VALARM', 'ACTION:DISPLAY', `TRIGGER:${triggerFor(r.minutesBefore)}`, `DESCRIPTION:${escapeText(ev.title || 'Reminder')}`, 'END:VALARM');
        }
        out.push('END:VEVENT');
    }
    out.push('END:VCALENDAR');
    return out.join('\r\n') + '\r\n';
}

function hash(s) {
    let h = 0;
    for (let i = 0; i < String(s).length; i++) { h = (h << 5) - h + s.charCodeAt(i); h |= 0; }
    return h;
}
