// Pure, headless-testable track parsing for the "Explore" map module.
//
// Zero-knowledge: every byte of a GPX/KML/TCX/FIT track is parsed HERE, in the
// browser — the server never sees track plaintext. Nothing in this file touches
// the DOM, network, Date.now() or random; it is deterministic and Vitest-safe.
//
// The XML formats (GPX/KML/TCX) are parsed with the browser's native DOMParser
// when it exists, and otherwise with a tiny dependency-free XML walker so the
// same code runs headless under the Vitest `node` environment (which has no
// DOMParser and we deliberately bundle no XML library — no external CDN, no dep).
//
// FIT is a binary container → parsed from an ArrayBuffer/Uint8Array. A minimal
// record-message decoder recovers lat/lng (semicircles→deg), altitude and
// timestamp. See the FIT LIMITATION note at the bottom of this file.

// ---------------------------------------------------------------------------
// Geo helpers
// ---------------------------------------------------------------------------

const EARTH_RADIUS_M = 6371000;
const DEG2RAD = Math.PI / 180;

/**
 * Great-circle distance between two lat/lng points, in metres (haversine).
 * @param {number} lat1 @param {number} lng1 @param {number} lat2 @param {number} lng2
 * @returns {number}
 */
export function haversineM(lat1, lng1, lat2, lng2) {
    const dLat = (lat2 - lat1) * DEG2RAD;
    const dLng = (lng2 - lng1) * DEG2RAD;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(lat1 * DEG2RAD) * Math.cos(lat2 * DEG2RAD) * Math.sin(dLng / 2) ** 2;
    return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(a)));
}

// ---------------------------------------------------------------------------
// Statistics
// ---------------------------------------------------------------------------

// A point below this ground speed (m/s) is treated as "stopped" for moving-time.
const MOVING_SPEED_THRESHOLD_MPS = 0.5;
// Ignore implausible instantaneous speeds (GPS glitches) when computing maxSpeed.
const MAX_PLAUSIBLE_SPEED_MPS = 150;
// Dead-band for ascent/descent: GPS/barometric elevation jitters ±5-10 m per
// fix, so summing raw per-point deltas inflates total climb wildly (a flat loop
// with a 30 m elevation span can show 300 m of "ascent"). Only commit a climb
// or drop once the change from the last committed elevation clears this band.
const ELEVATION_DEADBAND_M = 5;

/**
 * Total ascent + descent over a point list with GPS-noise smoothing.
 *
 * Hysteresis: hold a reference elevation and only count a gain/loss once the
 * signed change from that reference clears ELEVATION_DEADBAND_M — then the full
 * change is committed and the reference advances. Slow real climbs made of many
 * sub-threshold steps are still captured (the reference lags but catches up);
 * pure jitter under the band is discarded.
 *
 * @param {Array<{ele?:number}>} points
 * @param {number} [thresholdM]
 * @returns {{ascentM:number, descentM:number}}
 */
export function smoothedAscentDescent(points, thresholdM = ELEVATION_DEADBAND_M) {
    let ascent = 0;
    let descent = 0;
    let ref = null;
    for (const p of (Array.isArray(points) ? points : [])) {
        const e = (typeof p.ele === 'number' && isFinite(p.ele)) ? p.ele : null;
        if (e === null) continue;
        if (ref === null) { ref = e; continue; }
        const d = e - ref;
        if (d >= thresholdM) { ascent += d; ref = e; }
        else if (d <= -thresholdM) { descent += -d; ref = e; }
        // else: within the noise band — hold the reference.
    }
    return { ascentM: ascent, descentM: descent };
}

/**
 * @typedef {{lat:number,lng:number,ele:(number|null),t:(number|null)}} TrackPoint
 *   t = epoch milliseconds (or null when the source carries no time).
 */

