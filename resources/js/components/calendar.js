// Calendar module (ZK). Calendars + events live in the opaque per-module store
// window.LLModuleStore.calendar — the server only ever sees ciphertext. Slice 1:
// month view + day agenda + single (non-recurring) events + calendar management.
// Recurrence, reminders, OSM locations, iCal and sharing are layered on in later
// slices. Store-derived getters reference `void this._mut` because the mapped
// arrays are the (non-Alpine-reactive) store data.
import { zkModule } from '../shared/zk-module';
import { newId } from '../shared/sealed-store';
import { formatDate, saveBlobAs } from '../shared/dom';
import { parseIcs, buildIcs } from '../shared/ical';
import { getJson, postForm } from '../shared/api';
import { collectReminders, REMINDER_PRESETS } from '../shared/calendar-reminders';
import {
    ymd, dayStart, monthMatrix, eventsOnDay, timeLabel, weekNumberOf, CALENDAR_COLORS,
} from '../shared/calendar-utils';
import { CALENDAR_ICONS, calIconPath } from '../shared/calendar-icons';
import { calWeekNumbers, calWeekStart, calDefaultView, calDayStart, calDayEnd } from '../shared/prefs';
import {
    expandEvent, buildRRuleString, parseRRuleString, rruleSummary, RRULE_FREQS, RRULE_WEEKDAYS,
} from '../shared/calendar-rrule';

const BLANK_FORM = () => ({
    id: null,
    calendarId: '',
    title: '',
    description: '',
    location: '',
    locationLat: null,
    locationLng: null,
    reminders: [],
    allDay: false,
    startDate: '',
    startTime: '09:00',
    endDate: '',
    endTime: '10:00',
    // Recurrence editor state.
    repeat: 'none', // none | DAILY | WEEKLY | MONTHLY | YEARLY
    interval: 1,
    byday: [],
    ends: 'never', // never | count | until
    count: 10,
    until: '',
});

