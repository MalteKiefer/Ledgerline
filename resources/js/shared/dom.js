// Small DOM/string helpers shared across modules.

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
    return d.toLocaleString(undefined, opts || { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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
