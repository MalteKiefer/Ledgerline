import { describe, expect, it } from 'vitest';
import {
    pickDerivationPath, isBrowserDecodable, scaleDownSize, readJpegExif,
    THUMB_WIDTH, MEDIUM_WIDTH,
} from '../shared/gallery-derive';

describe('pickDerivationPath', () => {
    it('routes browser-decodable still images to the canvas path', () => {
        for (const m of ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'image/bmp']) {
            expect(pickDerivationPath(m)).toBe('canvas');
        }
    });
    it('defers HEIC/HEIF and video to the server /process path', () => {
        expect(pickDerivationPath('image/heic')).toBe('process');
        expect(pickDerivationPath('image/heif')).toBe('process');
        expect(pickDerivationPath('video/mp4')).toBe('process');
        expect(pickDerivationPath('video/quicktime')).toBe('process');
        expect(pickDerivationPath('image/avif')).toBe('process');
    });
    it('falls back to the filename when the MIME is blank (HEIC/MOV report "")', () => {
        expect(pickDerivationPath('', 'IMG_1.HEIC')).toBe('process');
        expect(pickDerivationPath('', 'clip.MOV')).toBe('process');
        expect(pickDerivationPath('', 'photo.JPG')).toBe('canvas');
        expect(pickDerivationPath('', 'photo.png')).toBe('canvas');
        expect(pickDerivationPath('', 'noextension')).toBe('process');
    });
    it('isBrowserDecodable mirrors the canvas verdict', () => {
        expect(isBrowserDecodable('image/png')).toBe(true);
        expect(isBrowserDecodable('image/heic')).toBe(false);
    });
});

describe('scaleDownSize', () => {
    it('shrinks by width, preserving aspect ratio', () => {
        expect(scaleDownSize(4000, 3000, THUMB_WIDTH)).toEqual({ w: 400, h: 300 });
        expect(scaleDownSize(4000, 3000, MEDIUM_WIDTH)).toEqual({ w: 1600, h: 1200 });
    });
    it('never enlarges an already-small source', () => {
        expect(scaleDownSize(200, 100, 400)).toEqual({ w: 200, h: 100 });
    });
    it('rounds to whole pixels and never yields a zero height', () => {
        expect(scaleDownSize(1000, 3, 400)).toEqual({ w: 400, h: 1 });
    });
    it('guards degenerate dimensions', () => {
        expect(scaleDownSize(0, 0, 400)).toEqual({ w: 0, h: 0 });
        expect(scaleDownSize(-5, 10, 400)).toEqual({ w: 0, h: 0 });
    });
});

/* ---- JPEG EXIF fixture builder (little-endian "II" TIFF) ---- */

