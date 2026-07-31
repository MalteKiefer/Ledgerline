import { describe, it, expect } from 'vitest';
import { buildGpx, gpxFilename } from '../shared/track-export.js';

describe('buildGpx', () => {
    it('emits a valid GPX doc with points, elevation and time', () => {
        const gpx = buildGpx({
            name: 'Morning hike',
            points: [
                { lat: 48.5216, lng: 9.0576, ele: 340, t: 1_700_000_000_000 },
                { lat: 48.5220, lng: 9.0580, ele: 345, t: 1_700_000_060_000 },
            ],
        });
        expect(gpx).toContain('<gpx version="1.1" creator="Ledgerline"');
        expect(gpx).toContain('<name>Morning hike</name>');
        expect(gpx).toContain('<trkpt lat="48.5216000" lon="9.0576000">');
        expect(gpx).toContain('<ele>340</ele>');
        expect(gpx).toContain('<time>2023-11-14T22:13:20.000Z</time>');
        expect(gpx.match(/<trkpt/g)).toHaveLength(2);
    });

    it('omits ele/time when absent and skips invalid points', () => {
        const gpx = buildGpx({
            name: 'Planned',
            points: [
                { lat: 1, lng: 2 },
                { lat: null, lng: 2 }, // skipped
            ],
        });
        expect(gpx).toContain('<trkpt lat="1.0000000" lon="2.0000000">');
        expect(gpx).not.toContain('<ele>');
        expect(gpx).not.toContain('<time>');
        expect(gpx.match(/<trkpt/g)).toHaveLength(1);
    });

    it('escapes the name', () => {
        const gpx = buildGpx({ name: 'A & B <x>', points: [{ lat: 1, lng: 2 }] });
        expect(gpx).toContain('<name>A &amp; B &lt;x&gt;</name>');
    });

    it('returns empty string for a track with no usable points', () => {
        expect(buildGpx({ name: 'x', points: [] })).toBe('');
        expect(buildGpx({ name: 'x', points: [{ lat: 'nope', lng: 2 }] })).toBe('');
    });
});

describe('gpxFilename', () => {
    it('sanitises and appends .gpx', () => {
        expect(gpxFilename('Morning hike')).toBe('Morning_hike.gpx');
        expect(gpxFilename('a/b:c')).toBe('a_b_c.gpx');
        expect(gpxFilename('tour.gpx')).toBe('tour.gpx');
        expect(gpxFilename('')).toBe('track.gpx');
    });
});
