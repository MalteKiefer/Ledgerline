/**
 * Ledgerline service worker.
 *
 * Deliberately conservative for an authenticated app:
 *  - navigations are network-first and fall back to the offline page,
 *  - hashed build assets (/build/...) are cached forever, first hit wins,
 *  - everything else (JSON APIs, DAV, uploads) goes straight to the network.
 *
 * Bump CACHE whenever the precached set changes; activate() drops old caches.
 */
const CACHE = 'll-v3';
const PRECACHE = ['/offline.html', '/icon-192.png', '/icon-512.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Public visitor pages (upload/download/share links) and DAV must never be
    // handled by the app shell — let the browser fetch them directly.
    if (/^\/(u|f|p|dav)\//.test(url.pathname)) return;

    // App navigations: try the network, fall back to the offline page. Never
    // pass a redirected response to a navigation (Chrome rejects those).
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).then((res) => (res.redirected ? Response.redirect(res.url, 302) : res))
                .catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Vite build assets are content-hashed — cache-first is always correct.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, copy));
                }
                return res;
            }))
        );
    }
});
