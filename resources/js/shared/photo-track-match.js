// Pure, headless-testable photo→track matcher for the "Explore" map module.
//
// Zero-knowledge: the match runs entirely in the browser over already-decrypted
// photo metadata and parsed tracks. No DOM, no network, no Date.now/random — the
// result is a deterministic function of its inputs.
//
// The cascade decides where a photo sits on the map:
//   1. EXIF GPS present  → 'exif'         (assign to a track only when a track
//                                           point is within BOTH tolerances)
//   2. no EXIF GPS        → 'interpolated' (assign by time; position interpolated
//                                           along the track at the photo's time)
//   3. neither            → 'none'

import { haversineM } from './track-parse';

/**
 * @typedef {{lat:number,lng:number,ele:(number|null),t:(number|null)}} TrackPoint
 * @typedef {{id:string, points:TrackPoint[]}} MatchTrack
 */

/**
 * Linear interpolation of a position along a track at time `t` (epoch ms).
 * Finds the two points that bracket `t` and interpolates lat/lng (and ele)
 * proportionally by time. Returns null when `t` is outside every segment or the
 * track has no timed points.
 *
 * @param {TrackPoint[]} points  ordered by time
 * @param {number} t             epoch milliseconds
 * @returns {{lat:number,lng:number,ele:(number|null)}|null}
 */
export function interpolatePosition(points, t) {
    if (!Array.isArray(points) || points.length === 0 || typeof t !== 'number') return null;

    for (let i = 0; i < points.length - 1; i++) {
        const a = points[i];
        const b = points[i + 1];
        if (typeof a.t !== 'number' || typeof b.t !== 'number') continue;
        if (t < a.t || t > b.t) continue;

        const span = b.t - a.t;
        const frac = span > 0 ? (t - a.t) / span : 0;
        const lerp = (x, y) => x + (y - x) * frac;
        const ele = (typeof a.ele === 'number' && typeof b.ele === 'number')
            ? lerp(a.ele, b.ele)
            : (typeof a.ele === 'number' ? a.ele : (typeof b.ele === 'number' ? b.ele : null));

        return { lat: lerp(a.lat, b.lat), lng: lerp(a.lng, b.lng), ele };
    }

    // Exact hit on the first/last timed point (degenerate zero-length segment).
    for (const p of points) {
        if (typeof p.t === 'number' && p.t === t) {
            return { lat: p.lat, lng: p.lng, ele: typeof p.ele === 'number' ? p.ele : null };
        }
    }
    return null;
}

/** [minT, maxT] of a track's timed points, or null when it has none. */
function timeSpan(points) {
    let min = null;
    let max = null;
    for (const p of points) {
        if (typeof p.t !== 'number') continue;
        if (min === null || p.t < min) min = p.t;
        if (max === null || p.t > max) max = p.t;
    }
    return min === null ? null : [min, max];
}

/**
 * Match one photo to the best track via the cascade described above.
 *
 * @param {{photoLat:(number|null), photoLng:(number|null), photoTime:(number|null)}} photo
 *   photoTime = epoch milliseconds (or null).
 * @param {MatchTrack[]} tracks
 * @param {{timeToleranceS:number, distanceToleranceM:number}} opts
 * @returns {{trackId:(string|null), source:'exif'|'interpolated'|'manual'|'none', lat?:number, lng?:number}}
 */
export function matchPhotoToTracks(photo, tracks, opts) {
    const list = Array.isArray(tracks) ? tracks : [];
    const timeTolMs = Math.max(0, (opts?.timeToleranceS ?? 0)) * 1000;
    const distTolM = Math.max(0, opts?.distanceToleranceM ?? 0);

    const hasGps = photo
        && typeof photo.photoLat === 'number' && Number.isFinite(photo.photoLat)
        && typeof photo.photoLng === 'number' && Number.isFinite(photo.photoLng);
    const hasTime = photo && typeof photo.photoTime === 'number' && Number.isFinite(photo.photoTime);

    // --- 1) EXIF GPS: nearest track point within the distance tolerance -------
    // Spatial proximity is the primary signal: a photo taken ON a track almost
    // certainly belongs to it, even if the camera clock is off or in the wrong
    // timezone. Time is only a TIEBREAKER — among equally-near candidates the
    // temporally-closest wins (disambiguating two tours over the same ground) —
    // never a hard gate that drops a spatially-perfect match.
    if (hasGps) {
        let best = null; // { trackId, distM, dtMs }
        for (const track of list) {
            const pts = track?.points || [];
            for (const p of pts) {
                const d = haversineM(photo.photoLat, photo.photoLng, p.lat, p.lng);
                if (d > distTolM) continue;
                const dtMs = (hasTime && typeof p.t === 'number') ? Math.abs(p.t - photo.photoTime) : Infinity;
                if (best === null || d < best.distM || (d === best.distM && dtMs < best.dtMs)) {
                    best = { trackId: track.id, distM: d, dtMs };
                }
            }
        }
        if (best) return { trackId: best.trackId, source: 'exif', lat: photo.photoLat, lng: photo.photoLng };
        // GPS present but no track nearby → still an EXIF-positioned photo.
        return { trackId: null, source: 'exif', lat: photo.photoLat, lng: photo.photoLng };
    }

    // --- 2) No GPS: assign by time span, interpolate the position -------------
    if (hasTime) {
        let best = null; // { trackId, pos, dt }
        for (const track of list) {
            const pts = track?.points || [];
            const span = timeSpan(pts);
            if (!span) continue;
            const [minT, maxT] = span;
            if (photo.photoTime < minT - timeTolMs || photo.photoTime > maxT + timeTolMs) continue;

            const pos = interpolatePosition(pts, photo.photoTime);
            if (!pos) continue;
            // Distance from the span midpoint is a stable tiebreaker across tracks.
            const dt = Math.min(Math.abs(photo.photoTime - minT), Math.abs(photo.photoTime - maxT));
            if (best === null || dt < best.dt) best = { trackId: track.id, pos, dt };
        }
        if (best) {
            return { trackId: best.trackId, source: 'interpolated', lat: best.pos.lat, lng: best.pos.lng };
        }
    }

    // --- 3) Nothing matched ---------------------------------------------------
    return { trackId: null, source: 'none' };
}
