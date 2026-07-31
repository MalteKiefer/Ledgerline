/**
 * Pure health-metric logic — no DOM, no Alpine, no side-effects.
 *
 * Exports:
 *   METRICS         ordered registry (weight, bp, pulse, spo2, temp, glucose)
 *   metric(key)     look up a registry entry
 *   computeAge      integer years from birthdate (YYYY-MM-DD) + injected nowIso
 *   computeBmi      kg / m² to 1 dp
 *   kgToLb / lbToKg / cToF / fToC / mgdlToMmoll / mmollToMgdl  unit conversions
 *   classify        'ok' | 'amber' | 'red' per clinical thresholds
 *   csvRows         export rows sorted by ts, values display-converted
 */

// ---------------------------------------------------------------------------
// Registry
// ---------------------------------------------------------------------------

/** @type {Array<{key:string,labelKey:string,unit:string,tint:string,icon:string,dual:boolean}>} */
export const METRICS = [
    { key: 'weight',  labelKey: 'health.metric_weight',  unit: 'kg',    tint: 'sky',    icon: 'scale',          dual: false },
    { key: 'bp',      labelKey: 'health.metric_bp',      unit: 'mmHg',  tint: 'rose',   icon: 'heart',          dual: true  },
    { key: 'pulse',   labelKey: 'health.metric_pulse',   unit: 'bpm',   tint: 'pink',   icon: 'heart',          dual: false },
    { key: 'spo2',    labelKey: 'health.metric_spo2',    unit: '%',     tint: 'blue',   icon: 'beaker',         dual: false },
    { key: 'temp',    labelKey: 'health.metric_temp',    unit: '°C',    tint: 'amber',  icon: 'thermometer',    dual: false },
    { key: 'glucose', labelKey: 'health.metric_glucose', unit: 'mg/dL', tint: 'green',  icon: 'beaker',         dual: false },
];

const _byKey = Object.fromEntries(METRICS.map((m) => [m.key, m]));

/**
 * @param {string} key
 * @returns {typeof METRICS[0] | undefined}
 */
export function metric(key) {
    return _byKey[key];
}

// ---------------------------------------------------------------------------
// Age / BMI
// ---------------------------------------------------------------------------

/**
 * Compute age in whole years.
 *
 * @param {string} birthdate  YYYY-MM-DD (empty or invalid → null)
 * @param {string} nowIso     ISO 8601 string for "now" (injected for testability)
 * @returns {number|null}
 */
export function computeAge(birthdate, nowIso) {
    if (!birthdate) return null;
    const bd = new Date(birthdate);
    if (isNaN(bd.getTime())) return null;

    const now = new Date(nowIso);
    let age = now.getFullYear() - bd.getFullYear();
    const monthDiff = now.getMonth() - bd.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < bd.getDate())) {
        age -= 1;
    }
    return age;
}

/**
 * Body-mass index rounded to 1 decimal place.
 *
 * @param {number|null|undefined} weightKg
 * @param {number|null|undefined} heightCm
 * @returns {number|null}
 */
export function computeBmi(weightKg, heightCm) {
    if (!weightKg || !heightCm || weightKg <= 0 || heightCm <= 0) return null;
    const m = heightCm / 100;
    return Math.round((weightKg / (m * m)) * 10) / 10;
}

// ---------------------------------------------------------------------------
// Unit conversions
// ---------------------------------------------------------------------------

/** @param {number} kg @returns {number} */
export function kgToLb(kg) { return Math.round(kg * 2.20462 * 10) / 10; }

/** @param {number} lb @returns {number} */
export function lbToKg(lb) { return Math.round((lb / 2.20462) * 10) / 10; }

/** @param {number} c @returns {number} */
export function cToF(c) { return Math.round((c * 9 / 5 + 32) * 10) / 10; }

/** @param {number} f @returns {number} */
export function fToC(f) { return Math.round(((f - 32) * 5 / 9) * 10) / 10; }

/** @param {number} v mg/dL → mmol/L (1 dp) @returns {number} */
export function mgdlToMmoll(v) { return Math.round((v / 18.0182) * 10) / 10; }