// Build a minimal but valid JPEG (SOI + APP1/Exif + EOI) carrying IFD0 with
// Make/Model + ExifIFD (DateTimeOriginal) + GPSIFD (lat/lng). All offsets are
// relative to the TIFF header start, per the EXIF spec.
function buildExifJpeg({ make, model, dto, lat, latRef, lon, lonRef }) {
    // We lay out the TIFF block manually. Layout after the 8-byte TIFF header:
    //   IFD0 (entries), then ExifIFD, then GPSIFD, then the out-of-line value area.
    const enc = new TextEncoder();

    // --- compute out-of-line values ---
    const makeBytes = enc.encode(make + '\0');
    const modelBytes = enc.encode(model + '\0');
    const dtoBytes = enc.encode(dto + '\0'); // "YYYY:MM:DD HH:MM:SS\0" = 20 bytes

    // GPS lat/lon = 3 rationals each (deg/min/sec), 24 bytes each.
    const rationals = (deg, min, sec) => {
        const b = new DataView(new ArrayBuffer(24));
        b.setUint32(0, deg, true); b.setUint32(4, 1, true);
        b.setUint32(8, min, true); b.setUint32(12, 1, true);
        // sec stored with denominator 100 for a fractional second value
        b.setUint32(16, Math.round(sec * 100), true); b.setUint32(20, 100, true);
        return new Uint8Array(b.buffer);
    };
    const latBytes = rationals(...lat);
    const lonBytes = rationals(...lon);

    // Number of IFD0 entries: Make, Model, ExifIFDPointer, GPSIFDPointer.
    const ifd0Count = 4;
    const ifd0Size = 2 + ifd0Count * 12 + 4;
    // ExifIFD: DateTimeOriginal only.
    const exifCount = 1;
    const exifSize = 2 + exifCount * 12 + 4;
    // GPSIFD: LatRef, Lat, LonRef, Lon.
    const gpsCount = 4;
    const gpsSize = 2 + gpsCount * 12 + 4;

    const ifd0Off = 8;
    const exifOff = ifd0Off + ifd0Size;
    const gpsOff = exifOff + exifSize;
    let valOff = gpsOff + gpsSize;

    const makeOff = valOff; valOff += makeBytes.length;
    const modelOff = valOff; valOff += modelBytes.length;
    const dtoOff = valOff; valOff += dtoBytes.length;
    const latOff = valOff; valOff += latBytes.length;
    const lonOff = valOff; valOff += lonBytes.length;

    const total = valOff;
    const buf = new ArrayBuffer(total);
    const dv = new DataView(buf);
    const u8 = new Uint8Array(buf);

    // TIFF header (little-endian).
    dv.setUint16(0, 0x4949, false); // "II"
    dv.setUint16(2, 42, true);
    dv.setUint32(4, ifd0Off, true);

    // Helper to write one 12-byte IFD entry. The value/offset always occupies
    // bytes 8-11 (inline for ≤4-byte values, an offset otherwise).
    const writeEntry = (base, i, tag, type, count, valueOrOffset) => {
        const e = base + 2 + i * 12;
        dv.setUint16(e, tag, true);
        dv.setUint16(e + 2, type, true);
        dv.setUint32(e + 4, count, true);
        dv.setUint32(e + 8, valueOrOffset, true);
    };

    // ASCII values ≤4 bytes are stored inline (bytes 8-11), per the TIFF spec;
    // longer ones go out-of-line at an offset. Pack a short value little-endian.
    const inlineAscii = (bytes) => {
        let v = 0;
        for (let i = 0; i < bytes.length && i < 4; i++) v |= bytes[i] << (8 * i);
        return v >>> 0;
    };
    const asciiField = (bytes, off) => (bytes.length <= 4 ? inlineAscii(bytes) : off);

    // IFD0
    dv.setUint16(ifd0Off, ifd0Count, true);
    writeEntry(ifd0Off, 0, 0x010F, 2, makeBytes.length, asciiField(makeBytes, makeOff));    // Make (ASCII)
    writeEntry(ifd0Off, 1, 0x0110, 2, modelBytes.length, asciiField(modelBytes, modelOff)); // Model (ASCII)
    writeEntry(ifd0Off, 2, 0x8769, 4, 1, exifOff);                  // ExifIFDPointer
    writeEntry(ifd0Off, 3, 0x8825, 4, 1, gpsOff);                   // GPSIFDPointer
    dv.setUint32(ifd0Off + 2 + ifd0Count * 12, 0, true);            // next IFD = 0

    // ExifIFD
    dv.setUint16(exifOff, exifCount, true);
    writeEntry(exifOff, 0, 0x9003, 2, dtoBytes.length, asciiField(dtoBytes, dtoOff)); // DateTimeOriginal
    dv.setUint32(exifOff + 2 + exifCount * 12, 0, true);

    // GPSIFD — 2-byte ASCII refs fit inline (first byte = N/S/E/W, then NUL).
    dv.setUint16(gpsOff, gpsCount, true);
    const refInline = (c) => c.charCodeAt(0); // little-endian: first byte is the char
    writeEntry(gpsOff, 0, 0x0001, 2, 2, refInline(latRef));        // GPSLatitudeRef
    writeEntry(gpsOff, 1, 0x0002, 5, 3, latOff);                   // GPSLatitude (3 rationals)
    writeEntry(gpsOff, 2, 0x0003, 2, 2, refInline(lonRef));        // GPSLongitudeRef
    writeEntry(gpsOff, 3, 0x0004, 5, 3, lonOff);                   // GPSLongitude
    dv.setUint32(gpsOff + 2 + gpsCount * 12, 0, true);

    // Values.
    u8.set(makeBytes, makeOff);
    u8.set(modelBytes, modelOff);
    u8.set(dtoBytes, dtoOff);
    u8.set(latBytes, latOff);
    u8.set(lonBytes, lonOff);

    // Wrap the TIFF block in a JPEG: SOI + APP1("Exif\0\0" + TIFF) + EOI.
    const exifHeader = enc.encode('Exif'); // 4 bytes
    const app1Payload = new Uint8Array(6 + total);
    app1Payload.set(exifHeader, 0);
    app1Payload[4] = 0; app1Payload[5] = 0; // "\0\0"
    app1Payload.set(u8, 6);
    const app1Len = app1Payload.length + 2; // includes the 2 length bytes

    const jpeg = [];
    jpeg.push(0xFF, 0xD8); // SOI
    jpeg.push(0xFF, 0xE1, (app1Len >> 8) & 0xFF, app1Len & 0xFF);
    for (const b of app1Payload) jpeg.push(b);
    jpeg.push(0xFF, 0xD9); // EOI
    return new Uint8Array(jpeg);
}

