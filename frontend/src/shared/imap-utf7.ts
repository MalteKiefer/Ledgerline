/**
 * Decodes IMAP's own flavour of UTF-7 (RFC 3501 §5.1.3) for display.
 *
 * A folder called "Entwürfe" travels the wire as `Entw&APw-rfe`, and that is
 * what the server means by its name — so it is what we store and what we send
 * back. Only the label a person reads is decoded; nothing that goes to the
 * server passes through here, which is why there is no encoder: re-encoding a
 * name we already hold in wire form would be a way to corrupt it.
 *
 * Differences from real UTF-7, both of which this handles:
 *  - the shift character is `&`, not `+`
 *  - base64 uses `,` where standard base64 uses `/`
 *  - `&-` is a literal ampersand
 */
export function decodeImapFolder(name: string): string {
  if (!name.includes('&')) return name;

  let out = '';
  let i = 0;
  while (i < name.length) {
    const amp = name.indexOf('&', i);
    if (amp === -1) {
      out += name.slice(i);
      break;
    }
    out += name.slice(i, amp);

    const end = name.indexOf('-', amp + 1);
    if (end === -1) {
      // Unterminated shift: not valid, so the rest is shown as it is rather
      // than guessed at.
      out += name.slice(amp);
      break;
    }

    const chunk = name.slice(amp + 1, end);
    if (chunk === '') {
      out += '&'; // "&-" is a literal ampersand.
    } else {
      const decoded = decodeChunk(chunk);
      // A chunk we cannot decode is shown verbatim: a mangled label is better
      // than a silently wrong one.
      out += decoded ?? name.slice(amp, end + 1);
    }
    i = end + 1;
  }
  return out;
}

/** modified-base64 (`,` for `/`) of UTF-16BE code units. */
function decodeChunk(chunk: string): string | null {
  try {
    const b64 = chunk.replace(/,/g, '/');
    const bin = atob(b64.padEnd(Math.ceil(b64.length / 4) * 4, '='));
    if (bin.length % 2 !== 0) return null; // not whole UTF-16 units
    let out = '';
    for (let i = 0; i < bin.length; i += 2) {
      out += String.fromCharCode((bin.charCodeAt(i) << 8) | bin.charCodeAt(i + 1));
    }
    return out;
  } catch {
    return null;
  }
}
