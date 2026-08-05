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

// Whether the mail is flagged as spam by the receiving server (SpamAssassin /
// Rspamd / generic X-Spam-* headers).
export function isSpam(headers) {
    if ((headers['x-spam-flag'] || '').toLowerCase() === 'yes') return true;
    if (/^\s*yes\b/i.test(headers['x-spam-status'] || '')) return true;
    if ((headers['x-spamd-result'] || '').toLowerCase().includes('default: true')) return true;
    if (/\bjunk\b/i.test(headers['x-spam'] || '')) return true;
    return false;
}

// Parse the Authentication-Results header into { spf, dkim, dmarc } — each the
// mechanism's verdict (pass/fail/softfail/neutral/none/…) or null if absent.
export function parseAuthResults(headers) {
    const ar = (headers['authentication-results'] || headers['arc-authentication-results'] || '').toLowerCase();
    const pick = (name) => {
        const m = new RegExp(`(?:^|;|\\s)${name}=(pass|fail|softfail|hardfail|neutral|none|temperror|permerror|policy|bestguesspass)`).exec(ar);
        return m ? m[1] : null;
    };
    return { spf: pick('spf'), dkim: pick('dkim'), dmarc: pick('dmarc') };
}

// The ORIGINAL header block (verbatim, not unfolded/lowercased) for a
// "show original headers" view. `input` is the raw RFC822 (bytes or string).
export function rawHeaderBlock(input) {
    const text = typeof input === 'string' ? input : decodeBytes(input, 'latin1');
    const norm = text.replace(/\r\n/g, '\n');
    const sep = norm.indexOf('\n\n');
    return (sep === -1 ? norm : norm.slice(0, sep)).trim();
}

