// Contact name/sort helpers, shared by the contacts module and the gallery
// People panel so the two render linked names and ordering identically.

// Split a contact record into { first, last } — falling back to a "First Last"
// FN split.
export function contactNameParts(c) {
    if (! c) return { first: '', last: '' };
    let first = (c.first || '').trim(), last = (c.last || '').trim();
    if (! first && ! last) {
        const p = (c.fn || '').trim().split(/\s+/).filter(Boolean);
        if (p.length > 1) { last = p.pop(); first = p.join(' '); } else if (p.length === 1) { first = p[0]; }
    }
    return { first, last };
}

// Canonical contact display label — always "Last, First".
export function contactDisplayName(c) {
    const { first, last } = contactNameParts(c);
    if (last || first) return [last, first].filter(Boolean).join(', ');
    return (c?.fn || c?.org || (c?.emails ?? [])[0]?.value || '').trim();
}

// The contacts module persists its sort mode in localStorage; the gallery reads
// the same pref so People ordering follows the contacts setting.
export function contactsSortPref() {
    try { return (JSON.parse(localStorage.getItem('ll-contacts-prefs') || '{}').sortBy) || 'name'; } catch (e) { return 'name'; }
}
