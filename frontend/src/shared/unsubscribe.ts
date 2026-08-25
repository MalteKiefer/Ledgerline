/**
 * Reads the List-Unsubscribe header out of a message's raw headers.
 *
 * Pure: header text in, links out — so the parsing can be tested against what
 * real newsletters send rather than guessed at.
 *
 * RFC 2369 puts one or more targets in angle brackets, comma-separated, and
 * they may be `mailto:` or `http(s):`. RFC 8058 adds List-Unsubscribe-Post,
 * which marks a URL as safe to POST to for one-click unsubscribe — we do NOT
 * use it: posting on the user's behalf would confirm to a sender that the
 * address is live, and doing it from the server would additionally tell them
 * this server's address. The link is opened in the browser instead, where the
 * user sees where it goes and decides.
 */
export interface UnsubscribeTarget {
  kind: 'mailto' | 'http';
  /** The raw target, ready to hand to a mail composer or a new tab. */
  value: string;
  /** For a mailto: the address, for an http: the host — what to show the user. */
  label: string;
}

/** Headers can fold across lines; a value may continue on an indented line. */
function unfold(headers: string): string {
  return headers.replace(/\r?\n[ \t]+/g, ' ');
}

export function parseUnsubscribe(headersRaw: string | null | undefined): UnsubscribeTarget[] {
  if (!headersRaw) return [];
  const line = unfold(headersRaw)
    .split(/\r?\n/)
    .find((l) => /^list-unsubscribe\s*:/i.test(l));
  if (!line) return [];

  const out: UnsubscribeTarget[] = [];
  for (const m of line.matchAll(/<([^>]+)>/g)) {
    const raw = m[1].trim();
    if (/^mailto:/i.test(raw)) {
      const address = raw.slice('mailto:'.length).split('?')[0];
      // A mailto: with no address is not a target, it is a malformed header.
      if (address.includes('@')) out.push({ kind: 'mailto', value: raw, label: address });
      continue;
    }
    if (/^https?:\/\//i.test(raw)) {
      try {
        out.push({ kind: 'http', value: raw, label: new URL(raw).host });
      } catch {
        // An unparseable URL is not offered: the label would be the raw string
        // and the user could not tell where the link goes.
      }
    }
    // Anything else (ftp:, javascript:, a bare word) is ignored on purpose.
  }
  return out;
}
