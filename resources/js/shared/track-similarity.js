// Decide whether two Explore tracks cover the "same route" — the same loop or
// out-and-back walked more than once — so the detail view can compare them
// (pace, calories, improvement). Pragmatic + pure: a similar total distance plus
// matching start/end points (in either direction) is a good, cheap signal for a
// repeated route; a full geometry match (Fréchet/Hausdorff) is overkill here.

import { haversineM } from './track-parse';

/**
 * Whether two tracks look like the same route.
 *
 * @param {{stats?:{distanceM?:number}, points?:Array<{lat:number,lng:number}>}} a
 * @param {{stats?:{distanceM?:number}, points?:Array<{lat:number,lng:number}>}} b
 * @param {{distanceToleranceFrac?:number, endpointToleranceM?:number}} [opts]
 * @returns {boolean}
 */
export function tracksSimilar(a, b, opts = {}) {
    const distTolFrac = opts.distanceToleranceFrac ?? 0.2; // ±20 % total distance
    const endTolM = opts.endpointToleranceM ?? 150;        // start & end within 150 m

    const da = Number(a?.stats?.distanceM);
    const db = Number(b?.stats?.distanceM);
    if (! (da > 0) || ! (db > 0)) return false;
    if (Math.abs(da - db) / Math.max(da, db) > distTolFrac) return false;

    const pa = a?.points || [];
    const pb = b?.points || [];
    if (pa.length < 2 || pb.length < 2) return false;

    const a0 = pa[0], aN = pa[pa.length - 1];
    const b0 = pb[0], bN = pb[pb.length - 1];

    // Same direction: start↔start and end↔end near.
    const forward = haversineM(a0.lat, a0.lng, b0.lat, b0.lng) <= endTolM
        && haversineM(aN.lat, aN.lng, bN.lat, bN.lng) <= endTolM;
    // Reverse direction (b walked the other way): start↔end and end↔start near.
    const reverse = haversineM(a0.lat, a0.lng, bN.lat, bN.lng) <= endTolM
        && haversineM(aN.lat, aN.lng, b0.lat, b0.lng) <= endTolM;

    return forward || reverse;
}

/**
 * From a list, the tracks similar to `track` (excluding itself), plus `track`
 * itself as the first element — i.e. the full "same route" group.
 *
 * @param {object} track
 * @param {object[]} list
 * @param {object} [opts]
 * @returns {object[]}
 */
export function routeGroup(track, list, opts = {}) {
    if (! track) return [];
    const others = (Array.isArray(list) ? list : []).filter((t) => t && t.id !== track.id && tracksSimilar(track, t, opts));

    return [track, ...others];
}
