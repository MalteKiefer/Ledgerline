// Pure, headless-testable helpers for the Explore track-detail view.
//
// Zero-knowledge: these operate purely over already-decrypted track data in the
// browser. No DOM, no network, no Date.now()/random — deterministic functions of
// their inputs, so Vitest can exercise them in the `node` environment.

import { computeStats } from './track-parse';

/**
 * Build a track record for a user-planned route (a hand-drawn polyline). Planned
 * routes carry no elevation and no time — every point is {lat,lng,ele:null,t:null}
 * — so computeStats yields distance only (durations/ascent/speeds stay 0).
 *
 * @param {Array<[number,number]>} waypoints  ordered [lat,lng] pairs
 * @param {string} name
 * @param {string} id
 * @param {string} createdAt  ISO string (caller supplies — keeps this pure)
 * @returns {object|null} track record, or null when fewer than 2 waypoints
 */
export function buildPlannedTrack(waypoints, name, id, createdAt) {
    const wp = Array.isArray(waypoints) ? waypoints : [];
    const points = wp
        .filter((p) => Array.isArray(p) && Number.isFinite(p[0]) && Number.isFinite(p[1]))
        .map(([lat, lng]) => ({ lat, lng, ele: null, t: null }));
    if (points.length < 2) return null;

    let minLat = Infinity;
    let minLng = Infinity;
    let maxLat = -Infinity;
    let maxLng = -Infinity;
    for (const p of points) {
        if (p.lat < minLat) minLat = p.lat;
        if (p.lat > maxLat) maxLat = p.lat;
        if (p.lng < minLng) minLng = p.lng;
        if (p.lng > maxLng) maxLng = p.lng;
    }

    return {
        id,
        name: name || 'Route',
        sourceFormat: 'planned',
        startedAt: null,
        endedAt: null,
        points,
        stats: computeStats(points),
        bbox: { minLat, minLng, maxLat, maxLng },
        rawBlobId: null,
        rawBlobKey: null,
        createdAt: createdAt || null,
    };
}

/**
 * Convert a routing service's elevation array ([{distM, eleM}]) into the internal
 * elevation-profile shape ([{distM, eleM}]) used by computeStats/hasElevation and
 * the uPlot chart. Filters out malformed entries and keeps only finite distances,
 * mapping non-finite elevations to null. Returns [] for missing/empty input.
 *
 * @param {Array<{distM:number, eleM:(number|null)}>|null|undefined} elevation
 * @returns {Array<{distM:number, eleM:(number|null)}>}
 */
export function normalizeRouteElevation(elevation) {
    const src = Array.isArray(elevation) ? elevation : [];
    const out = [];
    for (const pt of src) {
        if (!pt || typeof pt !== 'object') continue;
        const distM = Number(pt.distM);
        if (!Number.isFinite(distM)) continue;
        const ele = Number(pt.eleM);
        out.push({ distM, eleM: Number.isFinite(ele) ? ele : null });
    }
    return out;
}

/**
 * Aggregate a routing service's per-segment surface list ([{surface, distM}]) into
 * a compact breakdown: one row per distinct surface with the summed distance,
 * sorted by distance descending. Unknown/blank surface names collapse into a
 * single 'unknown' bucket. Returns [] for missing/empty input.
 *
 * @param {Array<{surface:string, distM:number}>|null|undefined} surfaces
 * @returns {Array<{surface:string, distM:number}>}
 */
export function aggregateSurfaces(surfaces) {
    const src = Array.isArray(surfaces) ? surfaces : [];
    const totals = new Map();
    for (const seg of src) {
        if (!seg || typeof seg !== 'object') continue;
        const distM = Number(seg.distM);
        if (!Number.isFinite(distM) || distM <= 0) continue;
        const name = (typeof seg.surface === 'string' && seg.surface.trim()) ? seg.surface.trim() : 'unknown';
        totals.set(name, (totals.get(name) || 0) + distM);
    }
    return [...totals.entries()]
        .map(([surface, dist]) => ({ surface, distM: dist }))
        .sort((a, b) => b.distM - a.distM);
}

/**
 * True when the profile carries at least two real elevation samples — i.e. an
 * elevation chart is worth drawing. Planned routes / GPS-only tracks return false.
 *
 * @param {Array<{distM:number, eleM:(number|null)}>} profile
 * @returns {boolean}
 */
export function hasElevation(profile) {
    const p = Array.isArray(profile) ? profile : [];
    let n = 0;
    for (const pt of p) if (pt && pt.eleM != null && Number.isFinite(pt.eleM)) n++;
    return n >= 2;
}

/**
 * Downsample an elevation profile to at most `maxPoints` samples for charting,
 * keeping the first and last sample and picking evenly spaced indices between.
 * Returns { xs, ys, idx } as parallel arrays plus the original point indices each
 * sample maps to (so a chart hover can find the matching track point). Distances
 * are converted to kilometres for the x-axis.
 *
 * @param {Array<{distM:number, eleM:(number|null)}>} profile
 * @param {number} [maxPoints=400]
 * @returns {{xs:number[], ys:Array<number|null>, idx:number[]}}
 */
export function downsampleProfile(profile, maxPoints = 400) {
    const p = Array.isArray(profile) ? profile : [];
    const n = p.length;
    if (n === 0) return { xs: [], ys: [], idx: [] };

    const cap = Math.max(2, Math.floor(maxPoints));
    let indices;
    if (n <= cap) {
        indices = [];
        for (let i = 0; i < n; i++) indices.push(i);
    } else {
        indices = [];
        const step = (n - 1) / (cap - 1);
        for (let i = 0; i < cap; i++) indices.push(Math.round(i * step));
        // De-dup any collisions from rounding while preserving order.
        indices = indices.filter((v, i) => i === 0 || v !== indices[i - 1]);
    }

    const xs = [];
    const ys = [];
    const idx = [];
    for (const i of indices) {
        xs.push(p[i].distM / 1000);
        ys.push(p[i].eleM == null || !Number.isFinite(p[i].eleM) ? null : p[i].eleM);
        idx.push(i);
    }
    return { xs, ys, idx };
}