describe('readJpegExif', () => {
    it('extracts date, GPS and camera from a JPEG EXIF block', () => {
        const bytes = buildExifJpeg({
            make: 'Apple', model: 'iPhone 15 Pro',
            dto: '2024:06:30 14:22:05',
            lat: [48, 12, 29.5], latRef: 'N',
            lon: [16, 22, 5.7], lonRef: 'E',
        });
        const out = readJpegExif(bytes);
        expect(out.camera).toBe('Apple iPhone 15 Pro');
        // DateTimeOriginal parsed as local wall-clock → ISO.
        expect(out.taken_at).toBe(new Date(2024, 5, 30, 14, 22, 5).toISOString());
        expect(out.lat).toBeCloseTo(48 + 12 / 60 + 29.5 / 3600, 5);
        expect(out.lon).toBeCloseTo(16 + 22 / 60 + 5.7 / 3600, 5);
    });

    it('applies S/W hemisphere as a negative coordinate', () => {
        const bytes = buildExifJpeg({
            make: 'Canon', model: 'EOS',
            dto: '2020:01:02 03:04:05',
            lat: [33, 51, 0], latRef: 'S',
            lon: [151, 12, 0], lonRef: 'W',
        });
        const out = readJpegExif(bytes);
        expect(out.lat).toBeLessThan(0);
        expect(out.lon).toBeLessThan(0);
        expect(out.lat).toBeCloseTo(-(33 + 51 / 60), 5);
    });

    it('is fail-safe: non-JPEG / garbage input yields all-null', () => {
        expect(readJpegExif(new Uint8Array([1, 2, 3, 4]))).toEqual({ taken_at: null, lat: null, lon: null, camera: null });
        expect(readJpegExif(new Uint8Array(0))).toEqual({ taken_at: null, lat: null, lon: null, camera: null });
        // A JPEG with no EXIF (SOI + EOI only).
        expect(readJpegExif(new Uint8Array([0xFF, 0xD8, 0xFF, 0xD9]))).toEqual({ taken_at: null, lat: null, lon: null, camera: null });
    });

    it('accepts an ArrayBuffer as well as a Uint8Array', () => {
        const bytes = buildExifJpeg({
            make: 'X', model: 'Y', dto: '2021:12:25 00:00:00',
            lat: [10, 0, 0], latRef: 'N', lon: [20, 0, 0], lonRef: 'E',
        });
        const out = readJpegExif(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength));
        expect(out.camera).toBe('X Y');
        expect(out.lat).toBeCloseTo(10, 6);
    });
});
