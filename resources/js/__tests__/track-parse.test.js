import { describe, expect, it } from 'vitest';
import {
    parseTrack, parseTrackBinary, computeStats, haversineM, parseXml, smoothedAscentDescent,
} from '../shared/track-parse';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

// Three points ~111m apart in latitude at the equator prime meridian, one
// minute apart, rising 10m each step. Exact enough to assert stats by hand.
const GPX = `<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">
  <trk>
    <name>Morning Run</name>
    <trkseg>
      <trkpt lat="0.0" lon="0.0"><ele>100</ele><time>2026-01-01T08:00:00Z</time></trkpt>
      <trkpt lat="0.001" lon="0.0"><ele>110</ele><time>2026-01-01T08:01:00Z</time></trkpt>
      <trkpt lat="0.002" lon="0.0"><ele>105</ele><time>2026-01-01T08:02:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>`;

const KML = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>Hike</name>
    <Placemark>
      <LineString>
        <coordinates>
          0.0,0.0,100
          0.0,0.001,110
          0.0,0.002,105
        </coordinates>
      </LineString>
    </Placemark>
  </Document>
</kml>`;

const KML_GX = `<?xml version="1.0"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">
  <Document><name>GXTrack</name><Placemark><gx:Track>
    <when>2026-01-01T08:00:00Z</when>
    <when>2026-01-01T08:01:00Z</when>
    <gx:coord>0.0 0.0 100</gx:coord>
    <gx:coord>0.0 0.001 110</gx:coord>
  </gx:Track></Placemark></Document>
</kml>`;

const TCX = `<?xml version="1.0" encoding="UTF-8"?>
<TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
  <Activities><Activity Sport="Running"><Id>2026-01-01T08:00:00Z</Id><Lap><Track>
    <Trackpoint><Time>2026-01-01T08:00:00Z</Time><Position>
      <LatitudeDegrees>0.0</LatitudeDegrees><LongitudeDegrees>0.0</LongitudeDegrees>
    </Position><AltitudeMeters>100</AltitudeMeters></Trackpoint>
    <Trackpoint><Time>2026-01-01T08:01:00Z</Time><Position>
      <LatitudeDegrees>0.001</LatitudeDegrees><LongitudeDegrees>0.0</LongitudeDegrees>
    </Position><AltitudeMeters>110</AltitudeMeters></Trackpoint>
  </Track></Lap></Activity></Activities>
