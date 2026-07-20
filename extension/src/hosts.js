// Shared host-matching utilities for autofill + passkey rpId binding.
// hostsMatch rejects bare TLDs (no dot after www-strip) — parity with
// rpIdAllowed; prevents over-broad autofill host matching.

// Normalise a URL or raw hostname to a hostname string.
// Prepends a scheme when none is present so `new URL()` parses correctly;
// strips leading www. afterwards (scheme is discarded — only the host matters).
export function hostOf(input) {
    try {
        return new URL(/^https?:\/\//.test(input) ? input : 'https://' + input).hostname.replace(/^www\./, '');
    } catch (e) { return ''; }
}

// True iff credential for `stored` should be offered on page with host `page`.
// Direction: parent→child only (example.com fills on accounts.example.com, never
// the reverse — a child-domain credential must NOT surface on the parent origin).
// Bare TLDs (no dot after www-strip) are REJECTED — 'com' would match every site.
// www. is stripped from both sides before comparison.
export function hostsMatch(page, stored) {
    if (! page || ! stored) return false;
    page = page.replace(/^www\./, '');
    stored = stored.replace(/^www\./, '');
    // Reject bare TLDs
    if (! stored.includes('.')) return false;
    return page === stored || page.endsWith('.' + stored);
}

// Match score for a login against the current tab host: 1 if any URL matches, 0 otherwise.
// Used by popup.js to sort + prefilter items by relevance.
export function matchScore(lg, h) {
    return lg.type === 'login' && (lg.urls || []).some((u) => hostsMatch(h, hostOf(u))) ? 1 : 0;
}