// The list-row envelope: decoded From / To / Subject, an ISO-ish Date, the
// attachment flag, and a spam flag. `input` is the RFC822 message (bytes/string).
export function parseEnvelope(input) {
    const { headers, body } = splitMessage(input);
    return {
        from: decodeWords(headers.from || ''),
        to: decodeWords(headers.to || ''),
        subject: decodeWords(headers.subject || ''),
        date: headers.date || '',
        hasAttachment: hasAttachment(headers, body),
        spam: isSpam(headers),
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

// Byte-exact latin1 string -> bytes (every code point 0..255 is one byte).
function latin1ToBytes(s) {
    const out = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) out[i] = s.charCodeAt(i) & 0xff;
    return out;
}

// Decode a leaf part's Content-Transfer-Encoding to raw bytes.
function decodeTransfer(latin1Body, encoding) {
    const enc = (encoding || '7bit').toLowerCase().trim();
    if (enc === 'base64') return base64ToBytes(latin1Body);
    if (enc === 'quoted-printable') {
        const collapsed = latin1Body.replace(/=\r?\n/g, ''); // soft line breaks
        const bytes = [];
        for (let i = 0; i < collapsed.length; i++) {
            const c = collapsed[i];
            if (c === '=' && i + 2 < collapsed.length) {
                bytes.push(parseInt(collapsed.substr(i + 1, 2), 16) || 0);
                i += 2;
            } else {
                bytes.push(collapsed.charCodeAt(i) & 0xff);
            }
        }
        return Uint8Array.from(bytes);
    }
    // 7bit / 8bit / binary: the latin1 string is already byte-exact.
    return latin1ToBytes(latin1Body);
}

// Pull a parameter (e.g. boundary=, filename=, name=) out of a structured
// header value, honouring quotes and RFC 2047 encoded-words. Also handles the
// simplest RFC 2231 `name*=charset''percent-encoded` continuation-less form.
function headerParam(value, key) {
    if (!value) return '';
    const ext = new RegExp(`${key}\\*\\s*=\\s*([^;]+)`, 'i').exec(value);
    if (ext) {
        const raw = ext[1].trim().replace(/^"|"$/g, '');
        const m = /^([^']*)'[^']*'(.*)$/.exec(raw);
        const pct = (m ? m[2] : raw).replace(/%([0-9a-f]{2})/gi, (_x, h) => String.fromCharCode(parseInt(h, 16)));
        try { return decodeBytes(latin1ToBytes(pct), m ? m[1] : 'utf-8'); } catch { return pct; }
    }
    const q = new RegExp(`${key}\\s*=\\s*"([^"]*)"`, 'i').exec(value);
    if (q) return decodeWords(q[1]);
    const b = new RegExp(`${key}\\s*=\\s*([^;\\s]+)`, 'i').exec(value);
    return b ? decodeWords(b[1]) : '';
}

// Recursively collect leaf MIME parts from a raw (latin1) message string.
// Returns { textBody, htmlBody, attachments:[{filename,contentType,contentId,inline,size,bytes}] }.
function walkParts(latin1, depth, acc) {
    if (depth > 20) return; // pathological nesting guard
    const { headers, body } = splitMessage(latin1);
    const ctype = (headers['content-type'] || 'text/plain').toLowerCase();
    const disposition = (headers['content-disposition'] || '').toLowerCase();

    if (ctype.startsWith('multipart/')) {
        const boundary = headerParam(headers['content-type'], 'boundary');
        if (!boundary) return;
        const marker = '--' + boundary;
        const segments = body.split(marker);
        // segments[0] is the preamble; the last is the epilogue (after --boundary--).
        for (let i = 1; i < segments.length; i++) {
            let seg = segments[i];
            if (seg.startsWith('--')) break; // closing boundary
            seg = seg.replace(/^\r?\n/, '');   // drop the CRLF after the boundary line
            walkParts(seg, depth + 1, acc);
        }
        return;
    }

    // Leaf part.
    const encoding = headers['content-transfer-encoding'] || '7bit';
    const filename = headerParam(disposition, 'filename') || headerParam(headers['content-type'], 'name');
    // Content-ID identifies an inline (cid:) part referenced from the HTML body;
    // strip the surrounding angle brackets so it matches a `cid:...` reference.
    const contentId = (headers['content-id'] || '').trim().replace(/^<|>$/g, '');
    const isInline = contentId !== '' || disposition.startsWith('inline');
    const isAttachment = disposition.startsWith('attachment') || (filename !== '' && !ctype.startsWith('multipart/'));

    if (!isAttachment && ctype.startsWith('text/plain') && !acc.textBody) {
        acc.textBody = decodeBytes(decodeTransfer(body, encoding), headerParam(headers['content-type'], 'charset'));
        return;
    }
    if (!isAttachment && ctype.startsWith('text/html') && !acc.htmlBody) {
        acc.htmlBody = decodeBytes(decodeTransfer(body, encoding), headerParam(headers['content-type'], 'charset'));
        return;
    }
    // Capture real attachments AND inline (cid) images — the latter carry a
    // Content-ID and are resolved into the body as data: URIs at render time.
    if (isAttachment || (isInline && !ctype.startsWith('text/'))) {
        const bytes = decodeTransfer(body, encoding);
        acc.attachments.push({
            filename: filename || (contentId ? contentId.split('@')[0] : 'attachment'),
            contentType: ctype.split(';')[0].trim() || 'application/octet-stream',
            contentId,
            inline: contentId !== '',
            size: bytes.length,
            bytes,
        });
    }
}

// Full parse for the message/attachments modal: the envelope plus the text/html
// body and every attachment (filename, type, size, decoded bytes). `input` is
// the decrypted RFC822 message (bytes or string).
export function parseMessage(input) {
    const latin1 = typeof input === 'string' ? input : decodeBytes(input, 'latin1');
    const acc = { textBody: '', htmlBody: '', attachments: [] };
    walkParts(latin1, 0, acc);
    return { envelope: parseEnvelope(latin1), ...acc };
}

// Extract just the display name (or address) from a "Name <addr@host>" field.
export function displayAddress(field) {
    if (!field) return '';
    const m = field.match(/^\s*"?([^"<]*?)"?\s*<([^>]+)>/);
    if (m) return (m[1].trim() || m[2].trim());
    return field.trim();
}
