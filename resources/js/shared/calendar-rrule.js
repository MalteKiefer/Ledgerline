// Recurrence expansion for calendar events (ZK, client-side). Wraps the vendored
// rrule.js. rrule treats DTSTART as "floating": occurrence Dates carry the
// wall-clock in their UTC components, so we build DTSTART from the event's local
// wall-clock and read occurrences back via UTC getters. Everything is pure and
// Vitest-tested; no timezone conversion is applied (wall-clock in = wall-clock out).
import { rrulestr } from '../vendor/rrule.min.mjs';

const WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
export const RRULE_FREQS = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
export const RRULE_WEEKDAYS = WEEKDAYS;

function pad(n) { return String(n).padStart(2, '0'); }

// iso (yyyy-mm-dd or yyyy-mm-ddThh:mm[:ss]) → { y,m,d,hh,mm,ss }.
function parts(iso) {
    const [date, time = ''] = String(iso).split('T');
    const [y, m, d] = date.split('-').map(Number);
    const [hh = 0, mm = 0, ss = 0] = time.split(':').map(Number);
    return { y, m, d, hh, mm, ss };
}

// A UTC Date whose UTC components equal the iso's wall-clock (rrule's floating basis).
function floatingUtc(iso) {
    const p = parts(iso);
    return new Date(Date.UTC(p.y, p.m - 1, p.d, p.hh, p.mm, p.ss));
}

// DTSTART value string for an event (floating): YYYYMMDD (all-day) or YYYYMMDDTHHMMSS.
function dtstartValue(ev) {
    const p = parts(ev.start);
    const date = `${p.y}${pad(p.m)}${pad(p.d)}`;
    return ev.allDay ? date : `${date}T${pad(p.hh)}${pad(p.mm)}${pad(p.ss)}`;
}

// Occurrence Date (UTC-floating) → wall-clock iso string.
function occIso(occ, allDay) {
    const y = occ.getUTCFullYear();
    const m = pad(occ.getUTCMonth() + 1);
    const d = pad(occ.getUTCDate());
    if (allDay) return `${y}-${m}-${d}`;
    return `${y}-${m}-${d}T${pad(occ.getUTCHours())}:${pad(occ.getUTCMinutes())}`;
}

// Milliseconds between an event's start and end (from the wall-clock strings).
function durationMs(ev) {
    const s = floatingUtc(ev.start).getTime();
    const e = floatingUtc(ev.end || ev.start).getTime();
    const dur = e - s;
    return dur > 0 ? dur : (ev.allDay ? 86_400_000 : 3_600_000);
}

// Expand a recurring event into concrete occurrences intersecting [rangeStartIso,
// rangeEndIso] (both yyyy-mm-dd). Returns synthetic event objects that carry the
// occurrence's own start/end and a `recurrenceId` (the occurrence date) + `_base`
// (the master event id). EXDATE dates are skipped. Non-recurring events → [].
export function expandEvent(ev, rangeStartIso, rangeEndIso) {
    if (!ev || !ev.rrule) return [];
    let rule;
    try {
        rule = rrulestr(`DTSTART:${dtstartValue(ev)}\nRRULE:${ev.rrule}`);
    } catch {
        return [];
    }
    const after = floatingUtc(`${rangeStartIso}T00:00:00`);
    const before = floatingUtc(`${rangeEndIso}T23:59:59`);
    let occs;
    try {
        occs = rule.between(after, before, true);
    } catch {
        return [];
    }
    const ex = new Set((ev.exdates || []).map((x) => String(x).slice(0, 10)));
    const dur = durationMs(ev);
    const out = [];
    for (const occ of occs) {
        const startIso = occIso(occ, ev.allDay);
        const dayIso = startIso.slice(0, 10);
        if (ex.has(dayIso)) continue;
        const endIso = occIso(new Date(occ.getTime() + dur), ev.allDay);
        out.push({ ...ev, start: startIso, end: endIso, _base: ev.id, recurrenceId: dayIso });
    }
    return out;
}

// Build an RRULE string from editor options. Returns '' for freq 'none'.
export function buildRRuleString({ freq, interval = 1, byday = [], ends = 'never', count = 1, until = '' }) {
    if (!freq || freq === 'none') return '';
    const bits = [`FREQ=${freq}`];
    if (interval && interval > 1) bits.push(`INTERVAL=${interval}`);
    if (freq === 'WEEKLY' && byday.length) bits.push(`BYDAY=${byday.join(',')}`);
    if (ends === 'count' && count > 0) bits.push(`COUNT=${count}`);
    if (ends === 'until' && until) bits.push(`UNTIL=${until.replace(/-/g, '')}T235959Z`);
    return bits.join(';');
}

// Parse an RRULE string back into editor options (best-effort).
export function parseRRuleString(str) {
    const out = { freq: 'none', interval: 1, byday: [], ends: 'never', count: 1, until: '' };
    if (!str) return out;
    for (const part of str.split(';')) {
        const [k, v] = part.split('=');
        if (k === 'FREQ') out.freq = v;
        else if (k === 'INTERVAL') out.interval = Number(v) || 1;
        else if (k === 'BYDAY') out.byday = v.split(',').filter(Boolean);
        else if (k === 'COUNT') { out.ends = 'count'; out.count = Number(v) || 1; }
        else if (k === 'UNTIL') { out.ends = 'until'; out.until = `${v.slice(0, 4)}-${v.slice(4, 6)}-${v.slice(6, 8)}`; }
    }
    return out;
}

// Human summary of a recurrence for display (uses provided label fns).
export function rruleSummary(str, t) {
    if (!str) return '';
    const o = parseRRuleString(str);
    const freq = (t.freq && t.freq[o.freq.toLowerCase()]) || o.freq;
    let s = o.interval > 1 ? `${t.every || 'every'} ${o.interval} ${freq}` : freq;
    if (o.freq === 'WEEKLY' && o.byday.length) s += ` (${o.byday.join(', ')})`;
    if (o.ends === 'count') s += ` · ${o.count}×`;
    if (o.ends === 'until') s += ` · → ${o.until}`;
    return s;
}
