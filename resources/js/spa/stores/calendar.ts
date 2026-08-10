import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

// A calendar collection (mirrors AddressBook in stores/contacts.ts).
export interface CalendarCol {
  id: string;
  name: string;
  uri: string;
  color: string | null;
  kind: 'normal' | 'holidays' | 'school_holidays' | 'birthdays';
  country?: string | null;
  subdivision?: string | null;
  owned: boolean;
}

// Country / subdivision options for the special-calendar dialog (from OpenHolidays).
export interface HolidayCountry {
  isoCode: string;
  name: string;
}
export interface HolidaySubdivision {
  code: string;
  name: string;
}

// A recurrence-expanded occurrence returned by the range query. `id`/`uid`
// identify the master event for editing; `color` echoes the parent calendar.
export interface Occurrence {
  id: string;
  calendar: string;
  uid: string;
  summary: string;
  location: string | null;
  description: string | null;
  start: string; // ISO-8601 UTC
  end: string; // ISO-8601 UTC
  all_day: boolean;
  status: string | null;
  recurring: boolean;
  color: string | null;
  geo_lat: number | string | null;
  geo_lon: number | string | null;
}

// One address suggestion from the server geo proxy (GET /geo/search).
export interface GeoResult {
  display: string;
  lat: number;
  lon: number;
}

// Full editor payload for one master event (mirrors ContactDetail).
export interface EventDetail extends Record<string, unknown> {
  id: string;
  calendar: string;
  uid: string;
  summary: string;
  description: string | null;
  location: string | null;
  dtstart: string;
  dtend: string | null;
  all_day: boolean;
  rrule: string | null;
  status: string | null;
  alarm_minutes_before: number | null;
  geo_lat: number | string | null;
  geo_lon: number | string | null;
  sequence: number;
  etag: string;
}

export interface CalSettings {
  default_view: 'month' | 'week' | 'agenda';
  week_start: 0 | 1;
}

export interface CalImportResult {
  created: number;
  updated: number;
  skipped: number;
}

export const useCalendarStore = defineStore('calendar', () => {
  const calendars = ref<CalendarCol[]>([]);
  const settings = ref<CalSettings>({ default_view: 'month', week_start: 1 });
  const events = ref<Occurrence[]>([]);

  async function loadData() {
    const r = await api.get<{ calendars: CalendarCol[]; settings: CalSettings }>('/api/v1/calendar/data');
    calendars.value = r.calendars;
    settings.value = r.settings;
  }

  async function loadRange(fromISO: string, toISO: string) {
    const qs = new URLSearchParams({ from: fromISO, to: toISO });
    const r = await api.get<{ events: Occurrence[] }>(`/api/v1/calendar/events?${qs}`);
    events.value = r.events;
  }

  const show = (id: string) => api.get<EventDetail>(`/api/v1/calendar/events/${id}`);
  const create = (body: Record<string, unknown>) => api.post<{ id: string }>('/api/v1/calendar/events', body);
  const update = (id: string, body: Record<string, unknown>) =>
    api.put<{ ok: boolean; etag: string }>(`/api/v1/calendar/events/${id}`, body); // may 409 { error:'etag_conflict', etag }
  const destroy = (id: string) => api.delete(`/api/v1/calendar/events/${id}`);
  // Single-occurrence ops for a recurring series (RECURRENCE-ID / EXDATE).
  const excludeOccurrence = (id: string, start: string) =>
    api.post<{ ok: boolean; etag: string }>(`/api/v1/calendar/events/${id}/exclude`, { start });
  const overrideOccurrence = (id: string, recurrenceId: string, body: Record<string, unknown>) =>
    api.put<{ ok: boolean; etag: string }>(`/api/v1/calendar/events/${id}/occurrence`, { ...body, recurrence_id: recurrenceId });

  const createCalendar = (name: string, color?: string) => api.post<{ id: string }>('/api/v1/calendars', { name, color });
  const updateCalendar = (id: string, body: Record<string, unknown>) => api.put(`/api/v1/calendars/${id}`, body);
  const deleteCalendar = (id: string) => api.delete(`/api/v1/calendars/${id}`);

  // Special (generated, read-only) calendars — public/school holidays / birthdays.
  // Holiday kinds carry an optional country + region (subdivision); birthdays ignore them.
  const createSpecial = (
    kind: 'holidays' | 'school_holidays' | 'birthdays',
    opts: { name: string; color?: string; country?: string; subdivision?: string },
  ) =>
    api.post<{ id: string; created: number }>('/api/v1/calendars/special', {
      kind,
      name: opts.name,
      color: opts.color,
      country: opts.country || undefined,
      subdivision: opts.subdivision || undefined,
    });
  const regenerate = (id: string) => api.post<{ ok: boolean; created: number }>(`/api/v1/calendars/${id}/regenerate`, {});

  // OpenHolidays country / subdivision lists (server-proxied + cached) for the dialog selects.
  const loadHolidayCountries = () =>
    api.get<{ countries: HolidayCountry[] }>('/api/v1/calendar/holiday-countries').then((r) => r.countries);
  const loadHolidaySubdivisions = (country: string) =>
    api
      .get<{ subdivisions: HolidaySubdivision[] }>(`/api/v1/calendar/holiday-subdivisions?country=${encodeURIComponent(country)}`)
      .then((r) => r.subdivisions);

  const saveSettings = (s: CalSettings) => api.post('/api/v1/calendar/settings', s);

  // Address autocomplete — server-side geo proxy (authenticated, throttled).
  const geoSearch = (q: string) =>
    api.get<{ results: GeoResult[] }>(`/api/v1/geo/search?q=${encodeURIComponent(q)}`).then((r) => r.results);

  function importIcs(file: File, calendarId: string) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('calendar_id', calendarId);
    return api.upload<CalImportResult>('/api/v1/calendar/import', fd);
  }

  // Path to the .ics export; the view wraps this with api.streamUrl so the
  // bearer token rides along for an <a>/download (no Authorization header there).
  const exportUrl = (id?: string) => `/api/v1/calendar/export${id ? `?calendar=${encodeURIComponent(id)}` : ''}`;

  return {
    calendars, settings, events,
    loadData, loadRange, show, create, update, destroy, excludeOccurrence, overrideOccurrence,
    createCalendar, updateCalendar, deleteCalendar, createSpecial, regenerate, saveSettings, importIcs, exportUrl,
    loadHolidayCountries, loadHolidaySubdivisions, geoSearch,
  };
});
