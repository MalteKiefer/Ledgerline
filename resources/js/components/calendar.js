// Calendar module (ZK). Calendars + events live in the opaque per-module store
// window.LLModuleStore.calendar — the server only ever sees ciphertext. Slice 1:
// month view + day agenda + single (non-recurring) events + calendar management.
// Recurrence, reminders, OSM locations, iCal and sharing are layered on in later
// slices. Store-derived getters reference `void this._mut` because the mapped
// arrays are the (non-Alpine-reactive) store data.
import { zkModule } from '../shared/zk-module';
import { newId } from '../shared/sealed-store';
import { formatDate } from '../shared/dom';
import {
    ymd, monthMatrix, eventsOnDay, timeLabel, CALENDAR_COLORS,
} from '../shared/calendar-utils';
import {
    expandEvent, buildRRuleString, parseRRuleString, rruleSummary, RRULE_FREQS, RRULE_WEEKDAYS,
} from '../shared/calendar-rrule';

const BLANK_FORM = () => ({
    id: null,
    calendarId: '',
    title: '',
    description: '',
    location: '',
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
        },
    }),

    labels,
    calendars: [],
    events: [],
    _mut: 0,

    // Current visible month.
    viewY: new Date().getFullYear(),
    viewM: new Date().getMonth(),
    todayIso: ymd(new Date()),

    selectedDay: null, // iso of the open day agenda, or null
    editorOpen: false,
    editing: null,      // the (master or single) event being edited (null = closed)
    _occRid: null,      // recurrenceId when editing one occurrence of a series
    editScope: 'all',   // 'all' | 'this' — for recurring occurrences
    _saveAttempted: false,
    freqs: RRULE_FREQS,
    weekdays: RRULE_WEEKDAYS,
    _form: BLANK_FORM(),

    calMgrOpen: false,
    _calForm: null,    // { id, name, color } or null
    colors: CALENDAR_COLORS,

    async init() {
        await this._initZk();
    },

    // Seed a default calendar on first use so events always have a home.
    _ensureDefault(ms) {
        const data = ms.data;
        if (!Array.isArray(data.calendars)) data.calendars = [];
        if (data.calendars.length === 0) {
            data.calendars.push({ id: newId(), name: this.labels.default_calendar || 'Personal', color: CALENDAR_COLORS[0], isDefault: true });
            ms.touch();
        }
    },

    // ---- month grid ----
    get monthWeeks() {
        void this._mut;
        return monthMatrix(this.viewY, this.viewM, this.todayIso);
    },
    get monthLabel() {
        return new Date(this.viewY, this.viewM, 1).toLocaleDateString(document.documentElement.lang || 'en', { month: 'long', year: 'numeric' });
    },
    get weekdayLabels() {
        // Monday-first short weekday names in the UI locale.
        const base = new Date(2026, 5, 1); // a Monday
        const out = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(base);
            d.setDate(base.getDate() + i);
            out.push(d.toLocaleDateString(document.documentElement.lang || 'en', { weekday: 'short' }));
        }
        return out;
    },

    // Non-recurring events + expanded occurrences of recurring ones (minus any
    // occurrence that a per-occurrence override replaces) across the visible range.
    get visibleEvents() {
        void this._mut; void this.viewY; void this.viewM;
        const rangeStart = ymd(new Date(this.viewY, this.viewM, 1 - 7));
        const rangeEnd = ymd(new Date(this.viewY, this.viewM + 1, 7));
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
        return eventsOnDay(this.visibleEvents, iso);
    },
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

    prevMonth() { if (--this.viewM < 0) { this.viewM = 11; this.viewY--; } },
    nextMonth() { if (++this.viewM > 11) { this.viewM = 0; this.viewY++; } },
    goToday() { const n = new Date(); this.viewY = n.getFullYear(); this.viewM = n.getMonth(); this.selectedDay = this.todayIso; },

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
            location: f.location.trim() ? { label: f.location.trim(), lat: null, lng: null } : null,
            allDay: !!f.allDay,
            updatedAt: now,
        };
        const temporal = { start, end, tz: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' };

        if (!this.editing) {
            this.events.push({ id: newId(), createdAt: now, ...props, ...temporal, rrule, exdates: [], reminders: [], status: 'confirmed' });
        } else if (this._occRid && this.editScope === 'this' && this.editing.rrule) {
            // Override just this occurrence: exclude it from the master + add a
            // standalone override record carrying the edited fields.
            if (!Array.isArray(this.editing.exdates)) this.editing.exdates = [];
            if (!this.editing.exdates.includes(this._occRid)) this.editing.exdates.push(this._occRid);
            this.events.push({
                id: newId(), createdAt: now, ...props, ...temporal,
                rrule: null, exdates: [], reminders: this.editing.reminders || [],
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
        this.closeEditor();
    },

    // ---- calendars ----
    openCalMgr() { this.calMgrOpen = true; this._calForm = null; },
    closeCalMgr() { this.calMgrOpen = false; this._calForm = null; },
    newCalendar() { this._calForm = { id: null, name: '', color: CALENDAR_COLORS[this.calendars.length % CALENDAR_COLORS.length] }; },
    editCalendar(c) { this._calForm = { id: c.id, name: c.name, color: c.color }; },
    saveCalendar() {
        const f = this._calForm;
        if (!f || !f.name.trim()) return;
        if (f.id) {
            const c = this.calendars.find((x) => x.id === f.id);
            if (c) { c.name = f.name.trim(); c.color = f.color; }
        } else {
            this.calendars.push({ id: newId(), name: f.name.trim(), color: f.color, isDefault: this.calendars.length === 0 });
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
