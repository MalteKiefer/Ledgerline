// Central date/time formatting that honours the user's display preferences:
// an optional hard IANA timezone (else the browser's), a date-format preset,
// and the 12/24h clock. Used across the SPA so every view shows dates the same
// way. Dependency-free — timezone maths via Intl.DateTimeFormat.

export interface DateTimePrefs {
  timezone?: string | null;
  date_format?: string; // system | dmy | dmy_dot | mdy | ymd
  time_format?: string; // 24h | 12h
}

let prefs: DateTimePrefs = {};

/** Set once after /me loads (and on preference changes). */
export function setDateTimePrefs(p: DateTimePrefs | null | undefined): void {
  prefs = p ?? {};
}

/** The user's effective timezone: the hard override, else the browser/system. */
export function effectiveTz(): string {
  if (prefs.timezone) return prefs.timezone;
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch {
    return 'UTC';
  }
}

export function browserTz(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch {
    return 'UTC';
  }
}

function is12h(): boolean {
  return prefs.time_format === '12h';
}

function toDate(input: string | number | Date): Date | null {
  if (input instanceof Date) return Number.isNaN(input.getTime()) ? null : input;
  const d = new Date(input);
  return Number.isNaN(d.getTime()) ? null : d;
}

/** Extract the y/m/d/h/min the given instant shows in `tz`. */
function zonedParts(d: Date, tz: string): { y: number; mo: number; da: number; h: number; mi: number; s: number } {
  const p = new Intl.DateTimeFormat('en-US', {
    timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
  }).formatToParts(d);
  const get = (t: string) => Number(p.find((x) => x.type === t)?.value ?? '0');
  let h = get('hour');
  if (h === 24) h = 0; // some engines emit 24 for midnight
  return { y: get('year'), mo: get('month'), da: get('day'), h, mi: get('minute'), s: get('second') };
}

