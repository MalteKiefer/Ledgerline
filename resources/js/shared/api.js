// Shared JSON request helpers for the reload-free module clients (notes / todos
// / bookmarks / files / …). One definition so a change to the CSRF/accept
// handling or error behaviour applies everywhere.

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function jsonHeaders() {
    return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() };
}

export async function apiRequest(method, url, body) {
    const res = await fetch(url, { method, headers: jsonHeaders(), body: body ? JSON.stringify(body) : undefined });
    if (! res.ok) throw new Error('request failed');
    return res.json().catch(() => ({}));
}

export async function getJson(url) {
    const res = await fetch(url, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (! res.ok) throw new Error(`getJson ${res.status}: ${url}`);
    return res.json();
}

export async function postForm(url, body, method = 'POST') {
    const res = await fetch(url, { method, headers: jsonHeaders(), body: body != null ? JSON.stringify(body) : undefined });
    if (! res.ok) throw new Error(`request failed: ${res.status}`);
    return res.json().catch(() => ({}));
}
