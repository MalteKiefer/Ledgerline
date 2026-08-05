// Reminder computation (ZK, client-side). Expands recurring events over a horizon
// and yields the concrete reminder fire times. Reused by the local notification
// scheduler (slice 3) and the opaque server-push registration (slice 4). Pure +
// Vitest-tested. The server never sees any of this except (later) an opaque
// timestamp per reminder.
import { expandEvent } from './calendar-rrule.js';

// Reminder presets offered in the UI (minutes before the event start).
export const REMINDER_PRESETS = [0, 5, 10, 15, 30, 60, 120, 1440];

// Local-wall-clock ISO → ms (floating; matches how events are stored/displayed).
export function isoToMs(iso) {
    if (!iso) return 0;
    const [date, time = '00:00'] = String(iso).split('T');
    const [y, m, d] = date.split('-').map(Number);
    const [hh = 0, mm = 0] = time.split(':').map(Number);
    return new Date(y, (m || 1) - 1, d || 1, hh, mm, 0, 0).getTime();
}

export function reminderFireMs(startIso, minutesBefore) {
    return isoToMs(startIso) - (Number(minutesBefore) || 0) * 60_000;
}

// A stable key for a fired/registered reminder (dedupes across scans).
export function reminderKey(eventId, recurrenceId, minutesBefore) {
    return `${eventId}:${recurrenceId || ''}:${minutesBefore}`;
}

// Every reminder firing in [fromMs, toMs], across single + recurring events.
// Each entry: { key, fireMs, eventId, recurrenceId, minutesBefore, title, start }.
export function collectReminders(events, fromMs, toMs) {
    const out = [];
    const rangeStart = ymdOf(fromMs - 86_400_000);
    const rangeEnd = ymdOf(toMs + 86_400_000);
    for (const ev of events || []) {
        if (ev.status === 'cancelled') continue;
        const reminders = Array.isArray(ev.reminders) ? ev.reminders : [];
        if (reminders.length === 0) continue;
        const occurrences = ev.rrule ? expandEvent(ev, rangeStart, rangeEnd) : [ev];
        for (const occ of occurrences) {
            const rid = occ.recurrenceId || (occ.start || '').slice(0, 10);
            for (const r of reminders) {
                const fireMs = reminderFireMs(occ.start, r.minutesBefore);
                if (fireMs < fromMs || fireMs > toMs) continue;
                out.push({
                    key: reminderKey(occ._base || ev.id, rid, r.minutesBefore),
                    fireMs,
                    eventId: occ._base || ev.id,
                    recurrenceId: rid,
                    minutesBefore: r.minutesBefore,
                    method: r.method || 'local',
                    title: ev.title || '',
                    start: occ.start,
                });
            }
        }
    }
    return out.sort((a, b) => a.fireMs - b.fireMs);
}

function ymdOf(ms) {
    const d = new Date(ms);
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
}
