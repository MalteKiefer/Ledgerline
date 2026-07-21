// Pure, headless-testable helpers for the gallery derivation path (Store v3
// §8.1/§8.2/§8.3 + §4.1/§5.2). Kept out of the Alpine component so they can be
// unit-tested in Vitest (the component itself needs a browser: canvas, workers,
// crypto). The browser-only glue (canvas decode/encode, encrypt, upload) lives in
// gallery.js and calls these.

// MIME types the browser can reliably decode to a bitmap for on-device thumbnail
// / medium derivation. HEIC/HEIF are deliberately EXCLUDED: Chrome/Firefox cannot
// decode them, so those fall back to the server /process path (Store v3 §8.2).
// (Safari can decode HEIC, but we key on the lowest-common-denominator so a v3
// library written by any browser is consistent.)
const DECODABLE_MIME = new Set([
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/bmp',
]);

// Server rendition targets — MUST mirror GalleryProcessor.php
// (THUMB_WIDTH / MEDIUM_WIDTH + WebpEncoder quality) so a browser-derived
// rendition is interchangeable with a server-derived one.
export const THUMB_WIDTH = 400;
export const MEDIUM_WIDTH = 1600;
export const THUMB_QUALITY = 0.75;
export const MEDIUM_QUALITY = 0.82;

/**
 * Decide how a file's thumb/medium renditions are derived:
 *   - 'canvas' : the browser can decode this MIME → derive on-device via
 *                <canvas> + toBlob('image/webp') (no plaintext egress).
 *   - 'process': the browser cannot decode it (HEIC/HEIF, video) → defer to the
 *                server /process endpoint (transient plaintext, RAII-unlinked).
 *
 * ML (CLIP embedding + faces) always goes server-side regardless — this only
 * governs thumb/medium/dims.
 *
 * @param {string} mime
 * @param {string} [name] filename, used only as a fallback when mime is empty
 *   (the OS often reports "" for HEIC/MOV).
 * @returns {'canvas'|'process'}
 */
export function pickDerivationPath(mime, name = '') {
    const m = (mime || '').toLowerCase();
    if (DECODABLE_MIME.has(m)) return 'canvas';
    // A missing/blank MIME is decided by extension: only the browser-decodable
    // still-image extensions take the canvas path; everything else (heic/heif,
    // any video) defers to the server.
    if (! m) {
        if (/\.(jpe?g|png|webp|gif|bmp)$/i.test(name)) return 'canvas';
    }
    return 'process';
}

/** True if the browser can decode this MIME for on-device derivation. */
export function isBrowserDecodable(mime, name = '') {
    return pickDerivationPath(mime, name) === 'canvas';
}

/* ---- Minimal, fail-safe JPEG EXIF reader (on-device, no dependency) ----
 *
 * The web client has no bundled EXIF library. When it derives renditions on-device
 * (§8.2) it no longer sends the original to /process, so it would otherwise lose
 * the EXIF-sourced capture date / GPS / camera. This parser recovers the few hot
 * fields we actually use — DateTimeOriginal, GPS lat/lng, Make+Model — directly
 * from the JPEG APP1/TIFF structure. It is intentionally minimal and returns
 * nulls on ANYTHING unexpected (never throws), so a malformed header degrades to
 * "no EXIF" exactly like the deferred path. Non-JPEG decodable formats
 * (png/webp/gif/bmp) rarely carry EXIF → they simply get no EXIF here.
 */

function readU16(view, off, little) { return view.getUint16(off, little); }
function readU32(view, off, little) { return view.getUint32(off, little); }

// One TIFF rational = num/den (4+4 bytes). Returns a float or null.
function readRational(view, off, little) {
    const num = readU32(view, off, little);
    const den = readU32(view, off + 4, little);
    if (! den) return null;
    return num / den;
}

// GPS coordinate = 3 rationals (deg, min, sec) + a hemisphere ref (N/S/E/W).
function gpsToDecimal(view, valueOff, little, ref) {
    const d = readRational(view, valueOff, little);
    const m = readRational(view, valueOff + 8, little);
    const s = readRational(view, valueOff + 16, little);
    if (d == null || m == null || s == null) return null;
    let dec = d + m / 60 + s / 3600;
    if (ref === 'S' || ref === 'W') dec = -dec;
    return dec;
}

// Parse one IFD, collecting the tags we care about. `want` maps tag id -> handler.
function walkIfd(view, tiffStart, ifdOff, little, out) {
    const count = readU16(view, tiffStart + ifdOff, little);
    let entry = tiffStart + ifdOff + 2;
    for (let i = 0; i < count; i++, entry += 12) {
        const tag = readU16(view, entry, little);
        const type = readU16(view, entry + 2, little);
        const num = readU32(view, entry + 4, little);
        // Value offset (or inline value for ≤4 bytes) at entry+8.
        const valPtr = entry + 8;
        out.push({ tag, type, num, valPtr });
    }
    return out;
}

function asciiAt(view, tiffStart, entry, little) {
    // ASCII values >4 bytes are stored at an offset; ≤4 bytes are inline.
    const len = entry.num;
    const base = len <= 4 ? entry.valPtr : tiffStart + readU32(view, entry.valPtr, little);
    let s = '';
    for (let i = 0; i < len; i++) {
        const c = view.getUint8(base + i);
        if (c === 0) break;
        s += String.fromCharCode(c);
    }
    return s.trim();
}

/**
 * Extract the hot EXIF fields from JPEG bytes. Always returns an object; every
 * field is null when absent/unparseable. Never throws.
 *
 * @param {ArrayBuffer|Uint8Array} buffer
 * @returns {{taken_at: string|null, lat: number|null, lon: number|null, camera: string|null}}
 */