/** Format a date only, per the date-format preset, in the effective timezone. */
/** Format a bare civil date (no timezone) per the date-format preset. */
export function fmtYmd(y: number, mo: number, da: number): string {
  const fmt = prefs.date_format || 'system';
  if (fmt === 'system') {
    const loc = document.documentElement.lang || 'en';
    return new Date(Date.UTC(y, mo - 1, da)).toLocaleDateString(loc, { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
  }
  const yy = String(y).padStart(4, '0'); const mm = String(mo).padStart(2, '0'); const dd = String(da).padStart(2, '0');
  switch (fmt) {
    case 'dmy': return `${dd}/${mm}/${yy}`;
    case 'dmy_dot': return `${dd}.${mm}.${yy}`;
    case 'mdy': return `${mm}/${dd}/${yy}`;
    case 'ymd': return `${yy}-${mm}-${dd}`;
    default: return `${dd}.${mm}.${yy}`;
  }
}

export function fmtDate(input: string | number | Date | null | undefined, opts?: { tz?: string }): string {
  if (input == null || input === '') return '';
  // A bare "YYYY-MM-DD" is a civil date — never shift it by a timezone.
  if (typeof input === 'string') {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(input.trim());
    if (m) return fmtYmd(Number(m[1]), Number(m[2]), Number(m[3]));
  }
  const d = toDate(input);
  if (!d) return String(input);
  const tz = opts?.tz ?? effectiveTz();
  const fmt = prefs.date_format || 'system';
  if (fmt === 'system') {
    const loc = document.documentElement.lang || 'en';
    return d.toLocaleDateString(loc, { timeZone: tz, year: 'numeric', month: 'long', day: 'numeric' });
  }
  const { y, mo, da } = zonedParts(d, tz);
  const yy = String(y).padStart(4, '0');
  const mm = String(mo).padStart(2, '0');
  const dd = String(da).padStart(2, '0');
  switch (fmt) {
    case 'dmy': return `${dd}/${mm}/${yy}`;
    case 'dmy_dot': return `${dd}.${mm}.${yy}`;
    case 'mdy': return `${mm}/${dd}/${yy}`;
    case 'ymd': return `${yy}-${mm}-${dd}`;
    default: return `${dd}.${mm}.${yy}`;
  }
}

/** Format a time only (HH:mm or h:mm AM/PM), in the effective timezone. */
export function fmtTime(input: string | number | Date | null | undefined, opts?: { tz?: string }): string {
  if (input == null || input === '') return '';
  const d = toDate(input);
  if (!d) return String(input);
  const tz = opts?.tz ?? effectiveTz();
  const loc = document.documentElement.lang || 'en';
  return d.toLocaleTimeString(loc, { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: is12h() });
}

/** Format a full date + time, in the effective timezone. */
export function fmtDateTime(input: string | number | Date | null | undefined, opts?: { tz?: string }): string {
  if (input == null || input === '') return '';
  const d = toDate(input);
  if (!d) return String(input);
  const date = fmtDate(d, opts);
  const time = fmtTime(d, opts);
  return date && time ? `${date}, ${time}` : (date || time);
}

/**
 * Interpret an <input type="datetime-local"> (or date) wall-clock string as
 * being in `tz` and return the corresponding UTC ISO string.
 * Dependency-free via the offset trick (accurate outside the rare DST fold).
 */
export function zonedInputToUtc(wall: string, tz: string): string {
  const [datePart, timePart = '00:00'] = wall.split('T');
  const [y, mo, da] = datePart.split('-').map(Number);
  const [h, mi] = timePart.split(':').map(Number);
  const guess = Date.UTC(y, (mo || 1) - 1, da || 1, h || 0, mi || 0, 0);
  const zp = zonedParts(new Date(guess), tz);
  const zonedAsUtc = Date.UTC(zp.y, zp.mo - 1, zp.da, zp.h, zp.mi, zp.s);
  const offset = zonedAsUtc - guess; // tz is `offset` ms ahead of UTC at that instant
  return new Date(guess - offset).toISOString();
}

/**
 * Format a UTC instant as the "YYYY-MM-DDTHH:mm" wall-clock string an
 * <input type="datetime-local"> in `tz` expects (or "YYYY-MM-DD" for all-day).
 */
export function utcToZonedInput(iso: string, tz: string, allDay = false): string {
  const d = toDate(iso);
  if (!d) return '';
  const p = zonedParts(d, tz);
  const date = `${String(p.y).padStart(4, '0')}-${String(p.mo).padStart(2, '0')}-${String(p.da).padStart(2, '0')}`;
  return allDay ? date : `${date}T${String(p.h).padStart(2, '0')}:${String(p.mi).padStart(2, '0')}`;
}

/** Hour-of-day (fractional) an instant shows in the timezone — for grid layout. */
export function hoursInTz(input: string | number | Date, tz?: string): number {
  const d = toDate(input);
  if (!d) return 0;
  const p = zonedParts(d, tz ?? effectiveTz());
  return p.h + p.mi / 60;
}

/** A reasonable, deduped list of IANA timezones for a picker (browser-provided when available). */
export function timezoneList(): string[] {
  const anyIntl = Intl as unknown as { supportedValuesOf?: (k: string) => string[] };
  let list: string[] = [];
  try {
    if (typeof anyIntl.supportedValuesOf === 'function') list = anyIntl.supportedValuesOf('timeZone');
  } catch { /* ignore */ }
  if (!list.length) list = FALLBACK_TZ;
  const browser = browserTz();
  return [...new Set([browser, ...list])];
}

const FALLBACK_TZ = [
  'UTC', 'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'Europe/Madrid', 'Europe/Rome',
  'Europe/Moscow', 'Europe/Istanbul', 'America/New_York', 'America/Chicago', 'America/Denver',
  'America/Los_Angeles', 'America/Sao_Paulo', 'Asia/Dubai', 'Asia/Kolkata', 'Asia/Shanghai',
  'Asia/Tokyo', 'Asia/Singapore', 'Australia/Sydney', 'Pacific/Auckland',
];
