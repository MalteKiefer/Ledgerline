import { describe, expect, it } from 'vitest';
import { matchPhotoToTracks, interpolatePosition } from '../shared/photo-track-match';

// A single track: 3 points, 0.001 deg lat apart, 60 s apart.
const T0 = Date.parse('2026-01-01T08:00:00Z');
const track = {
    id: 'trk1',
    points: [
        { lat: 0.000, lng: 0, ele: 100, t: T0 },
        { lat: 0.001, lng: 0, ele: 110, t: T0 + 60000 },
        { lat: 0.002, lng: 0, ele: 105, t: T0 + 120000 },
    ],
};

const OPTS = { timeToleranceS: 3600, distanceToleranceM: 100 };

describe('interpolatePosition', () => {
    it('interpolates linearly between the bracketing points by time', () => {
        // 30 s into the first 60 s segment → halfway: lat 0.0005, ele 105.
        const pos = interpolatePosition(track.points, T0 + 30000);
        expect(pos.lat).toBeCloseTo(0.0005, 9);
        expect(pos.lng).toBe(0);
        expect(pos.ele).toBeCloseTo(105, 9);
    });
    it('returns the exact point at a segment boundary', () => {
        const pos = interpolatePosition(track.points, T0 + 60000);
        expect(pos.lat).toBeCloseTo(0.001, 9);
    });
    it('returns null outside the track time span', () => {
        expect(interpolatePosition(track.points, T0 - 1)).toBeNull();
        expect(interpolatePosition(track.points, T0 + 120001)).toBeNull();
    });
    it('returns null for empty points or non-numeric time', () => {
        expect(interpolatePosition([], T0)).toBeNull();
        expect(interpolatePosition(track.points, null)).toBeNull();
    });
});

describe('matchPhotoToTracks — EXIF GPS cascade (1)', () => {
    it('assigns to a track when a point is within both time + distance tol', () => {
        const r = matchPhotoToTracks(
            { photoLat: 0.001, photoLng: 0, photoTime: T0 + 60000 },
            [track],
            OPTS,
        );
        expect(r).toEqual({ trackId: 'trk1', source: 'exif', lat: 0.001, lng: 0 });
    });

    it('keeps EXIF source but no trackId when out of distance tolerance', () => {
        // 0.01 deg ≈ 1.1 km away, well beyond 100 m.
        const r = matchPhotoToTracks(
            { photoLat: 0.01, photoLng: 0, photoTime: T0 + 60000 },
            [track],
            OPTS,
        );
        expect(r.source).toBe('exif');
        expect(r.trackId).toBeNull();
        expect(r.lat).toBe(0.01);
    });

    it('matches a spatially-near GPS photo regardless of the time gate', () => {
        const r = matchPhotoToTracks(
            { photoLat: 0.001, photoLng: 0, photoTime: T0 + 60000 },
            [track],
            { timeToleranceS: 1, distanceToleranceM: 100 },
        );
        expect(r.trackId).toBe('trk1');

        // A photo taken ON the track but with a wildly wrong clock (80 s past the
        // last point, time tol 1 s) STILL matches — spatial proximity wins; time
        // is only a tiebreaker, never a hard gate that drops a good match.
        const r2 = matchPhotoToTracks(
            { photoLat: 0.0015, photoLng: 0, photoTime: T0 + 200000 },
            [track],
            { timeToleranceS: 1, distanceToleranceM: 100 },
        );
        expect(r2.trackId).toBe('trk1');
        expect(r2.source).toBe('exif');
    });

    it('uses time only to disambiguate two tracks over the same ground', () => {
        const near = { id: 'a', points: [{ lat: 0.001, lng: 0, t: T0 }] };
        const far = { id: 'b', points: [{ lat: 0.001, lng: 0, t: T0 + 3_600_000 }] };
        // Same position; the photo's time is closest to track b.
        const r = matchPhotoToTracks(
            { photoLat: 0.001, photoLng: 0, photoTime: T0 + 3_600_000 },
            [near, far],
            { timeToleranceS: 60, distanceToleranceM: 100 },
        );
        expect(r.trackId).toBe('b');
    });
});

describe('matchPhotoToTracks — interpolation cascade (2)', () => {
    it('interpolates position by timestamp when the photo has no GPS', () => {
        const r = matchPhotoToTracks(
            { photoLat: null, photoLng: null, photoTime: T0 + 30000 },
            [track],
            OPTS,
        );
        expect(r.source).toBe('interpolated');
        expect(r.trackId).toBe('trk1');
        // Hand-computed: halfway through segment 1 → lat 0.0005.
        expect(r.lat).toBeCloseTo(0.0005, 9);
        expect(r.lng).toBe(0);
    });

    it('honours the time tolerance around the span edges', () => {
        // 30 min before start, within the 3600 s tol, but interpolation only
        // works INSIDE a segment → no bracketing pair → none.
        const r = matchPhotoToTracks(
            { photoLat: null, photoLng: null, photoTime: T0 - 1800000 },
            [track],
            OPTS,
        );
        expect(r.source).toBe('none');
    });
});

describe('matchPhotoToTracks — none cascade (3)', () => {
    it('returns none when no GPS and time is outside every span', () => {
        const r = matchPhotoToTracks(
            { photoLat: null, photoLng: null, photoTime: T0 + 10 * 3600 * 1000 },
            [track],
            OPTS,
        );
        expect(r).toEqual({ trackId: null, source: 'none' });
    });

    it('returns none for empty tracks with no GPS', () => {
        const r = matchPhotoToTracks(
            { photoLat: null, photoLng: null, photoTime: T0 },
            [],
            OPTS,
        );
        expect(r).toEqual({ trackId: null, source: 'none' });
    });

    it('returns none when the photo has neither GPS nor time', () => {
        const r = matchPhotoToTracks(
            { photoLat: null, photoLng: null, photoTime: null },
            [track],
            OPTS,
        );
        expect(r).toEqual({ trackId: null, source: 'none' });
    });

    it('EXIF-positions even with empty tracks (GPS present)', () => {
        const r = matchPhotoToTracks(
            { photoLat: 5, photoLng: 6, photoTime: null },
            [],
            OPTS,
        );
        expect(r).toEqual({ trackId: null, source: 'exif', lat: 5, lng: 6 });
    });
});
