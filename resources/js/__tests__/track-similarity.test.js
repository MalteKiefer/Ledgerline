import { describe, it, expect } from 'vitest';
import { tracksSimilar, routeGroup } from '../shared/track-similarity.js';

// A little loop near (48.52, 9.05); distance ~stated.
const loopA = {
    id: 'a', stats: { distanceM: 5000 },
    points: [{ lat: 48.520, lng: 9.050 }, { lat: 48.525, lng: 9.055 }, { lat: 48.520, lng: 9.050 }],
};
// Same loop, walked again (endpoints ~coincide, similar distance).
const loopA2 = {
    id: 'a2', stats: { distanceM: 5200 },
    points: [{ lat: 48.5201, lng: 9.0501 }, { lat: 48.5252, lng: 9.0551 }, { lat: 48.5199, lng: 9.0499 }],
};
// Same route reversed.
const loopArev = {
    id: 'arev', stats: { distanceM: 5100 },
    points: [{ lat: 48.5200, lng: 9.0500 }, { lat: 48.525, lng: 9.055 }, { lat: 48.5201, lng: 9.0501 }],
};
// A different route far away.
const other = {
    id: 'b', stats: { distanceM: 5100 },
    points: [{ lat: 40.0, lng: -3.0 }, { lat: 40.01, lng: -3.01 }, { lat: 40.0, lng: -3.0 }],
};
// Same area but very different distance.
const longer = {
    id: 'c', stats: { distanceM: 12000 },
    points: [{ lat: 48.520, lng: 9.050 }, { lat: 48.525, lng: 9.055 }, { lat: 48.520, lng: 9.050 }],
};

describe('tracksSimilar', () => {
    it('matches the same loop walked twice', () => {
        expect(tracksSimilar(loopA, loopA2)).toBe(true);
    });
    it('matches a reversed walk of the same route', () => {
        expect(tracksSimilar(loopA, loopArev)).toBe(true);
    });
    it('rejects a route in a different place', () => {
        expect(tracksSimilar(loopA, other)).toBe(false);
    });
    it('rejects a much longer route from the same start', () => {
        expect(tracksSimilar(loopA, longer)).toBe(false);
    });
    it('needs stats + at least two points', () => {
        expect(tracksSimilar({ id: 'x', points: [] }, loopA)).toBe(false);
    });
});

describe('routeGroup', () => {
    it('returns the track plus its similar siblings, itself first', () => {
        const group = routeGroup(loopA, [loopA, loopA2, loopArev, other, longer]);
        expect(group.map((t) => t.id)).toEqual(['a', 'a2', 'arev']);
    });
    it('returns just the track when nothing else matches', () => {
        expect(routeGroup(loopA, [loopA, other]).map((t) => t.id)).toEqual(['a']);
    });
});
