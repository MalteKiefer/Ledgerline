import { describe, expect, it } from 'vitest';
import { buildPlannedTrack, hasElevation, downsampleProfile } from '../shared/explore-detail';

describe('buildPlannedTrack', () => {
    it('returns null for fewer than two valid waypoints', () => {
        expect(buildPlannedTrack([], 'x', 'id1', null)).toBeNull();
        expect(buildPlannedTrack([[1, 2]], 'x', 'id1', null)).toBeNull();
        expect(buildPlannedTrack([[NaN, 2], [1, 2]], 'x', 'id1', null)).toBeNull();
    });

    it('builds a planned track with distance-only stats', () => {
        // Two points ~111m apart in latitude at the equator.
        const t = buildPlannedTrack([[0, 0], [0.001, 0]], 'My Route', 'id42', '2026-01-01T00:00:00Z');
        expect(t).not.toBeNull();
        expect(t.id).toBe('id42');
        expect(t.name).toBe('My Route');
        expect(t.sourceFormat).toBe('planned');
        expect(t.rawBlobId).toBeNull();
        expect(t.createdAt).toBe('2026-01-01T00:00:00Z');
        expect(t.points).toHaveLength(2);
        expect(t.points[0]).toEqual({ lat: 0, lng: 0, ele: null, t: null });
        // Distance is ~111m; durations/ascent/speeds are zero (no time/ele).
        expect(t.stats.distanceM).toBeGreaterThan(100);
        expect(t.stats.distanceM).toBeLessThan(120);
        expect(t.stats.durationTotalS).toBe(0);
        expect(t.stats.ascentM).toBe(0);
        expect(t.stats.maxSpeedMps).toBe(0);
        expect(t.stats.minEleM).toBeNull();
    });

    it('computes a bbox over the waypoints and defaults the name', () => {
        const t = buildPlannedTrack([[10, 20], [12, 18], [11, 25]], '', 'id', null);
        expect(t.name).toBe('Route');
        expect(t.bbox).toEqual({ minLat: 10, minLng: 18, maxLat: 12, maxLng: 25 });
    });

    it('drops malformed waypoints before counting', () => {
        const t = buildPlannedTrack([[0, 0], ['a', 'b'], [1, 1]], 'x', 'id', null);
        expect(t.points).toHaveLength(2);
    });
});

describe('hasElevation', () => {
    it('is false with fewer than two elevation samples', () => {
        expect(hasElevation([])).toBe(false);
        expect(hasElevation([{ distM: 0, eleM: null }, { distM: 1, eleM: null }])).toBe(false);
        expect(hasElevation([{ distM: 0, eleM: 100 }, { distM: 1, eleM: null }])).toBe(false);
    });
    it('is true with two or more real elevation samples', () => {
        expect(hasElevation([{ distM: 0, eleM: 100 }, { distM: 1, eleM: 110 }])).toBe(true);
    });
    it('ignores non-finite elevations', () => {
        expect(hasElevation([{ distM: 0, eleM: Infinity }, { distM: 1, eleM: 110 }])).toBe(false);
    });
});

describe('downsampleProfile', () => {
    it('returns empty arrays for an empty profile', () => {
        expect(downsampleProfile([])).toEqual({ xs: [], ys: [], idx: [] });
    });

    it('passes profiles at or below the cap through unchanged (km conversion)', () => {
        const profile = [
            { distM: 0, eleM: 100 },
            { distM: 500, eleM: 110 },
            { distM: 2000, eleM: 120 },
        ];
        const r = downsampleProfile(profile, 400);
        expect(r.xs).toEqual([0, 0.5, 2]);
        expect(r.ys).toEqual([100, 110, 120]);
        expect(r.idx).toEqual([0, 1, 2]);
    });

    it('downsamples large profiles, keeping first and last', () => {
        const profile = [];
        for (let i = 0; i < 1000; i++) profile.push({ distM: i * 10, eleM: i });
        const r = downsampleProfile(profile, 100);
        expect(r.xs.length).toBeLessThanOrEqual(100);
        expect(r.idx[0]).toBe(0);
        expect(r.idx[r.idx.length - 1]).toBe(999);
        // Indices are strictly increasing.
        for (let i = 1; i < r.idx.length; i++) expect(r.idx[i]).toBeGreaterThan(r.idx[i - 1]);
    });

    it('maps null elevations to null (planned routes)', () => {
        const profile = [
            { distM: 0, eleM: null },
            { distM: 1000, eleM: null },
        ];
        const r = downsampleProfile(profile);
        expect(r.ys).toEqual([null, null]);
        expect(r.xs).toEqual([0, 1]);
    });
});