/** @param {number} v mmol/L → mg/dL (integer) @returns {number} */
export function mmollToMgdl(v) { return Math.round(v * 18.0182); }

// ---------------------------------------------------------------------------
// Classification
// ---------------------------------------------------------------------------

/**
 * Clinical traffic-light classification.
 *
 * Thresholds (canonical units):
 *   spo2:    <92 red | 92–94 amber | ≥95 ok
 *   bp:      worst-of(sys,dia) — ≥140 or ≥90 red | 121–139 or 81–89 amber | else ok
 *   pulse:   60–100 ok | else amber
 *   temp:    <38 ok | 38–38.9 amber | ≥39 red
 *   weight / glucose: always ok
 *
 * @param {string} key
 * @param {number} v   primary value (canonical unit)
 * @param {number} [v2] secondary value (systolic/diastolic for bp)
 * @returns {'ok'|'amber'|'red'}
 */
export function classify(key, v, v2) {
    switch (key) {
        case 'spo2':
            if (v < 92) return 'red';
            if (v < 95) return 'amber';
            return 'ok';

        case 'bp': {
            const sys = v;
            const dia = v2 ?? 0;
            if (sys >= 140 || dia >= 90) return 'red';
            if (sys >= 121 || dia >= 81) return 'amber';
            return 'ok';
        }

        case 'pulse':
            if (v >= 60 && v <= 100) return 'ok';
            return 'amber';

        case 'temp':
            if (v >= 39) return 'red';
            if (v >= 38) return 'amber';
            return 'ok';

        case 'weight':
        case 'glucose':
        default:
            return 'ok';
    }
}

// ---------------------------------------------------------------------------
// CSV export
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// CSV cell escaping
// ---------------------------------------------------------------------------

/**
 * RFC 4180 CSV cell escaper.
 * Wraps the field in double-quotes when it contains a comma, double-quote,
 * or newline. Internal double-quotes are escaped by doubling them.
 *
 * @param {string} field
 * @returns {string}
 */
export function csvCell(field) {
    const s = String(field ?? '');
    if (s.includes(',') || s.includes('"') || s.includes('\n') || s.includes('\r')) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

// ---------------------------------------------------------------------------
// CSV export
// ---------------------------------------------------------------------------

/**
 * Build display-ready rows for one metric, sorted by ts ascending.
 *
 * unitCfg shape: { weight: 'kg'|'lb', temp: 'c'|'f', glucose: 'mgdl'|'mmoll' }
 * bp / pulse / spo2 are always exported in their canonical units.
 *
 * @param {Array<{id:string,ts:string,metric:string,v:number,v2:number|null,note:string}>} entries
 * @param {string} key
 * @param {{weight:string,temp:string,glucose:string}} unitCfg
 * @returns {string[][]}  header + one row per matching entry
 */
export function csvRows(entries, key, unitCfg) {
    const header = ['date', 'time', 'value', 'value2', 'unit', 'note'];

    const filtered = entries
        .filter((e) => e.metric === key)
        .sort((a, b) => (a.ts < b.ts ? -1 : a.ts > b.ts ? 1 : 0));

    const rows = filtered.map((e) => {
        const dt = new Date(e.ts);
        const date = dt.toISOString().slice(0, 10);
        const time = dt.toISOString().slice(11, 19);

        let displayV = e.v;
        let displayV2 = e.v2 ?? null;
        let unit = metric(key)?.unit ?? '';

        if (key === 'weight') {
            if (unitCfg.weight === 'lb') {
                displayV = kgToLb(e.v);
                unit = 'lb';
            }
        } else if (key === 'temp') {
            if (unitCfg.temp === 'f') {
                displayV = cToF(e.v);
                unit = '°F';
            }
        } else if (key === 'glucose') {
            if (unitCfg.glucose === 'mmoll') {
                displayV = mgdlToMmoll(e.v);
                unit = 'mmol/L';
            }
        }

        return [
            date,
            time,
            String(displayV),
            displayV2 !== null ? String(displayV2) : '',
            unit,
            e.note ?? '',
        ];
    });

    return [header, ...rows];
}
