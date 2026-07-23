// Serialise an Explore track's decrypted points into GPX 1.1 for download.
// Pure + client-side (the points come from the already-decrypted explore store,
// so nothing leaves the zero-knowledge boundary). Mirrors the parse side
// (shared/track-parse.js): a point is { lat, lng, ele:(number|null), t:(number|null) }.

/** XML-escape a string for use in element text / attribute values. */
function xmlEscape(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

/** A finite number formatted to 7 decimals (≈1 cm), or null. */
function coord(n) {
    const v = typeof n === 'number' ? n : parseFloat(n);
    return Number.isFinite(v) ? v.toFixed(7) : null;
}

/**
 * Build a GPX 1.1 document string for a track. Points without a valid lat/lng are
 * skipped; elevation + time are emitted only when present. Returns '' when the
 * track has no usable points.
 *
 * @param {{name?:string, points?:Array<{lat:number,lng:number,ele?:(number|null),t?:(number|null)}>}} track
 * @returns {string}
 */
export function buildGpx(track) {
    const points = Array.isArray(track?.points) ? track.points : [];
    const seg = [];
    for (const p of points) {
        const lat = coord(p?.lat);
        const lon = coord(p?.lng);
        if (lat === null || lon === null) continue;
        let pt = `      <trkpt lat="${lat}" lon="${lon}">`;
        if (typeof p.ele === 'number' && Number.isFinite(p.ele)) {
            pt += `<ele>${p.ele}</ele>`;
        }
        if (typeof p.t === 'number' && Number.isFinite(p.t)) {
            pt += `<time>${new Date(p.t).toISOString()}</time>`;
        }
        pt += '</trkpt>';
        seg.push(pt);
    }
    if (seg.length === 0) return '';

    const name = xmlEscape(track?.name || 'track');

    return '<?xml version="1.0" encoding="UTF-8"?>\n'
        + '<gpx version="1.1" creator="Ledgerline" xmlns="http://www.topografix.com/GPX/1/1">\n'
        + '  <trk>\n'
        + `    <name>${name}</name>\n`
        + '    <trkseg>\n'
        + seg.join('\n') + '\n'
        + '    </trkseg>\n'
        + '  </trk>\n'
        + '</gpx>\n';
}

/** A filesystem-safe .gpx filename from a track name. */
export function gpxFilename(name) {
    const base = String(name || 'track').trim().replace(/[^\w.\- ]+/g, '_').replace(/\s+/g, '_').slice(0, 80) || 'track';
    return base.toLowerCase().endsWith('.gpx') ? base : base + '.gpx';
}
