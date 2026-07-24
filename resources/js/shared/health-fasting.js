// Pure, testable logic for intermittent-fasting tracking in the Health module.
// A "fast" is a client-side record { id, start, end|null, targetHours, note } kept
// in the sealed `health` module store (key `healthFasts`) — zero-knowledge, no new
// server field. The ACTIVE fast is the one with end === null; only one may ever be
// active (enforced client-side + by the store's optimistic version, see health.js).

/**
 * Common fasting protocols. `targetHours` is the FASTING window (the "X" in X:Y,
 * where Y = 24 − X is the eating window). Reaching targetHours = goal met.
 */
export const FAST_TEMPLATES = [
    { key: '12:12', targetHours: 12 },
    { key: '14:10', targetHours: 14 },
    { key: '16:8', targetHours: 16 },
    { key: '18:6', targetHours: 18 },
    { key: '20:4', targetHours: 20 },
];

/**
 * The single active fast (end === null), or null.
 *
 * @param {Array<object>} fasts
 * @returns {object|null}
 */
export function activeFast(fasts) {
    if (! Array.isArray(fasts)) return null;
    return fasts.find((f) => f && ! f.end) || null;
}

/** Elapsed seconds of a fast (to its end, or to `nowMs` if still running). */
export function fastElapsedSeconds(fast, nowMs = Date.now()) {
    if (! fast || ! fast.start) return 0;
    const start = Date.parse(fast.start);
    if (! Number.isFinite(start)) return 0;
    const end = fast.end ? Date.parse(fast.end) : nowMs;
    if (! Number.isFinite(end)) return 0;
    const s = Math.floor((end - start) / 1000);
    return s > 0 ? s : 0;
}

/** Target duration of a fast in seconds (targetHours × 3600), or 0 if unset. */
export function fastTargetSeconds(fast) {
    const h = Number(fast?.targetHours);
    return Number.isFinite(h) && h > 0 ? Math.round(h * 3600) : 0;
}

/**
 * Progress of a fast toward its target.
 *
 * @returns {{elapsed:number, target:number, fraction:number, reached:boolean}}
 */
export function fastProgress(fast, nowMs = Date.now()) {
    const target = fastTargetSeconds(fast);
    const elapsed = fastElapsedSeconds(fast, nowMs);
    const fraction = target > 0 ? Math.max(0, elapsed / target) : 0;
    return { elapsed, target, fraction, reached: target > 0 && elapsed >= target };
}

/** "Xh MMm" (e.g. 5040 → "1h 24m"). */
export function formatDuration(seconds) {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    return h + 'h ' + String(m).padStart(2, '0') + 'm';
}

/** The X:Y label for a fasting window given its fasting hours. */
export function templateLabel(targetHours) {
    const h = Number(targetHours);
    if (! Number.isFinite(h) || h <= 0) return '';
    const found = FAST_TEMPLATES.find((x) => x.targetHours === h);
    if (found) return found.key;
    const eat = Math.max(0, 24 - h);
    return h + ':' + eat;
}

/** Validate a fast record before saving (start required; end after start; sane target). */
export function isValidFast(fast) {
    if (! fast || ! fast.start) return false;
    const start = Date.parse(fast.start);
    if (! Number.isFinite(start)) return false;
    if (fast.end) {
        const end = Date.parse(fast.end);
        if (! Number.isFinite(end) || end <= start) return false;
    }
    const h = Number(fast.targetHours);
    return Number.isFinite(h) && h > 0 && h <= 48;
}
