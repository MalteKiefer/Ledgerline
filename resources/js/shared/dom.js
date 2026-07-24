// Small DOM/string helpers shared across modules.

import { is12h } from './prefs';

export function escapeHtml(text) {
    return String(text ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Format a date/time value for display. Returns '' for falsy or unparseable input.
// Default options match the files/gallery display (medium date + 2-digit time).
// Pass an opts object to override for callers that need a different format (e.g.
// date-only with month: 'long' for the public share viewer).
export function formatDate(v, opts) {
    if (! v) return '';
    const d = new Date(v);
    if (isNaN(d.getTime())) return '';
    let o = opts || { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    // Honour the user's 12/24h clock preference for any time-bearing format, unless
    // the caller pinned hour12 explicitly.
    const hasTime = o.timeStyle || o.hour || o.minute || o.second;
    if (hasTime && o.hour12 === undefined) o = { ...o, hour12: is12h() };
    return d.toLocaleString(undefined, o);
}

// Trigger a client-side download of raw bytes without ever touching the server.
export function saveBlobAs(bytes, name, mime) {
    const url = URL.createObjectURL(new Blob([bytes], { type: mime || 'application/octet-stream' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = name || 'download';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10000);
}