</TrainingCenterDatabase>`;

// ---------------------------------------------------------------------------
// XML walker (headless — node env has no DOMParser)
// ---------------------------------------------------------------------------

describe('parseXml (dependency-free fallback)', () => {
    it('parses elements, attributes, text and namespaced local names', () => {
        const root = parseXml('<a:root x="1"><child>hi</child><b:self y="2"/></a:root>');
        expect(root.tag).toBe('root');
        expect(root.attrs.x).toBe('1');
        expect(root.children[0].tag).toBe('child');
        expect(root.children[0].text).toBe('hi');
        expect(root.children[1].tag).toBe('self');
        expect(root.children[1].attrs.y).toBe('2');
    });
    it('decodes entities and CDATA', () => {
        const root = parseXml('<r><a>&lt;x&gt; &amp; y</a><b><![CDATA[<raw> & </raw>]]></b></r>');
        expect(root.children[0].text).toBe('<x> & y');
        expect(root.children[1].text).toBe('<raw> & </raw>');
    });
    it('throws on empty input', () => {
        expect(() => parseXml('')).toThrow();
    });
});

// ---------------------------------------------------------------------------
// haversine
// ---------------------------------------------------------------------------

describe('haversineM', () => {
    it('is ~111.19 m per 0.001 deg latitude', () => {
        expect(haversineM(0, 0, 0.001, 0)).toBeCloseTo(111.19, 1);
    });
    it('is zero for identical points', () => {
        expect(haversineM(1.23, 4.56, 1.23, 4.56)).toBe(0);
    });
});

// ---------------------------------------------------------------------------
// GPX
// ---------------------------------------------------------------------------

describe('parseTrack GPX', () => {
    const t = parseTrack(GPX, 'run.gpx');

    it('extracts name, format, points and timestamps', () => {
        expect(t.name).toBe('Morning Run');
        expect(t.sourceFormat).toBe('gpx');
        expect(t.points).toHaveLength(3);
        expect(t.points[0]).toEqual({ lat: 0, lng: 0, ele: 100, t: Date.parse('2026-01-01T08:00:00Z') });
        expect(t.startedAt).toBe('2026-01-01T08:00:00.000Z');
        expect(t.endedAt).toBe('2026-01-01T08:02:00.000Z');
    });

    it('computes bbox', () => {
        expect(t.bbox).toEqual({ minLat: 0, minLng: 0, maxLat: 0.002, maxLng: 0 });
    });

    it('computes stats (distance, ascent/descent, duration, speed)', () => {
        // Two 0.001-deg lat segments ≈ 222.4 m total.
        expect(t.stats.distanceM).toBeCloseTo(222.4, 0);
        expect(t.stats.pointCount).toBe(3);
        // +10 then -5 → ascent 10, descent 5.
        expect(t.stats.ascentM).toBe(10);
        expect(t.stats.descentM).toBe(5);
        expect(t.stats.minEleM).toBe(100);
        expect(t.stats.maxEleM).toBe(110);
        expect(t.stats.durationTotalS).toBe(120);
        // Each segment ~1.85 m/s > threshold → all moving.
        expect(t.stats.durationMovingS).toBe(120);
        expect(t.stats.avgSpeedMps).toBeCloseTo(222.4 / 120, 2);
        expect(t.stats.elevationProfile).toHaveLength(3);
        expect(t.stats.elevationProfile[0]).toEqual({ distM: 0, eleM: 100 });
    });

    it('sniffs GPX without an extension', () => {
        expect(parseTrack(GPX).sourceFormat).toBe('gpx');
    });
});

// ---------------------------------------------------------------------------
// KML
// ---------------------------------------------------------------------------

describe('parseTrack KML', () => {
    it('parses LineString coordinates (lng,lat,ele; no time)', () => {
        const t = parseTrack(KML, 'hike.kml');
        expect(t.sourceFormat).toBe('kml');
        expect(t.name).toBe('Hike');
        expect(t.points).toHaveLength(3);
        expect(t.points[0]).toEqual({ lat: 0, lng: 0, ele: 100, t: null });
        expect(t.points[2]).toEqual({ lat: 0.002, lng: 0, ele: 105, t: null });
        expect(t.startedAt).toBeNull();
        expect(t.bbox).toEqual({ minLat: 0, minLng: 0, maxLat: 0.002, maxLng: 0 });
    });

    it('parses gx:Track with parallel when/coord lists', () => {
        const t = parseTrack(KML_GX, 'gx.kml');
        expect(t.points).toHaveLength(2);
        expect(t.points[0]).toEqual({ lat: 0, lng: 0, ele: 100, t: Date.parse('2026-01-01T08:00:00Z') });
        // gx:coord order is "lon lat alt" → second coord (0.0 0.001 110) is lat 0.001.
        expect(t.points[1].lat).toBe(0.001);
        expect(t.points[1].lng).toBe(0);
        expect(t.startedAt).toBe('2026-01-01T08:00:00.000Z');
    });
});

// ---------------------------------------------------------------------------
// TCX
// ---------------------------------------------------------------------------

describe('parseTrack TCX', () => {
    it('parses Trackpoint Position/Altitude/Time', () => {
        const t = parseTrack(TCX, 'workout.tcx');
        expect(t.sourceFormat).toBe('tcx');
        expect(t.points).toHaveLength(2);
        expect(t.points[0]).toEqual({ lat: 0, lng: 0, ele: 100, t: Date.parse('2026-01-01T08:00:00Z') });
        expect(t.points[1].lat).toBe(0.001);
        expect(t.stats.ascentM).toBe(10);
    });
});

// ---------------------------------------------------------------------------
// Broken / unknown input
// ---------------------------------------------------------------------------

describe('parseTrack error handling', () => {
    it('throws on empty input', () => {
        expect(() => parseTrack('', 'x.gpx')).toThrow(/empty/i);
    });
    it('throws on unknown format', () => {
        expect(() => parseTrack('just some text', 'notes.txt')).toThrow(/unrecognised/i);
    });
    it('throws on a GPX with no points', () => {
        expect(() => parseTrack('<gpx><trk><name>x</name></trk></gpx>', 'e.gpx')).toThrow(/no track points/i);
    });
    it('throws on malformed XML (unbalanced tag)', () => {
        expect(() => parseTrack('<gpx><trk></gpxx>', 'b.gpx')).toThrow();
    });
});

// ---------------------------------------------------------------------------
// computeStats edge cases
// ---------------------------------------------------------------------------

describe('computeStats', () => {
    it('returns zeroed stats for an empty list', () => {
        const s = computeStats([]);
        expect(s.pointCount).toBe(0);
        expect(s.distanceM).toBe(0);
        expect(s.minEleM).toBeNull();
        expect(s.elevationProfile).toEqual([]);
    });
    it('excludes GPS-glitch speeds from maxSpeed but keeps distance', () => {
        // Two points 1000 km apart, 1 ms apart → implausible speed, excluded.
        const s = computeStats([
            { lat: 0, lng: 0, ele: null, t: 0 },
            { lat: 9, lng: 0, ele: null, t: 1 },
        ]);
        expect(s.distanceM).toBeGreaterThan(900000);
        expect(s.maxSpeedMps).toBe(0);
    });
});

// ---------------------------------------------------------------------------
// FIT (minimal binary decoder) — hand-built buffer, 2 record messages
// ---------------------------------------------------------------------------

function buildFit(records) {
    // Definition message (local type 0, global 20 "record") with 4 fields:
    //   253 timestamp uint32 (0x86), 0 position_lat sint32 (0x85),
    //   1 position_long sint32 (0x85), 2 altitude uint16 (0x84)
    const defBody = [
        0x40,             // record header: definition, local type 0
        0x00,             // reserved
        0x00,             // arch: little-endian
        20, 0x00,         // global message number 20
        4,                // field count
        253, 4, 0x86,     // timestamp
        0, 4, 0x85,       // position_lat
        1, 4, 0x85,       // position_long
        2, 2, 0x84,       // altitude
    ];

    const dataBytes = [];
    for (const r of records) {
        dataBytes.push(0x00); // data message header, local type 0
        pushU32(dataBytes, r.ts);
        pushI32(dataBytes, r.latSc);
        pushI32(dataBytes, r.lngSc);
        pushU16(dataBytes, r.altRaw);
    }

    const body = [...defBody, ...dataBytes];
    const dataSize = body.length;

    // 12-byte header (no CRC): size, protoVer, profileVer(u16 le), dataSize(u32 le), ".FIT"
    const header = [12, 0x10, 0x00, 0x00];
    pushU32(header, dataSize);
    header.push(0x2e, 0x46, 0x49, 0x54); // ".FIT"

    // 2-byte trailing CRC (ignored by the decoder — dataSize bounds the scan).
    return new Uint8Array([...header, ...body, 0x00, 0x00]);
}

function pushU16(a, v) { a.push(v & 0xff, (v >> 8) & 0xff); }
function pushU32(a, v) { a.push(v & 0xff, (v >> 8) & 0xff, (v >> 16) & 0xff, (v >>> 24) & 0xff); }
function pushI32(a, v) { pushU32(a, v >>> 0); }

describe('parseTrackBinary FIT', () => {
    // 0.001 deg in semicircles = round(0.001 / (180 / 2^31)).
    const SC = 180 / 2 ** 31;
    const latSc0 = Math.round(0 / SC);
    const latSc1 = Math.round(0.001 / SC);
    // FIT timestamp = seconds since 1989-12-31; 0 and 60.
    const buf = buildFit([
        { ts: 0, latSc: latSc0, lngSc: 0, altRaw: (100 + 500) * 5 }, // altitude enc: (m+500)*5
        { ts: 60, latSc: latSc1, lngSc: 0, altRaw: (110 + 500) * 5 },
    ]);

    it('decodes lat/lng (semicircles), altitude and timestamp', () => {
        const t = parseTrackBinary(buf, 'ride.fit');
        expect(t.sourceFormat).toBe('fit');
        expect(t.points).toHaveLength(2);
        expect(t.points[0].lat).toBeCloseTo(0, 6);
        expect(t.points[0].ele).toBeCloseTo(100, 3);
        expect(t.points[1].lat).toBeCloseTo(0.001, 6);
        expect(t.points[1].ele).toBeCloseTo(110, 3);
        // FIT epoch 1989-12-31T00:00:00Z + 0s.
        expect(t.points[0].t).toBe(Date.parse('1989-12-31T00:00:00Z'));
        expect(t.points[1].t).toBe(Date.parse('1989-12-31T00:01:00Z'));
        expect(t.stats.ascentM).toBeCloseTo(10, 3);
    });

    it('throws on a non-FIT / truncated buffer', () => {
        expect(() => parseTrackBinary(new Uint8Array(4), 'x.fit')).toThrow();
        const bad = new Uint8Array(20); // valid length, no ".FIT" signature
        bad[0] = 12;
        expect(() => parseTrackBinary(bad, 'x.fit')).toThrow(/FIT/);
    });
});

describe('smoothedAscentDescent', () => {
    it('discards sub-threshold GPS jitter', () => {
        // Elevation oscillating ±3 m around 100 — pure noise, no real climb.
        const pts = [100, 103, 98, 102, 99, 101, 100].map((ele) => ({ ele }));
        const r = smoothedAscentDescent(pts);
        expect(r.ascentM).toBe(0);
        expect(r.descentM).toBe(0);
    });

    it('counts a genuine climb and drop past the dead-band', () => {
        const pts = [100, 130, 110].map((ele) => ({ ele }));
        const r = smoothedAscentDescent(pts);
        expect(r.ascentM).toBe(30);
        expect(r.descentM).toBe(20);
    });

    it('captures a slow climb made of many sub-threshold steps', () => {
        // +2 m each step, 10 steps → +20 m real gain despite no single step ≥5.
        const pts = [];
        for (let e = 100; e <= 120; e += 2) pts.push({ ele: e });
        const r = smoothedAscentDescent(pts);
        // Captured within one dead-band of the true +20 (a sub-threshold tail
        // stays uncommitted) — the key point: it is NOT discarded as noise.
        expect(r.ascentM).toBeGreaterThan(14);
        expect(r.ascentM).toBeLessThanOrEqual(20);
        expect(r.descentM).toBe(0);
    });

    it('ignores points without elevation', () => {
        const pts = [{ ele: 100 }, { ele: null }, {}, { ele: 110 }];
        expect(smoothedAscentDescent(pts).ascentM).toBe(10);
    });
});