/**
 * Derive summary statistics + an elevation profile from an ordered point list.
 *
 * @param {TrackPoint[]} points
 * @returns {{
 *   distanceM:number, durationTotalS:number, durationMovingS:number,
 *   ascentM:number, descentM:number, minEleM:(number|null), maxEleM:(number|null),
 *   avgSpeedMps:number, maxSpeedMps:number, pointCount:number,
 *   elevationProfile: Array<{distM:number, eleM:(number|null)}>
 * }}
 */
export function computeStats(points) {
    const pts = Array.isArray(points) ? points : [];
    const out = {
        distanceM: 0,
        durationTotalS: 0,
        durationMovingS: 0,
        ascentM: 0,
        descentM: 0,
        minEleM: null,
        maxEleM: null,
        avgSpeedMps: 0,
        maxSpeedMps: 0,
        pointCount: pts.length,
        elevationProfile: [],
    };
    if (pts.length === 0) return out;

    let cumDist = 0;
    for (let i = 0; i < pts.length; i++) {
        const p = pts[i];
        if (typeof p.ele === 'number' && isFinite(p.ele)) {
            if (out.minEleM === null || p.ele < out.minEleM) out.minEleM = p.ele;
            if (out.maxEleM === null || p.ele > out.maxEleM) out.maxEleM = p.ele;
        }

        if (i > 0) {
            const prev = pts[i - 1];
            const seg = haversineM(prev.lat, prev.lng, p.lat, p.lng);
            cumDist += seg;
            out.distanceM += seg;

            // Time-based accumulation.
            if (typeof p.t === 'number' && typeof prev.t === 'number') {
                const dtS = (p.t - prev.t) / 1000;
                if (dtS > 0) {
                    const speed = seg / dtS;
                    if (speed >= MOVING_SPEED_THRESHOLD_MPS) out.durationMovingS += dtS;
                    if (speed <= MAX_PLAUSIBLE_SPEED_MPS && speed > out.maxSpeedMps) {
                        out.maxSpeedMps = speed;
                    }
                }
            }
        }

        out.elevationProfile.push({
            distM: cumDist,
            eleM: (typeof p.ele === 'number' && isFinite(p.ele)) ? p.ele : null,
        });
    }

    const first = pts[0];
    const last = pts[pts.length - 1];
    if (typeof first.t === 'number' && typeof last.t === 'number' && last.t > first.t) {
        out.durationTotalS = (last.t - first.t) / 1000;
    }
    // Ascent / descent with GPS-noise smoothing (raw per-point deltas would
    // inflate total climb by an order of magnitude on jittery elevation data).
    const ad = smoothedAscentDescent(pts);
    out.ascentM = ad.ascentM;
    out.descentM = ad.descentM;

    const durForAvg = out.durationMovingS > 0 ? out.durationMovingS : out.durationTotalS;
    if (durForAvg > 0) out.avgSpeedMps = out.distanceM / durForAvg;

    // Round the money fields to sane precision (keep profile raw for charts).
    out.distanceM = round(out.distanceM, 2);
    out.ascentM = round(out.ascentM, 2);
    out.descentM = round(out.descentM, 2);
    out.avgSpeedMps = round(out.avgSpeedMps, 4);
    out.maxSpeedMps = round(out.maxSpeedMps, 4);
    out.durationMovingS = round(out.durationMovingS, 3);
    out.durationTotalS = round(out.durationTotalS, 3);
    return out;
}

function round(n, dp) {
    const f = 10 ** dp;
    return Math.round(n * f) / f;
}

// ---------------------------------------------------------------------------
// Shared shape assembly
// ---------------------------------------------------------------------------

/**
 * Assemble the canonical parsed-track shape from a name, format tag and the raw
 * ordered point list. Computes stats + bbox + start/end timestamps. Throws when
 * no usable points were found (a broken/empty track is a UI error, not stored).
 *
 * @param {string} name
 * @param {string} sourceFormat  'gpx'|'kml'|'tcx'|'fit'
 * @param {TrackPoint[]} points
 */
