// HTML-escape for safe injection into innerHTML / template strings.
// Uses ?? '' so numeric 0 and boolean false are rendered as-is, not silently
// converted to empty string (security improvement over the || '' form).
// Escapes single quotes in addition to &, <, >, " (matches app-side escapeHtml).
export function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
