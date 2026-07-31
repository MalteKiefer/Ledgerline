// Global, non-secret DISPLAY preferences (measurement units + clock format),
// injected by the layout as <meta name="ll-prefs"> and mirrored to mobile via
// GET /me. Presentation only — the underlying data stays zero-knowledge; only the
// unit/format it is shown in is chosen here. Canonical storage is unchanged
// (meters, kg, °C, mg/dL); these helpers convert for display.
//
// Read lazily + memoised. After saving on the appearance page, call setPrefs() so
// the running UI reflects the change without a reload.

const DEFAULTS = { distance: 'km', elevation: 'm', weight: 'kg', temp: 'c', glucose: 'mgdl', time_format: '24h' };

let _cache = null;

function read() {
    if (typeof window !== 'undefined' && window.LLPrefs) return window.LLPrefs;
    if (_cache) return _cache;
    try {
        const el = typeof document !== 'undefined' ? document.querySelector('meta[name="ll-prefs"]') : null;
        _cache = el ? { ...DEFAULTS, ...JSON.parse(el.getAttribute('content') || '{}') } : { ...DEFAULTS };
    } catch (e) {
        _cache = { ...DEFAULTS };
    }
    return _cache;
}

/** The current preferences map (with defaults filled in). */
export function prefs() { return read(); }

/** Update the in-memory prefs after a save so the UI updates without a reload. */
export function setPrefs(patch) {
    const next = { ...read(), ...(patch || {}) };
    if (typeof window !== 'undefined') window.LLPrefs = next;
    _cache = next;
    return next;
}

/** Whether times should render in 12-hour (AM/PM) form. */
export function is12h() { return read().time_format === '12h'; }

/** Distance: canonical METERS → { value, unit } in the user's unit. */
export function convertDistance(meters, digits = 2) {
    const m = Number(meters) || 0;
    if (read().distance === 'mi') return { value: Math.round(m / 1609.344 * 10 ** digits) / 10 ** digits, unit: 'mi' };
    return { value: Math.round(m / 1000 * 10 ** digits) / 10 ** digits, unit: 'km' };
}
export function distanceUnit() { return read().distance === 'mi' ? 'mi' : 'km'; }
export function distanceLabel(meters, digits = 2) {
    const d = convertDistance(meters, digits);
    return d.value + ' ' + d.unit;
}
/** Distance in the user's unit as a bare number (for chart axes). */
export function distanceValue(meters, digits = 2) { return convertDistance(meters, digits).value; }

/** Elevation: canonical METERS → { value, unit } in the user's unit. */
export function convertElevation(meters, digits = 0) {
    const m = Number(meters) || 0;
    if (read().elevation === 'ft') return { value: Math.round(m * 3.28084 * 10 ** digits) / 10 ** digits, unit: 'ft' };
    return { value: Math.round(m * 10 ** digits) / 10 ** digits, unit: 'm' };
}
export function elevationUnit() { return read().elevation === 'ft' ? 'ft' : 'm'; }
export function elevationLabel(meters, digits = 0) {
    const e = convertElevation(meters, digits);
    return e.value + ' ' + e.unit;
}
export function elevationValue(meters, digits = 1) { return convertElevation(meters, digits).value; }

/** Health unit config (weight/temp/glucose) sourced from the global prefs. */
export function healthUnits() {
    const p = read();
    return { weight: p.weight === 'lb' ? 'lb' : 'kg', temp: p.temp === 'f' ? 'f' : 'c', glucose: p.glucose === 'mmoll' ? 'mmoll' : 'mgdl' };
}
