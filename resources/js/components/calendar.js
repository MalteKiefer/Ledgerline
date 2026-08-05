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
    editing: null,     // the event being edited (null = closed)
    _form: BLANK_FORM(),
    _saveAttempted: false,

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

    dayEvents(iso) {
        void this._mut;
        return eventsOnDay(this.events, iso);
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
        this._saveAttempted = false;
        this.editorOpen = true;
    },
    openEvent(ev) {
        const s = ev.allDay ? { d: (ev.start || '').slice(0, 10), t: '09:00' } : splitDt(ev.start);
        const e = ev.allDay ? { d: (ev.end || ev.start || '').slice(0, 10), t: '10:00' } : splitDt(ev.end || ev.start);
        this._form = {
            id: ev.id,
            calendarId: ev.calendarId || this._defaultCalendarId(),
            title: ev.title || '',
            description: ev.description || '',
            location: (ev.location && ev.location.label) || '',
            allDay: !!ev.allDay,
            startDate: s.d, startTime: s.t,
            endDate: e.d, endTime: e.t,
        };
        this.editing = ev;
        this._saveAttempted = false;
        this.editorOpen = true;
    },
    closeEditor() { this.editorOpen = false; this.editing = null; },

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
        const start = f.allDay ? f.startDate : `${f.startDate}T${f.startTime || '00:00'}`;
        const endDate = f.endDate || f.startDate;
        const end = f.allDay ? endDate : `${endDate}T${f.endTime || f.startTime || '00:00'}`;
        const patch = {
            calendarId: f.calendarId || this._defaultCalendarId(),
            title: f.title.trim(),
            description: f.description.trim(),
            location: f.location.trim() ? { label: f.location.trim(), lat: null, lng: null } : null,
            allDay: !!f.allDay,
            start,
            end,
            tz: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
            rrule: null,
            exdates: [],
            reminders: [],
            status: 'confirmed',
            updatedAt: new Date().toISOString(),
        };
        if (this.editing) {
            Object.assign(this.editing, patch);
        } else {
            this.events.push({ id: newId(), createdAt: patch.updatedAt, ...patch });
        }
        this._mut++;
        this._save();
        this.closeEditor();
    },

    deleteEvent(ev) {
        const i = this.events.findIndex((e) => e.id === ev.id);
        if (i < 0) return;
        this.events.splice(i, 1);
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