export function readJpegExif(buffer) {
    const empty = { taken_at: null, lat: null, lon: null, camera: null };
    try {
        const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
        const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
        if (view.byteLength < 4 || view.getUint16(0, false) !== 0xFFD8) return empty; // not JPEG (SOI)

        // Scan APP marker segments for APP1 ("Exif\0\0").
        let off = 2;
        let tiffStart = -1;
        while (off + 4 <= view.byteLength) {
            if (view.getUint8(off) !== 0xFF) break;
            const marker = view.getUint8(off + 1);
            const segLen = view.getUint16(off + 2, false);
            if (segLen < 2) break;
            if (marker === 0xE1) { // APP1
                const hdr = off + 4;
                if (hdr + 6 <= view.byteLength
                    && view.getUint32(hdr, false) === 0x45786966 /* "Exif" */
                    && view.getUint16(hdr + 4, false) === 0x0000) {
                    tiffStart = hdr + 6;
                    break;
                }
            }
            if (marker === 0xDA) break; // start of scan — no more metadata
            off += 2 + segLen;
        }
        if (tiffStart < 0 || tiffStart + 8 > view.byteLength) return empty;

        // TIFF header: byte order ("II"/"MM") + magic 42 + first-IFD offset.
        const bo = view.getUint16(tiffStart, false);
        const little = bo === 0x4949;
        if (! little && bo !== 0x4D4D) return empty;
        if (readU16(view, tiffStart + 2, little) !== 42) return empty;
        const ifd0Off = readU32(view, tiffStart + 4, little);

        const entries0 = walkIfd(view, tiffStart, ifd0Off, little, []);
        let make = '';
        let model = '';
        let exifIfdOff = 0;
        let gpsIfdOff = 0;
        for (const e of entries0) {
            if (e.tag === 0x010F) make = asciiAt(view, tiffStart, e, little);       // Make
            else if (e.tag === 0x0110) model = asciiAt(view, tiffStart, e, little);  // Model
            else if (e.tag === 0x8769) exifIfdOff = readU32(view, e.valPtr, little); // ExifIFDPointer
            else if (e.tag === 0x8825) gpsIfdOff = readU32(view, e.valPtr, little);  // GPSInfoIFDPointer
        }

        let takenAt = null;
        if (exifIfdOff && tiffStart + exifIfdOff + 2 <= view.byteLength) {
            for (const e of walkIfd(view, tiffStart, exifIfdOff, little, [])) {
                // DateTimeOriginal (0x9003), fallback CreateDate/DateTimeDigitized (0x9004).
                if (e.tag === 0x9003 || (! takenAt && e.tag === 0x9004)) {
                    const raw = asciiAt(view, tiffStart, e, little); // "YYYY:MM:DD HH:MM:SS"
                    const m = /^(\d{4}):(\d{2}):(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/.exec(raw);
                    if (m) {
                        // Local wall-clock, no timezone maths (mirrors ExifReader/server).
                        const d = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]);
                        if (! isNaN(d.getTime()) && e.tag === 0x9003) takenAt = d.toISOString();
                        else if (! isNaN(d.getTime()) && ! takenAt) takenAt = d.toISOString();
                    }
                }
            }
        }

        let lat = null;
        let lon = null;
        if (gpsIfdOff && tiffStart + gpsIfdOff + 2 <= view.byteLength) {
            let latRef = 'N';
            let lonRef = 'E';
            let latPtr = 0;
            let lonPtr = 0;
            for (const e of walkIfd(view, tiffStart, gpsIfdOff, little, [])) {
                if (e.tag === 0x0001) latRef = asciiAt(view, tiffStart, e, little) || 'N';       // GPSLatitudeRef
                else if (e.tag === 0x0003) lonRef = asciiAt(view, tiffStart, e, little) || 'E';  // GPSLongitudeRef
                else if (e.tag === 0x0002) latPtr = tiffStart + readU32(view, e.valPtr, little); // GPSLatitude
                else if (e.tag === 0x0004) lonPtr = tiffStart + readU32(view, e.valPtr, little); // GPSLongitude
            }
            if (latPtr && latPtr + 24 <= view.byteLength) lat = gpsToDecimal(view, latPtr, little, latRef);
            if (lonPtr && lonPtr + 24 <= view.byteLength) lon = gpsToDecimal(view, lonPtr, little, lonRef);
            if (lat != null && ! isFinite(lat)) lat = null;
            if (lon != null && ! isFinite(lon)) lon = null;
        }

        const camera = `${make} ${model}`.trim() || null;
        return { taken_at: takenAt, lat, lon, camera };
    } catch (e) {
        return empty;
    }
}

/**
 * Compute the target dimensions for a rendition given the source dimensions and
 * a max edge on the WIDTH axis. Mirrors Intervention's `scaleDown(width: max)`:
 * only ever shrinks (never enlarges), preserves aspect ratio, rounds to whole
 * pixels. Returns the source size unchanged when it is already within bounds.
 *
 * @param {number} srcW
 * @param {number} srcH
 * @param {number} maxWidth
 * @returns {{w: number, h: number}}
 */
export function scaleDownSize(srcW, srcH, maxWidth) {
    if (! (srcW > 0) || ! (srcH > 0)) return { w: 0, h: 0 };
    if (srcW <= maxWidth) return { w: Math.round(srcW), h: Math.round(srcH) };
    const scale = maxWidth / srcW;
    return { w: Math.round(srcW * scale), h: Math.max(1, Math.round(srcH * scale)) };
}