export default (labels = {}) => ({
    ...zkModule({
        store: 'calendar',
        map: { calendars: 'calendars', events: 'events' },
        afterLoad: (self, ms) => self._ensureDefault(ms),
        onLock: (self) => {
            self.editorOpen = false;
            self.editing = null;
            self.selectedDay = null;
            self.calMgrOpen = false;
            self._stopRemClock();
            self._firedReminders.clear();
        },
    }),

    labels,
    calendars: [],
    events: [],
    _mut: 0,

    // View + cursor. `anchorIso` is a date within the visible month/week/day.
    view: 'month', // month | week | day
    anchorIso: ymd(new Date()),
    todayIso: ymd(new Date()),
    get viewY() { return Number(this.anchorIso.slice(0, 4)); },
    get viewM() { return Number(this.anchorIso.slice(5, 7)) - 1; },

    selectedDay: null, // iso of the open day agenda, or null
    editorOpen: false,
    editing: null,      // the (master or single) event being edited (null = closed)
    _occRid: null,      // recurrenceId when editing one occurrence of a series
    editScope: 'all',   // 'all' | 'this' — for recurring occurrences
    _saveAttempted: false,
    freqs: RRULE_FREQS,
    weekdays: RRULE_WEEKDAYS,
    reminderPresets: REMINDER_PRESETS,
    _form: BLANK_FORM(),

    // Location search (OSM/Nominatim via the calendar-gated geocoder).
    geoResults: [],
    geoSearching: false,
    _geoTimer: null,

    // Local notification scheduler.
    _remClock: null,
    _firedReminders: new Set(),
    _lastScanMs: 0,
    _remSyncTimer: null,

    calMgrOpen: false,
    _calForm: null,    // { id, name, color, icon } or null
    colors: CALENDAR_COLORS,
    calIcons: CALENDAR_ICONS,
    calIconPath(name) { return calIconPath(name); },
    calIcon(id) {
        void this._mut;
        return (this.calendars.find((c) => c.id === id) || {}).icon || 'calendar';
    },

    async init() {
        this.view = calDefaultView();
        await this._initZk();
        if (this.state === 'ready') this._startRemClock();
        this.$watch('state', (s) => { if (s === 'ready') this._startRemClock(); else this._stopRemClock(); });
    },

    // Seed a default calendar on first use so events always have a home.
    _ensureDefault(ms) {
        const data = ms.data;
        if (!Array.isArray(data.calendars)) data.calendars = [];
        if (data.calendars.length === 0) {
            data.calendars.push({ id: newId(), name: this.labels.default_calendar || 'Personal', color: CALENDAR_COLORS[0], icon: 'calendar', isDefault: true });
            ms.touch();
        }
    },

    // ---- month grid ----
    _weekStartNum() { return calWeekStart() === 'sun' ? 0 : 1; },
    get showWeekNumbers() { return calWeekNumbers(); },
    get monthWeeks() {
        void this._mut;
        return monthMatrix(this.viewY, this.viewM, this.todayIso, this._weekStartNum());
    },
    weekNumber(week) { return week && week[0] ? weekNumberOf(week[0].iso) : ''; },
    get monthLabel() {
        return new Date(this.viewY, this.viewM, 1).toLocaleDateString(document.documentElement.lang || 'en', { month: 'long', year: 'numeric' });
    },
    get weekdayLabels() {
        // Short weekday names in the UI locale, starting on the chosen week-start.
        // Jun 1 2026 is a Monday; Jun 7 2026 is a Sunday.
        const base = new Date(2026, 5, this._weekStartNum() === 0 ? 7 : 1);
        const out = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(base);
            d.setDate(base.getDate() + i);
            out.push(d.toLocaleDateString(document.documentElement.lang || 'en', { weekday: 'short' }));
        }
        return out;
    },

    // Non-recurring events + expanded occurrences of recurring ones (minus any
    // occurrence a per-occurrence override replaces) across [rangeStart, rangeEnd].
    _expandRange(rangeStart, rangeEnd) {
        const overrides = new Set(this.events.filter((e) => e.overrideOf).map((e) => `${e.overrideOf}@${e.recurrenceId || ''}`));
        const out = [];
        for (const ev of this.events) {
            if (ev.rrule) {
                for (const occ of expandEvent(ev, rangeStart, rangeEnd)) {
                    if (!overrides.has(`${ev.id}@${occ.recurrenceId}`)) out.push(occ);
                }
            } else {
                out.push(ev);
            }
        }
        return out;
    },
    dayEvents(iso) {
        void this._mut;
        return eventsOnDay(this._expandRange(iso, iso), iso);
    },
    timedEventsForDay(iso) { return this.dayEvents(iso).filter((e) => !e.allDay); },
    allDayEventsForDay(iso) { return this.dayEvents(iso).filter((e) => e.allDay); },
    isRecurring(ev) { return !!(ev && (ev.rrule || ev._base)); },
    rruleLabel(ev) {
        void this._mut;
        const master = ev._base ? this.events.find((e) => e.id === ev._base) : ev;
        return master && master.rrule ? rruleSummary(master.rrule, this.labels.rrule || {}) : '';
    },
    timeLabel(ev) { return timeLabel(ev); },
    calColor(id) {
        void this._mut;
        return (this.calendars.find((c) => c.id === id) || {}).color || CALENDAR_COLORS[8];
    },
    calName(id) {
        void this._mut;
        return (this.calendars.find((c) => c.id === id) || {}).name || '';
    },

    _shiftAnchor(days) { const d = dayStart(this.anchorIso); d.setDate(d.getDate() + days); this.anchorIso = ymd(d); },
    prev() {
        if (this.view === 'month') { const d = dayStart(this.anchorIso); d.setMonth(d.getMonth() - 1, 1); this.anchorIso = ymd(d); }
        else this._shiftAnchor(this.view === 'week' ? -7 : -1);
    },
    next() {
        if (this.view === 'month') { const d = dayStart(this.anchorIso); d.setMonth(d.getMonth() + 1, 1); this.anchorIso = ymd(d); }
        else this._shiftAnchor(this.view === 'week' ? 7 : 1);
    },
    goToday() { this.anchorIso = this.todayIso; },
    switchView(v) { this.view = v; },

    // ---- week / day time grid ----
    get weekDays() {
        void this._mut;
        // The 7 iso days of the week containing the anchor, honouring week-start.
        const d = dayStart(this.anchorIso);
        const dow = d.getDay(); // 0=Sun..6=Sat
        const start = this._weekStartNum(); // 1=Mon,0=Sun
        const back = (dow - start + 7) % 7;
        d.setDate(d.getDate() - back);
        const out = [];
        for (let i = 0; i < 7; i++) { out.push(ymd(d)); d.setDate(d.getDate() + 1); }
        return out;
    },
    get gridDays() { return this.view === 'day' ? [this.anchorIso] : this.weekDays; },
    get gridColsStyle() { return { gridTemplateColumns: `3.5rem repeat(${this.gridDays.length}, minmax(0,1fr))` }; },
    switchToDay(iso) { this.anchorIso = iso; this.view = 'day'; },
    get gridHours() {
        const s = calDayStart(), e = Math.max(s + 1, calDayEnd());
        const out = [];
        for (let h = s; h < e; h++) out.push(h);
        return out;
    },
    get rangeLabel() {
        const loc = document.documentElement.lang || 'en';
        if (this.view === 'day') return dayStart(this.anchorIso).toLocaleDateString(loc, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const days = this.weekDays;
        const a = dayStart(days[0]), b = dayStart(days[6]);
        return `${a.toLocaleDateString(loc, { day: 'numeric', month: 'short' })} – ${b.toLocaleDateString(loc, { day: 'numeric', month: 'short', year: 'numeric' })}`;
    },
    // Weekday + day-number header for a grid column.
    gridColLabel(iso) {
        const d = dayStart(iso);
        return { wd: d.toLocaleDateString(document.documentElement.lang || 'en', { weekday: 'short' }), day: d.getDate(), iso, isToday: iso === this.todayIso };
    },
    // Absolute position of a timed event within the day column (48px per hour).
    eventStyle(ev) {
        const rowH = 48;
        const s = calDayStart();
        const sd = new Date(ev.start), ed = new Date(ev.end || ev.start);
        let startMin = (sd.getHours() * 60 + sd.getMinutes()) - s * 60;
        let endMin = (ed.getHours() * 60 + ed.getMinutes()) - s * 60;
        if (Number.isNaN(startMin)) startMin = 0;
        if (Number.isNaN(endMin) || endMin <= startMin) endMin = startMin + 30;
        const top = Math.max(0, startMin) / 60 * rowH;
        const height = Math.max(18, (endMin - Math.max(0, startMin)) / 60 * rowH);
        return { top: `${top}px`, height: `${height}px` };
    },
    // Click an empty slot → new event at that hour.
    openSlot(iso, hour) {
        this.openNew(iso);
        this._form.startTime = `${String(hour).padStart(2, '0')}:00`;
        this._form.endTime = `${String(Math.min(23, hour + 1)).padStart(2, '0')}:00`;
    },

    // ---- day agenda ----
    openDay(iso) { this.selectedDay = iso; },
    closeDay() { this.selectedDay = null; },

    // ---- event editor ----
    _defaultCalendarId() {
        const def = this.calendars.find((c) => c.isDefault) || this.calendars[0];
        return def ? def.id : '';
    },
    openNew(iso) {
        const day = iso || this.selectedDay || this.todayIso;
        this._form = { ...BLANK_FORM(), calendarId: this._defaultCalendarId(), startDate: day, endDate: day };
        this.editing = null;
        this._occRid = null;
        this.editScope = 'all';
        this._saveAttempted = false;
        this.editorOpen = true;
    },
    openEvent(ev) {
        // An expanded occurrence carries `_base`; edit the underlying master.
        const master = ev._base ? (this.events.find((e) => e.id === ev._base) || ev) : ev;
        const s = ev.allDay ? { d: (ev.start || '').slice(0, 10), t: '09:00' } : splitDt(ev.start);
        const e = ev.allDay ? { d: (ev.end || ev.start || '').slice(0, 10), t: '10:00' } : splitDt(ev.end || ev.start);
        const rr = parseRRuleString(master.rrule || '');
        this._form = {
            id: master.id,
            calendarId: ev.calendarId || this._defaultCalendarId(),
            title: ev.title || '',
            description: ev.description || '',
            location: (ev.location && ev.location.label) || '',
            locationLat: (ev.location && ev.location.lat) ?? null,
            locationLng: (ev.location && ev.location.lng) ?? null,
            reminders: Array.isArray(ev.reminders) ? ev.reminders.map((r) => ({ ...r })) : [],
            allDay: !!ev.allDay,
            startDate: s.d, startTime: s.t,
            endDate: e.d, endTime: e.t,
            repeat: rr.freq === 'none' ? 'none' : rr.freq,
            interval: rr.interval, byday: rr.byday, ends: rr.ends, count: rr.count, until: rr.until,
        };
        this.editing = master;
        this._occRid = ev._base ? (ev.recurrenceId || ev.start.slice(0, 10)) : null;
        this.editScope = this._occRid ? 'this' : 'all';
        this._saveAttempted = false;
        this.editorOpen = true;
    },
    toggleByday(wd) {
        const i = this._form.byday.indexOf(wd);
        if (i >= 0) this._form.byday.splice(i, 1); else this._form.byday.push(wd);
    },
    closeEditor() { this.editorOpen = false; this.editing = null; this._occRid = null; },

    get formValid() {
        const f = this._form;
        if (!f.title.trim()) return false;
        if (!f.startDate) return false;
        return true;
    },

    saveEvent() {
        this._saveAttempted = true;
        if (!this.formValid) return;
        const f = this._form;
        const now = new Date().toISOString();
        const start = f.allDay ? f.startDate : `${f.startDate}T${f.startTime || '00:00'}`;
        const endDate = f.endDate || f.startDate;
        const end = f.allDay ? endDate : `${endDate}T${f.endTime || f.startTime || '00:00'}`;
        const rrule = buildRRuleString({ freq: f.repeat, interval: f.interval, byday: f.byday, ends: f.ends, count: f.count, until: f.until }) || null;
        const props = {
            calendarId: f.calendarId || this._defaultCalendarId(),
            title: f.title.trim(),
            description: f.description.trim(),
            location: f.location.trim() ? { label: f.location.trim(), lat: f.locationLat ?? null, lng: f.locationLng ?? null } : null,
            reminders: (f.reminders || []).map((r) => ({ minutesBefore: Number(r.minutesBefore) || 0, method: r.method || 'local' })),
            allDay: !!f.allDay,
            updatedAt: now,
        };
        const temporal = { start, end, tz: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' };

        if (!this.editing) {
            this.events.push({ id: newId(), createdAt: now, ...props, ...temporal, rrule, exdates: [], status: 'confirmed' });
        } else if (this._occRid && this.editScope === 'this' && this.editing.rrule) {
            // Override just this occurrence: exclude it from the master + add a
            // standalone override record carrying the edited fields.
            if (!Array.isArray(this.editing.exdates)) this.editing.exdates = [];
            if (!this.editing.exdates.includes(this._occRid)) this.editing.exdates.push(this._occRid);
            this.events.push({
                id: newId(), createdAt: now, ...props, ...temporal,
                rrule: null, exdates: [],
                status: 'confirmed', overrideOf: this.editing.id, recurrenceId: this._occRid,
            });
        } else if (this._occRid && this.editScope === 'all') {
            // Whole series: edit properties + recurrence, keep the master anchor time.
            Object.assign(this.editing, props, { rrule });
        } else {
            // Plain edit of a single event (or an override record).
            Object.assign(this.editing, props, temporal, { rrule });
        }
        this._mut++;
        this._save();
        this._queueRemSync();
        this.closeEditor();
    },

    deleteEvent(ev) {
        // ev may be the master in the editor. When deleting one occurrence of a
        // series, add an EXDATE instead of removing the master.
        if (this._occRid && this.editScope === 'this' && this.editing && this.editing.rrule) {
            if (!Array.isArray(this.editing.exdates)) this.editing.exdates = [];
            if (!this.editing.exdates.includes(this._occRid)) this.editing.exdates.push(this._occRid);
            // Drop any override for that occurrence too.
            this.events = this.events.filter((e) => !(e.overrideOf === this.editing.id && e.recurrenceId === this._occRid));
        } else {
            const id = (this.editing || ev).id;
            this.events = this.events.filter((e) => e.id !== id && e.overrideOf !== id);
        }
        this._mut++;
        this._save();
        this._queueRemSync();
        this.closeEditor();
    },

    // ---- calendars ----
    openCalMgr() { this.calMgrOpen = true; this._calForm = null; },
    closeCalMgr() { this.calMgrOpen = false; this._calForm = null; },
    newCalendar() { this._calForm = { id: null, name: '', color: CALENDAR_COLORS[this.calendars.length % CALENDAR_COLORS.length], icon: 'calendar' }; },
    editCalendar(c) { this._calForm = { id: c.id, name: c.name, color: c.color, icon: c.icon || 'calendar' }; },
    saveCalendar() {
        const f = this._calForm;
        if (!f || !f.name.trim()) return;
        if (f.id) {
            const c = this.calendars.find((x) => x.id === f.id);
            if (c) { c.name = f.name.trim(); c.color = f.color; c.icon = f.icon || 'calendar'; }
        } else {
            this.calendars.push({ id: newId(), name: f.name.trim(), color: f.color, icon: f.icon || 'calendar', isDefault: this.calendars.length === 0 });
        }
        this._mut++;
        this._save();
        this._calForm = null;
    },
    deleteCalendar(c) {
        if (this.calendars.length <= 1) return; // keep at least one
        this.calendars = this.calendars.filter((x) => x.id !== c.id);
        // Reassign that calendar's events to the (new) default.
        const def = this._defaultCalendarId();
        for (const ev of this.events) if (ev.calendarId === c.id) ev.calendarId = def;
        if (!this.calendars.some((x) => x.isDefault) && this.calendars[0]) this.calendars[0].isDefault = true;
        this._mut++;
        this._save();
    },
    setDefaultCalendar(c) {
        for (const x of this.calendars) x.isDefault = x.id === c.id;
        this._mut++;
        this._save();
    },

    // ---- location search (OSM) ----
    onLocationInput() {
        clearTimeout(this._geoTimer);
        this._form.locationLat = null;
        this._form.locationLng = null;
        const q = (this._form.location || '').trim();
        if (q.length < 3) { this.geoResults = []; return; }
        this._geoTimer = setTimeout(() => this.searchLocation(q), 350);
    },
    async searchLocation(q) {
        this.geoSearching = true;
        try {
            const res = await getJson('/calendar/geocode?q=' + encodeURIComponent(q));
            this.geoResults = Array.isArray(res.results) ? res.results.slice(0, 6) : [];
        } catch {
            this.geoResults = [];
        } finally {
            this.geoSearching = false;
        }
    },
    pickLocation(r) {
        this._form.location = r.display;
        this._form.locationLat = r.lat;
        this._form.locationLng = r.lng;
        this.geoResults = [];
    },

    // ---- reminders ----
    hasReminder(min) { return (this._form.reminders || []).some((r) => Number(r.minutesBefore) === Number(min)); },
    toggleReminder(min) {
        const i = (this._form.reminders || []).findIndex((r) => Number(r.minutesBefore) === Number(min));
        if (i >= 0) { this._form.reminders.splice(i, 1); return; }
        this._form.reminders.push({ minutesBefore: Number(min), method: 'local' });
        this._requestNotifyPermission();
    },
    reminderLabel(min) {
        const m = Number(min);
        const L = this.labels.reminder || {};
        if (m === 0) return L.at_time || 'At time of event';
        if (m % 1440 === 0) return (L.days || ':n days before').replace(':n', m / 1440);
        if (m % 60 === 0) return (L.hours || ':n hours before').replace(':n', m / 60);
        return (L.minutes || ':n minutes before').replace(':n', m);
    },
    _requestNotifyPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
    },

    // ---- local notification scheduler ----
    // Fires desktop notifications for reminders while the tab is open. The durable,
    // works-when-closed path is the opaque server push (added in a later slice).
    _startRemClock() {
        if (this._remClock) return;
        this._lastScanMs = Date.now();
        this._remClock = setInterval(() => this._scanReminders(), 60_000);
        // First scan shortly after load (also nudges permission if reminders exist).
        setTimeout(() => this._scanReminders(), 2_000);
        if ((this.events || []).some((e) => Array.isArray(e.reminders) && e.reminders.length)) this._requestNotifyPermission();
        // Register the durable server-push set once on load.
        this._queueRemSync();
    },

    // Debounced upload of the upcoming reminder fire-times (opaque timestamps) so
    // the server can push even when the app is closed. Best-effort.
    _queueRemSync() {
        clearTimeout(this._remSyncTimer);
        this._remSyncTimer = setTimeout(() => this._syncReminders(), 1_500);
    },
    async _syncReminders() {
        try {
            const now = Date.now();
            const horizon = now + 60 * 86_400_000; // 60 days
            const list = collectReminders(this.events, now, horizon).map((r) => ({
                event_id: r.eventId,
                recurrence_id: r.recurrenceId,
                remind_at: new Date(r.fireMs).toISOString(),
            }));
            await postForm(this.labels.remindersUrl || '/calendar/reminders', { reminders: list }, 'PUT');
        } catch { /* best-effort; local scheduler still covers the open tab */ }
    },
    _stopRemClock() {
        if (this._remClock) { clearInterval(this._remClock); this._remClock = null; }
    },
    _scanReminders() {
        const now = Date.now();
        const from = this._lastScanMs || (now - 60_000);
        this._lastScanMs = now;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        for (const r of collectReminders(this.events, from, now)) {
            if (this._firedReminders.has(r.key)) continue;
            this._firedReminders.add(r.key);
            try {
                new Notification(r.title || (this.labels.untitled || 'Event'), {
                    body: this.fmtDay(r.start.slice(0, 10)),
                    tag: r.key,
                });
            } catch { /* notifications unavailable */ }
        }
    },

    // ---- iCalendar import / export (client-only, ZK) ----
    exportIcs() {
        const name = (this.calendars[0] && this.calendars[0].name) || 'Ledgerline';
        const ics = buildIcs(this.events, name);
        saveBlobAs(new Blob([ics], { type: 'text/calendar' }), 'ledgerline-calendar.ics');
    },
    async importIcs(fileList) {
        const files = Array.from(fileList || []);
        if (!files.length) return;
        const calId = this._defaultCalendarId();
        const now = new Date().toISOString();
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        let added = 0;
        for (const file of files) {
            let text = '';
            try { text = await file.text(); } catch { continue; }
            for (const e of parseIcs(text)) {
                if (!e.start) continue;
                this.events.push({
                    id: newId(),
                    calendarId: calId,
                    title: e.title || '',
                    description: e.description || '',
                    location: e.location || null,
                    allDay: !!e.allDay,
                    start: e.start,
                    end: e.end || e.start,
                    tz,
                    rrule: e.rrule || null,
                    exdates: Array.isArray(e.exdates) ? e.exdates : [],
                    reminders: Array.isArray(e.reminders) ? e.reminders : [],
                    status: 'confirmed',
                    createdAt: now,
                    updatedAt: now,
                });
                added++;
            }
        }
        if (added) {
            this._mut++;
            this._save();
            this._queueRemSync();
        }
        window.llToast?.((this.labels.import_done || ':n imported').replace(':n', added));
    },

    fmtDay(iso) { return formatDate(iso); },
});

// Split an ISO datetime into { d: yyyy-mm-dd, t: HH:MM } (local-ish; the stored
// value is already local wall-clock from datetime-local inputs).
function splitDt(iso) {
    if (!iso) return { d: '', t: '09:00' };
    const [d, rest] = String(iso).split('T');
    const t = (rest || '09:00').slice(0, 5);
    return { d, t };
}