function assembleTrack(name, sourceFormat, points) {
    if (!Array.isArray(points) || points.length === 0) {
        throw new Error(`No track points found in ${sourceFormat.toUpperCase()} data`);
    }

    let minLat = Infinity;
    let minLng = Infinity;
    let maxLat = -Infinity;
    let maxLng = -Infinity;
    let startedAt = null;
    let endedAt = null;

    for (const p of points) {
        if (p.lat < minLat) minLat = p.lat;
        if (p.lat > maxLat) maxLat = p.lat;
        if (p.lng < minLng) minLng = p.lng;
        if (p.lng > maxLng) maxLng = p.lng;
        if (typeof p.t === 'number') {
            if (startedAt === null || p.t < startedAt) startedAt = p.t;
            if (endedAt === null || p.t > endedAt) endedAt = p.t;
        }
    }

    return {
        name: name || `Track (${sourceFormat.toUpperCase()})`,
        sourceFormat,
        startedAt: startedAt === null ? null : new Date(startedAt).toISOString(),
        endedAt: endedAt === null ? null : new Date(endedAt).toISOString(),
        points,
        stats: computeStats(points),
        bbox: { minLat, minLng, maxLat, maxLng },
    };
}

function parseTimeToMs(raw) {
    if (!raw) return null;
    const ms = Date.parse(raw.trim());
    return Number.isFinite(ms) ? ms : null;
}

function toNum(raw) {
    if (raw == null) return null;
    const n = Number(String(raw).trim());
    return Number.isFinite(n) ? n : null;
}

// ---------------------------------------------------------------------------
// Minimal dependency-free XML parser (headless fallback + primary in node)
// ---------------------------------------------------------------------------
//
// Produces a lightweight node tree: { tag, attrs:{}, children:[], text }.
// It is intentionally small: it understands elements, attributes, text content,
// self-closing tags, comments, CDATA, XML declarations and DOCTYPE. It does NOT
// resolve namespaces (callers match on the LOCAL name, stripping any prefix) —
// which is exactly what GPX/KML/TCX need. Any structural problem throws.

/**
 * @typedef {{tag:string, attrs:Object<string,string>, children:XmlNode[], text:string}} XmlNode
 */

function decodeEntities(s) {
    return s
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&#x([0-9a-fA-F]+);/g, (_, h) => String.fromCodePoint(parseInt(h, 16)))
        .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(parseInt(d, 10)))
        .replace(/&amp;/g, '&');
}

function localName(tag) {
    const i = tag.indexOf(':');
    return i >= 0 ? tag.slice(i + 1) : tag;
}

/**
 * Parse an XML string into a single root XmlNode. Throws on malformed input.
 * @param {string} xml
 * @returns {XmlNode}
 */
