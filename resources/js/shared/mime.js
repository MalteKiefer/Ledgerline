// Minimal, dependency-free RFC822/MIME parsing for the mail archive's LIST view.
// Runs entirely client-side on the DECRYPTED message bytes — the server never
// sees any of this. For the list we only need the envelope fields (From / To /
// Subject / Date) plus a cheap "has attachment" flag; full MIME-part extraction
// for the attachments modal is a separate concern.
//
// Pure functions, no DOM, no libsodium — unit-testable in isolation.

// Decode a Uint8Array of the given charset label to a JS string, tolerant of
// unknown labels (falls back to utf-8, then latin1) and never throwing.
function decodeBytes(bytes, charset) {
    const label = (charset || 'utf-8').toLowerCase().trim();
    for (const enc of [label, 'utf-8', 'iso-8859-1']) {
        try {
            return new TextDecoder(enc, { fatal: false }).decode(bytes);
        } catch {
            // try the next fallback label
        }
    }
    // Last resort: raw latin1 mapping (every byte is a valid code point).
    let s = '';
    for (const b of bytes) s += String.fromCharCode(b);
    return s;
}

function base64ToBytes(b64) {
    const clean = b64.replace(/\s+/g, '');
    try {
        const bin = atob(clean);
        const out = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out;
    } catch {
        return new Uint8Array(0);
    }
}

// RFC 2047 "encoded-word" decoding: =?charset?B|Q?text?= — used in Subject/From/To
// for non-ASCII. Adjacent encoded-words separated only by whitespace collapse
// (the whitespace is not part of the text). Anything that is not an encoded-word
// passes through unchanged.
export function decodeWords(str) {
    if (typeof str !== 'string' || str === '') return str || '';
    // Collapse whitespace that sits BETWEEN two encoded-words (RFC 2047 §6.2).
    const joined = str.replace(/\?=\s+=\?/g, '?==?');
    return joined.replace(/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/g, (_m, charset, enc, text) => {
        if (enc.toUpperCase() === 'B') {
            return decodeBytes(base64ToBytes(text), charset);
        }
        // Q-encoding: '_' is a space, =XX is a hex byte.
        const bytes = [];
        for (let i = 0; i < text.length; i++) {
            const c = text[i];
            if (c === '_') {
                bytes.push(0x20);
            } else if (c === '=' && i + 2 < text.length) {
                bytes.push(parseInt(text.substr(i + 1, 2), 16) || 0);
                i += 2;
            } else {
                bytes.push(c.charCodeAt(0));
            }
        }
        return decodeBytes(Uint8Array.from(bytes), charset);
    });
}

// Split a raw RFC822 message into its header block and body, and parse headers
// into a lower-cased name → value map (unfolded; first occurrence wins). Input
// may be a Uint8Array (decrypted bytes) or a string.
export function splitMessage(input) {
    const text = typeof input === 'string' ? input : decodeBytes(input, 'latin1');
    const norm = text.replace(/\r\n/g, '\n');
    const sep = norm.indexOf('\n\n');
    const headerBlock = sep === -1 ? norm : norm.slice(0, sep);
    const body = sep === -1 ? '' : norm.slice(sep + 2);

    // Unfold continuation lines (a line starting with space/tab continues the
    // previous header).
    const unfolded = headerBlock.replace(/\n[ \t]+/g, ' ');
    const headers = {};
    for (const line of unfolded.split('\n')) {
        const idx = line.indexOf(':');
        if (idx === -1) continue;
        const name = line.slice(0, idx).trim().toLowerCase();
        if (name === '') continue;
        if (!(name in headers)) headers[name] = line.slice(idx + 1).trim();
    }
    return { headers, body };
}

// Cheap "does this message carry an attachment" heuristic for the list column.
// True when any part declares an attachment disposition or a filename, or the
// top-level type is multipart/mixed (the canonical attachment container). This
// scans the raw text, so it needs no full MIME tree walk.
export function hasAttachment(headers, body) {
    const ctype = (headers['content-type'] || '').toLowerCase();
    if (ctype.startsWith('multipart/mixed')) return true;
    const hay = (typeof body === 'string' ? body : '').toLowerCase();
    if (/content-disposition:\s*attachment/.test(hay)) return true;
    // A filename/name parameter on a part header is a strong attachment signal.
    if (/content-disposition:[^\n]*\bfilename\s*=/.test(hay)) return true;
    return false;
}

// The list-row envelope: decoded From / To / Subject, an ISO-ish Date, and the
// attachment flag. `input` is the decrypted RFC822 message (bytes or string).
export function parseEnvelope(input) {
    const { headers, body } = splitMessage(input);
    return {
        from: decodeWords(headers.from || ''),
        to: decodeWords(headers.to || ''),
        subject: decodeWords(headers.subject || ''),
        date: headers.date || '',
        hasAttachment: hasAttachment(headers, body),
    };
}

// Best-effort parse of an RFC 2822 Date header to a JS Date (for client-side
// sorting/formatting). Returns null on failure — the caller falls back to the
// ledger's archived-at timestamp.
export function parseDate(dateStr) {
    if (!dateStr) return null;
    const t = Date.parse(dateStr);
    return Number.isNaN(t) ? null : new Date(t);
}

// Extract just the display name (or address) from a "Name <addr@host>" field.
export function displayAddress(field) {
    if (!field) return '';
    const m = field.match(/^\s*"?([^"<]*?)"?\s*<([^>]+)>/);
    if (m) return (m[1].trim() || m[2].trim());
    return field.trim();
}
