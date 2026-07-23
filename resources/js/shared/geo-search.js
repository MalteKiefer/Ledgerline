// Pure parsing helpers for the Explore search box: recognise raw coordinates
// and Google-Maps links entirely client-side (no egress), so only a free-text
// place/POI query or a short-link that needs a redirect hop ever reaches the
// server. All functions are side-effect-free and unit-tested.

/** Clamp helper: a valid WGS84 pair (lat ∈ [-90,90], lng ∈ [-180,180]). */
function valid(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng)
        && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
        ? { lat, lng } : null;
}

/**
 * Parse a raw coordinate string into { lat, lng } or null. Accepts:
 *   "48.5216, 9.0576"  ·  "48.5216 9.0576"  ·  "48.5216;9.0576"
 *   "48.5216N, 9.0576E"  ·  "-33.8688, 151.2093"
 * A trailing hemisphere letter (N/S/E/W) flips the sign and assigns the value to
 * lat (N/S) or lng (E/W); a bare pair is read as lat,lng. Returns null if it
 * doesn't look like a coordinate.
 */
export function parseCoords(input) {
    const s = String(input || '').trim();
    if (!s) return null;
    // Reject anything with letters other than the hemisphere markers / separators.
    if (/[a-mo-qt-zA-MO-QT-Z]/.test(s.replace(/[NSEWnsew]/g, ''))) return null;

    // A number with an OPTIONAL immediately-adjacent hemisphere suffix (no space,
    // so "48.5 E" can't bind the E of the next token).
    const parts = s.match(/[+-]?\d+(?:\.\d+)?[NSEWnsew]?/g);
    if (!parts || parts.length !== 2) return null;

    let lat = null, lng = null, plainA = null, plainB = null;
    for (const raw of parts) {
        const m = raw.match(/([+-]?\d+(?:\.\d+)?)([NSEWnsew]?)/);
        if (!m) return null;
        let val = parseFloat(m[1]);
        const hemi = (m[2] || '').toUpperCase();
        if (hemi === 'S' || hemi === 'W') val = -Math.abs(val);
        else if (hemi === 'N' || hemi === 'E') val = Math.abs(val);
        if (hemi === 'N' || hemi === 'S') lat = val;
        else if (hemi === 'E' || hemi === 'W') lng = val;
        else if (plainA === null) plainA = val;
        else plainB = val;
    }
    // Fill unlabelled values as lat then lng, respecting any explicit labels.
    if (plainA !== null) { if (lat === null) lat = plainA; else if (lng === null) lng = plainA; }
    if (plainB !== null) { if (lng === null) lng = plainB; else if (lat === null) lat = plainB; }
    if (lat === null || lng === null) return null;
    return valid(lat, lng);
}

/**
 * Extract { lat, lng } from a *long* Google-Maps URL, entirely client-side.
 * Covers the common shapes:
 *   …/@48.5216,9.0576,15z       (map centre / place)
 *   …!3d48.5216!4d9.0576        (place data blob)
 *   ?q=48.5216,9.0576  ·  ?ll=… ·  ?center=… ·  &destination=… ·  ?query=…
 * Returns null when the URL carries no coordinates (e.g. a short link, or a
 * /place/Name link with no @). The `@` centre is preferred, then the !3d!4d
 * place pin, then the query params.
 */
export function parseGoogleMapsUrl(input) {
    const s = String(input || '').trim();
    if (!s) return null;

    // !3dLAT!4dLNG — the actual place pin (most accurate when present).
    const bang = s.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
    if (bang) { const r = valid(parseFloat(bang[1]), parseFloat(bang[2])); if (r) return r; }

    // @LAT,LNG(,zoom) — the map centre.
    const at = s.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
    if (at) { const r = valid(parseFloat(at[1]), parseFloat(at[2])); if (r) return r; }

    // q= / ll= / center= / query= / destination= = "lat,lng" (URL-encoded comma too).
    const param = s.match(/[?&](?:q|ll|center|query|destination|daddr)=(-?\d+(?:\.\d+)?)(?:,|%2[Cc])(-?\d+(?:\.\d+)?)/);
    if (param) { const r = valid(parseFloat(param[1]), parseFloat(param[2])); if (r) return r; }

    return null;
}

/** Whether a string is an http(s) URL. */
export function looksLikeUrl(input) {
    return /^https?:\/\/\S+$/i.test(String(input || '').trim());
}

/**
 * Whether a string is a Google-Maps *short* link whose target must be resolved
 * server-side by following the redirect (the coordinates aren't in the URL).
 * e.g. https://maps.app.goo.gl/xxxx · https://goo.gl/maps/xxxx · https://g.co/kgs/…
 */
export function isShortMapsLink(input) {
    const s = String(input || '').trim();
    if (!looksLikeUrl(s)) return false;
    let host;
    try { host = new URL(s).hostname.toLowerCase(); } catch { return false; }
    return host === 'maps.app.goo.gl'
        || host === 'goo.gl'
        || host === 'g.co'
        || host === 'maps.google.com' && !parseGoogleMapsUrl(s);
}

/**
 * Classify a raw search box value into an action the caller should take:
 *   { kind:'coords', lat, lng }        — parsed locally, place a pin
 *   { kind:'resolve', url }            — Google short link → GET /maps/resolve
 *   { kind:'geocode', q }              — free-text place/POI → GET /gallery/geocode
 * Returns null for empty input.
 */
export function classifySearch(input) {
    const s = String(input || '').trim();
    if (!s) return null;

    const coords = parseCoords(s);
    if (coords) return { kind: 'coords', ...coords };

    if (looksLikeUrl(s)) {
        const fromUrl = parseGoogleMapsUrl(s);
        if (fromUrl) return { kind: 'coords', ...fromUrl };
        if (isShortMapsLink(s)) return { kind: 'resolve', url: s };
        // A non-Google URL with no coords — fall through to a text lookup so a
        // pasted address-bearing URL still does *something* useful.
    }
    return { kind: 'geocode', q: s };
}