export function parseXml(xml) {
    if (typeof xml !== 'string' || xml.trim() === '') throw new Error('Empty XML input');

    // Strip declarations, comments, DOCTYPE and processing instructions.
    let s = xml
        .replace(/<\?[\s\S]*?\?>/g, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<!DOCTYPE[\s\S]*?>/gi, '');

    // Stash CDATA sections (their verbatim contents may contain <, >, & that
    // would otherwise be mis-scanned as markup) behind an unforgeable sentinel,
    // and expand them back in appendText.
    const cdataStash = [];
    s = s.replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, (_, body) => {
        cdataStash.push(body);
        return `\uE000CDATA${cdataStash.length - 1}\uE000`;
    });

    const root = { tag: '#root', attrs: {}, children: [], text: '' };
    const stack = [root];
    const tagRe = /<(\/?)([A-Za-z_][\w.\-:]*)((?:[^>"']|"[^"]*"|'[^']*')*?)(\/?)>/g;
    let lastIndex = 0;
    let m;

    while ((m = tagRe.exec(s)) !== null) {
        const [full, closing, rawTag, rawAttrs, selfClose] = m;

        const between = s.slice(lastIndex, m.index);
        appendText(stack[stack.length - 1], between, cdataStash);
        lastIndex = m.index + full.length;

        const tag = localName(rawTag);

        if (closing) {
            // Pop until we match (tolerate minor nesting slop but require a match).
            let matched = false;
            for (let i = stack.length - 1; i >= 1; i--) {
                if (stack[i].tag === tag) {
                    stack.length = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) throw new Error(`Unbalanced closing tag </${tag}>`);
            continue;
        }

        const node = { tag, attrs: parseAttrs(rawAttrs), children: [], text: '' };
        stack[stack.length - 1].children.push(node);
        if (!selfClose) stack.push(node);
    }

    appendText(stack[stack.length - 1], s.slice(lastIndex), cdataStash);

    if (root.children.length === 0) throw new Error('No XML elements found');
    return root.children[0];
}

function appendText(node, raw, cdataStash) {
    if (!raw) return;
    // Expand stashed CDATA sentinels verbatim; decode entities elsewhere.
    const sentinel = /CDATA(\d+)/g;
    let out = '';
    let idx = 0;
    let c;
    while ((c = sentinel.exec(raw)) !== null) {
        out += decodeEntities(raw.slice(idx, c.index)) + (cdataStash?.[+c[1]] ?? '');
        idx = c.index + c[0].length;
    }
    out += decodeEntities(raw.slice(idx));
    if (out.trim() !== '') node.text += out;
}

function parseAttrs(raw) {
    const attrs = {};
    const re = /([A-Za-z_][\w.\-:]*)\s*=\s*("([^"]*)"|'([^']*)')/g;
    let m;
    while ((m = re.exec(raw)) !== null) {
        attrs[localName(m[1])] = decodeEntities(m[3] !== undefined ? m[3] : m[4]);
    }
    return attrs;
}

// --- Uniform accessor over either a DOM Element or our XmlNode ---------------

function isDomEl(n) {
    return typeof n === 'object' && n !== null && typeof n.getElementsByTagName === 'function';
}

/** All descendant elements with the given local name (namespace-agnostic). */
function findAll(node, name) {
    const want = name.toLowerCase();
    const out = [];
    if (isDomEl(node)) {
        // Namespace-agnostic: scan every descendant, match on local name.
        for (const el of node.getElementsByTagName('*')) {
            if (localName(el.tagName).toLowerCase() === want) out.push(el);
        }
        return out;
    }
    walk(node, (n) => { if (n.tag.toLowerCase() === want) out.push(n); });
    return out;
}

/** First descendant element with the given local name, or null. */
function findFirst(node, name) {
    const all = findAll(node, name);
    return all.length ? all[0] : null;
}

/** Direct/descendant text content of a node. */
function textOf(node) {
    if (node == null) return '';
    if (isDomEl(node)) return (node.textContent || '').trim();
    let t = node.text || '';
    walk(node, (n) => { if (n !== node) t += n.text || ''; });
    return t.trim();
}

function attrOf(node, name) {
    if (node == null) return null;
    if (isDomEl(node)) {
        const v = node.getAttribute(name);
        return v == null ? null : v;
    }
    return name in node.attrs ? node.attrs[name] : null;
}

function walk(node, fn) {
    fn(node);
    const kids = isDomEl(node) ? [] : node.children;
    for (const k of kids) walk(k, fn);
}

/** Parse XML with native DOMParser when available, else the built-in walker. */
function parseXmlDoc(text) {
    if (typeof DOMParser !== 'undefined') {
        const doc = new DOMParser().parseFromString(text, 'application/xml');
        const err = doc.getElementsByTagName('parsererror');
        if (err && err.length) throw new Error('Malformed XML');
        const el = doc.documentElement;
        if (!el) throw new Error('Malformed XML');
        return el;
    }
    return parseXml(text);
}

// ---------------------------------------------------------------------------
// GPX
// ---------------------------------------------------------------------------

function parseGpx(text) {
    const root = parseXmlDoc(text);
    const name = textOf(findFirst(root, 'name')) || null;

    // Prefer trkpt; fall back to rtept, then standalone wpt if that is all there is.
    let raw = findAll(root, 'trkpt');
    if (raw.length === 0) raw = findAll(root, 'rtept');
    if (raw.length === 0) raw = findAll(root, 'wpt');

    const points = [];
    for (const pt of raw) {
        const lat = toNum(attrOf(pt, 'lat'));
        const lng = toNum(attrOf(pt, 'lon'));
        if (lat === null || lng === null) continue;
        points.push({
            lat,
            lng,
            ele: toNum(textOf(findFirst(pt, 'ele'))),
            t: parseTimeToMs(textOf(findFirst(pt, 'time'))),
        });
    }
    return assembleTrack(name, 'gpx', points);
}

// ---------------------------------------------------------------------------
// KML (LineString coordinates + optional gx:Track / gx:coord + when)
// ---------------------------------------------------------------------------

function parseKml(text) {
    const root = parseXmlDoc(text);
    const name = textOf(findFirst(root, 'name')) || null;
    const points = [];

    // 1) gx:Track — parallel <when> and <gx:coord>lng lat ele</gx:coord> lists.
    const tracks = findAll(root, 'Track');
    for (const trk of tracks) {
        const whens = findAll(trk, 'when').map((w) => textOf(w));
        const coords = findAll(trk, 'coord').map((c) => textOf(c));
        for (let i = 0; i < coords.length; i++) {
            const [lng, lat, ele] = coords[i].split(/\s+/).map(Number);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
            points.push({
                lat,
                lng,
                ele: Number.isFinite(ele) ? ele : null,
                t: parseTimeToMs(whens[i]),
            });
        }
    }

    // 2) Plain LineString / Point <coordinates> — "lng,lat,ele" tuples, no time.
    if (points.length === 0) {
        for (const c of findAll(root, 'coordinates')) {
            const raw = textOf(c);
            for (const tuple of raw.split(/\s+/)) {
                if (!tuple.trim()) continue;
                const [lng, lat, ele] = tuple.split(',').map(Number);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
                points.push({ lat, lng, ele: Number.isFinite(ele) ? ele : null, t: null });
            }
        }
    }

    return assembleTrack(name, 'kml', points);
}

// ---------------------------------------------------------------------------
// TCX (Garmin Training Center — Trackpoint / Position / AltitudeMeters / Time)
// ---------------------------------------------------------------------------

function parseTcx(text) {
    const root = parseXmlDoc(text);
    const points = [];
    for (const tp of findAll(root, 'Trackpoint')) {
        const pos = findFirst(tp, 'Position');
        const lat = pos ? toNum(textOf(findFirst(pos, 'LatitudeDegrees'))) : null;
        const lng = pos ? toNum(textOf(findFirst(pos, 'LongitudeDegrees'))) : null;
        if (lat === null || lng === null) continue;
        points.push({
            lat,
            lng,
            ele: toNum(textOf(findFirst(tp, 'AltitudeMeters'))),
            t: parseTimeToMs(textOf(findFirst(tp, 'Time'))),
        });
    }
    // A name is uncommon in TCX; fall back to the activity sport/id if present.
    const name = textOf(findFirst(root, 'Id')) || null;
    return assembleTrack(name, 'tcx', points);
}

// ---------------------------------------------------------------------------
// FIT (binary) — minimal record-message decoder
// ---------------------------------------------------------------------------
//
// FIT LIMITATION (documented): this is a deliberately MINIMAL decoder covering
// exactly the "record" message (global message number 20) fields we need for a
// map track: position_lat/position_long (semicircles → degrees), altitude and
// timestamp. It handles the 14-byte header, normal definition + data messages,
// little- and big-endian definitions, and skips developer-data / compressed-
// timestamp headers gracefully. It does NOT decode laps, sessions, HR/cadence,
// developer fields, or accumulate compressed timestamps. Files that carry track
// points only via non-record messages are not supported. On any structural
// surprise it throws (UI shows the error; nothing is stored).

const SEMICIRCLE_TO_DEG = 180 / 2 ** 31;
// FIT epoch = 1989-12-31T00:00:00Z, in ms since the Unix epoch.
const FIT_EPOCH_MS = 631065600000;

/**
 * Minimal FIT decoder. Returns the ordered record points.
 * @param {DataView} view
 * @returns {TrackPoint[]}
 */
function decodeFit(view) {
    const len = view.byteLength;
    if (len < 14) throw new Error('FIT file too short');

    const headerSize = view.getUint8(0);
    if (headerSize !== 12 && headerSize !== 14) throw new Error('Unsupported FIT header');
    // Signature ".FIT" at offset 8.
    const sig = String.fromCharCode(view.getUint8(8), view.getUint8(9), view.getUint8(10), view.getUint8(11));
    if (sig !== '.FIT') throw new Error('Not a FIT file');

    const dataSize = view.getUint32(4, true);
    let off = headerSize;
    const end = Math.min(len, headerSize + dataSize);

    // localMsgType → { little, fields:[{num,size,baseType}], globalMsg }
    const defs = {};
    const points = [];

    while (off < end) {
        const recordHeader = view.getUint8(off);
        off += 1;

        // Compressed-timestamp header (bit7=1) — we don't accumulate its offset;
        // treat as a data message of its local type, no timestamp gain.
        const isCompressed = (recordHeader & 0x80) !== 0;
        const isDefinition = !isCompressed && (recordHeader & 0x40) !== 0;
        const hasDevData = !isCompressed && (recordHeader & 0x20) !== 0;
        const localType = isCompressed ? (recordHeader >> 5) & 0x03 : recordHeader & 0x0f;

        if (isDefinition) {
            off += 1; // reserved
            const arch = view.getUint8(off); off += 1; // 0 = little, 1 = big
            const little = arch === 0;
            const globalMsg = view.getUint16(off, little); off += 2;
            const numFields = view.getUint8(off); off += 1;
            const fields = [];
            for (let i = 0; i < numFields; i++) {
                const num = view.getUint8(off);
                const size = view.getUint8(off + 1);
                const baseType = view.getUint8(off + 2);
                off += 3;
                fields.push({ num, size, baseType });
            }
            if (hasDevData) {
                const numDev = view.getUint8(off); off += 1;
                off += numDev * 3; // skip dev field defs
                for (let i = 0; i < numDev; i++) {
                    // record dev field sizes so we can skip their data bytes
                    fields.push({ dev: true, size: view.getUint8(off - numDev * 3 + i * 3 + 1) });
                }
            }
            defs[localType] = { little, fields, globalMsg };
            continue;
        }

        const def = defs[localType];
        if (!def) throw new Error('FIT data message before its definition');

        const rec = { lat: null, lng: null, ele: null, t: null };
        for (const f of def.fields) {
            if (f.dev) { off += f.size; continue; }
            const val = readFitField(view, off, f, def.little);
            off += f.size;
            if (def.globalMsg !== 20) continue; // only "record"
            switch (f.num) {
                case 0: if (val !== null) rec.lat = val * SEMICIRCLE_TO_DEG; break; // position_lat
                case 1: if (val !== null) rec.lng = val * SEMICIRCLE_TO_DEG; break; // position_long
                case 2: if (val !== null) rec.ele = val / 5 - 500; break;           // altitude (enhanced scale)
                case 253: if (val !== null) rec.t = FIT_EPOCH_MS + val * 1000; break; // timestamp
                default: break;
            }
        }

        if (def.globalMsg === 20 && rec.lat !== null && rec.lng !== null) {
            points.push(rec);
        }
    }

    return points;
}

// Read a single (scalar) FIT field value; returns null for the "invalid" sentinel.
function readFitField(view, off, f, little) {
    const bt = f.baseType;
    switch (bt) {
        case 0x00: case 0x02: case 0x0a: case 0x0d: { // enum/uint8/uint8z/byte
            const v = view.getUint8(off);
            return v === 0xff ? null : v;
        }
        case 0x01: { // sint8
            const v = view.getInt8(off);
            return v === 0x7f ? null : v;
        }
        case 0x83: { // sint16
            const v = view.getInt16(off, little);
            return v === 0x7fff ? null : v;
        }
        case 0x84: case 0x8b: { // uint16 / uint16z
            const v = view.getUint16(off, little);
            return v === 0xffff ? null : v;
        }
        case 0x85: { // sint32
            const v = view.getInt32(off, little);
            return v === 0x7fffffff ? null : v;
        }
        case 0x86: case 0x8c: { // uint32 / uint32z
            const v = view.getUint32(off, little);
            return v === 0xffffffff ? null : v;
        }
        case 0x88: { // float32
            const v = view.getFloat32(off, little);
            return Number.isNaN(v) ? null : v;
        }
        case 0x89: { // float64
            const v = view.getFloat64(off, little);
            return Number.isNaN(v) ? null : v;
        }
        default:
            return null; // string / 64-bit / unknown: skipped (size still advanced)
    }
}

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------

function extOf(filename) {
    const m = /\.([A-Za-z0-9]+)\s*$/.exec(filename || '');
    return m ? m[1].toLowerCase() : '';
}

/**
 * Parse a text-based track (GPX / KML / TCX). KMZ callers must unzip first and
 * pass the inner KML string. Detects the format by extension, falling back to a
 * content sniff. Throws a clear Error on unknown or broken input.
 *
 * @param {string} text
 * @param {string} [filename]
 * @returns {ReturnType<typeof assembleTrack>}
 */
export function parseTrack(text, filename = '') {
    if (typeof text !== 'string' || text.trim() === '') {
        throw new Error('Empty track data');
    }
    const ext = extOf(filename);

    if (ext === 'gpx') return parseGpx(text);
    if (ext === 'kml') return parseKml(text);
    if (ext === 'tcx') return parseTcx(text);

    // Content sniff when the extension is missing/unknown.
    const head = text.slice(0, 4096);
    if (/<gpx[\s>]/i.test(head)) return parseGpx(text);
    if (/<TrainingCenterDatabase[\s>]/i.test(head) || /<Trackpoint[\s>]/i.test(head)) return parseTcx(text);
    if (/<kml[\s>]/i.test(head) || /<coordinates[\s>]/i.test(head) || /<gx:Track[\s>]/i.test(head)) return parseKml(text);

    throw new Error(`Unrecognised track format${ext ? ` (.${ext})` : ''}`);
}

/**
 * Parse a binary track. FIT is detected by extension or the ".FIT" signature.
 * (KMZ is a zip — the component unzips it and calls parseTrack with the KML
 * string; it is not handled here.)
 *
 * @param {ArrayBuffer|Uint8Array} buffer
 * @param {string} [filename]
 * @returns {ReturnType<typeof assembleTrack>}
 */
export function parseTrackBinary(buffer, filename = '') {
    if (!buffer || (buffer.byteLength ?? 0) < 14) throw new Error('Empty or truncated binary track');
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const ext = extOf(filename);

    const sig = bytes.byteLength >= 12
        ? String.fromCharCode(bytes[8], bytes[9], bytes[10], bytes[11])
        : '';

    if (ext === 'fit' || sig === '.FIT') {
        const name = filename ? filename.replace(/\.[^.]+$/, '') : null;
        return assembleTrack(name, 'fit', decodeFit(view));
    }

    throw new Error(`Unrecognised binary track format${ext ? ` (.${ext})` : ''}`);
}
