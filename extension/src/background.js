// Background service worker: the only place the vault key and plaintext secrets
// live (in memory / chrome.storage.session — never written to disk). The popup
// and content scripts hold no secrets; they message this worker.
import * as api from './api.js';
import { deriveVaultKey, openManifest, b64, fromB64 } from './crypto.js';

// Decrypted secrets cache for this worker lifetime (re-derived from the session
// VK if the worker was recycled). Never persisted.
let SECRETS = null;
let FOLDERS = [];

const local = {
    get: (k) => new Promise((r) => chrome.storage.local.get(k, (v) => r(v))),
    set: (o) => new Promise((r) => chrome.storage.local.set(o, r)),
    clear: () => new Promise((r) => chrome.storage.local.remove(['serverUrl', 'token', 'storeCipher', 'vaultMeta', 'tfaDomains', 'tfaAt'], r)),
};
const session = {
    get: (k) => new Promise((r) => chrome.storage.session.get(k, (v) => r(v))),
    set: (o) => new Promise((r) => chrome.storage.session.set(o, r)),
    clear: () => new Promise((r) => chrome.storage.session.remove(['vk'], r)),
};

async function creds() { return local.get(['serverUrl', 'token']); }

async function getVk() {
    const { vk } = await session.get('vk');
    return vk ? fromB64(vk) : null;
}

// Ensure SECRETS is loaded: fetch the sealed store, decrypt with the session VK.
async function ensureSecrets() {
    if (SECRETS) return SECRETS;
    const vkB64 = (await session.get('vk')).vk;
    if (! vkB64) throw new Error('locked');
    const { serverUrl, token } = await creds();
    if (! serverUrl || ! token) throw new Error('unpaired');
    // Fetch the sealed manifest; cache the ciphertext locally so an offline
    // browser can still decrypt with the in-session vault key. Ciphertext at
    // rest is safe — the key is never stored on disk.
    let cipher = '';
    try {
        const store = await api.getStore(serverUrl, token);
        cipher = store.ciphertext || '';
        if (cipher) await local.set({ storeCipher: cipher });
    } catch (e) {
        cipher = (await local.get('storeCipher')).storeCipher || '';
        if (! cipher) throw e;
    }
    if (! cipher) { SECRETS = []; FOLDERS = []; return SECRETS; }
    const manifest = await openManifest(cipher, await fromB64(vkB64));
    SECRETS = (manifest.secrets || []).filter((s) => ! s.trashed);
    FOLDERS = (manifest.secretFolders || []).map((f) => ({ id: f.id, name: f.name || '' }));
    return SECRETS;
}

// Hostname of a login's first URL.
function hostOf(url) {
    try { return new URL(/^https?:\/\//.test(url) ? url : 'https://' + url).hostname.replace(/^www\./, ''); } catch (e) { return ''; }
}
// A stored host matches the page only if it IS the page host or a parent of it
// on a dot boundary (a credential for example.com fills on accounts.example.com,
// never the reverse — that would let a child-domain credential surface on the
// parent origin). No shared-suffix fuzzing beyond this.
function hostsMatch(page, stored) {
    if (! page || ! stored) return false;
    page = page.replace(/^www\./, ''); stored = stored.replace(/^www\./, '');
    return page === stored || page.endsWith('.' + stored);
}

function itemView(s) {
    return {
        id: s.id,
        title: s.title || '',
        type: s.type || 'login',
        icon: s.icon || '',
        favorite: ! ! s.favorite,
        username: s.fields?.username || '',
        password: s.fields?.password || '',
        urls: (s.fields?.urls || []).filter(Boolean),
        hasTotp: ! ! (s.fields?.totp),
        note: s.fields?.note || '',
        cardholder: s.fields?.cardholder || '',
        number: s.fields?.number || '',
        expiry: s.fields?.expiry || '',
        cvv: s.fields?.cvv || '',
        tags: s.tags || [],
        folder: s.folder || null,
    };
}
const byTitle = (a, b) => (a.title || '').localeCompare(b.title || '', undefined, { sensitivity: 'base' });

// TOTP (RFC 6238, SHA-1, 6 digits) via WebCrypto — the secret never leaves the
// worker; only the current code is handed out.
function base32Decode(str) {
    const A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    str = String(str || '').toUpperCase().replace(/[^A-Z2-7]/g, '');
    let bits = 0, val = 0; const out = [];
    for (const c of str) { const i = A.indexOf(c); if (i < 0) continue; val = (val << 5) | i; bits += 5; if (bits >= 8) { out.push((val >>> (bits - 8)) & 0xff); bits -= 8; } }
    return new Uint8Array(out);
}
function totpSecret(v) {
    v = String(v || '').trim();
    if (/^otpauth:\/\//i.test(v)) { try { return new URL(v).searchParams.get('secret') || ''; } catch (e) { const m = v.match(/[?&]secret=([^&]+)/i); return m ? decodeURIComponent(m[1]) : ''; } }
    return v;
}
async function totpCode(secretRaw, period = 30) {
    const key = base32Decode(totpSecret(secretRaw));
    if (! key.length) return null;
    const now = Math.floor(Date.now() / 1000);
    const counter = Math.floor(now / period);
    const buf = new ArrayBuffer(8); const dv = new DataView(buf);
    dv.setUint32(0, Math.floor(counter / 2 ** 32)); dv.setUint32(4, counter >>> 0);
    const ck = await crypto.subtle.importKey('raw', key, { name: 'HMAC', hash: 'SHA-1' }, false, ['sign']);
    const h = new Uint8Array(await crypto.subtle.sign('HMAC', ck, buf));
    const o = h[h.length - 1] & 0xf;
    const bin = ((h[o] & 0x7f) << 24) | ((h[o + 1] & 0xff) << 16) | ((h[o + 2] & 0xff) << 8) | (h[o + 3] & 0xff);
    return { code: String(bin % 1000000).padStart(6, '0'), remain: period - (now % period) };
}

async function matchFor(hostname) {
    const secrets = await ensureSecrets();
    return secrets
        .filter((s) => s.type === 'login')
        .filter((s) => (s.fields?.urls || []).some((u) => hostsMatch(hostname, hostOf(u))))
        .map(itemView)
        .sort(byTitle);
}

async function search(query) {
    const secrets = await ensureSecrets();
    const q = (query || '').toLowerCase();
    return secrets
        .filter((s) => ! q
            || (s.title || '').toLowerCase().includes(q)
            || (s.fields?.username || '').toLowerCase().includes(q)
            || (s.fields?.urls || []).some((u) => u.toLowerCase().includes(q))
            || (s.tags || []).some((t) => t.toLowerCase().includes(q)))
        .map(itemView)
        .sort(byTitle)
        .slice(0, 300);
}

// Domains that support app-based 2FA, from the public 2fa.directory v4 dataset.
// Cached in local storage for a day; the data is public so this leaks nothing.
async function tfaDomains() {
    const cache = await local.get(['tfaDomains', 'tfaAt']);
    if (cache.tfaDomains && cache.tfaAt && Date.now() - cache.tfaAt < 86400000) return cache.tfaDomains;
    const APP = ['totp', 'u2f', 'hardware', 'fido2', 'webauthn'];
    try {
        const res = await fetch('https://api.2fa.directory/v4/all.json', { headers: { Accept: 'application/json' } });
        if (! res.ok) return cache.tfaDomains || [];
        const data = await res.json();
        const set = {};
        for (const e of (Array.isArray(data) ? data : [])) {
            const m = Array.isArray(e) ? e[1] : null;
            if (! m || typeof m !== 'object') continue;
            const tfa = (m.tfa || []).map((x) => String(x).toLowerCase());
            if (! tfa.some((t) => APP.includes(t))) continue;
            for (const d of [m.domain, ...(m['additional-domains'] || [])]) if (typeof d === 'string' && d) set[d.toLowerCase()] = 1;
        }
        const domains = Object.keys(set);
        await local.set({ tfaDomains: domains, tfaAt: Date.now() });
        return domains;
    } catch (e) { return cache.tfaDomains || []; }
}

// Poll the pairing until the owner approves it (or time out).
async function runPairing(serverUrl, code) {
    await api.pair(serverUrl, code, 'Browser Extension');
    const deadline = Date.now() + 120000;
    for (;;) {
        const r = await api.collect(serverUrl, code);
        if (r.status === 'approved') {
            await local.set({ serverUrl, token: r.token });
            return { ok: true, user: r.user };
        }
        if (Date.now() > deadline) return { ok: false, error: 'timeout' };
        await new Promise((res) => setTimeout(res, 2500));
    }
}

const handlers = {
    async getState() {
        const { serverUrl, token } = await creds();
        const vk = await session.get('vk');
        return { paired: ! ! token, unlocked: ! ! vk.vk, serverUrl: serverUrl || '' };
    },
    async pair({ serverUrl, code }) {
        return runPairing(serverUrl, code);
    },
    async unlock({ passphrase }) {
        const { serverUrl, token } = await creds();
        if (! serverUrl || ! token) throw new Error('unpaired');
        // Vault KDF params + wrapped key; cached locally (safe at rest — the
        // passphrase is still required) so unlock works offline too.
        let vault;
        try { vault = await api.getVault(serverUrl, token); await local.set({ vaultMeta: vault }); }
        catch (e) { vault = (await local.get('vaultMeta')).vaultMeta; if (! vault) throw e; }
        if (! vault.configured) throw new Error('no vault');
        const vk = await deriveVaultKey(passphrase, vault); // throws on wrong passphrase
        await session.set({ vk: await b64(vk) });
        SECRETS = null;
        await ensureSecrets();
        return { ok: true };
    },
    async lock() { SECRETS = null; await session.clear(); return { ok: true }; },
    async refresh() { SECRETS = null; await ensureSecrets(); return { count: SECRETS.length }; },
    async unpair() {
        const { serverUrl, token } = await creds();
        if (serverUrl && token) await api.logout(serverUrl, token);
        SECRETS = null; await session.clear(); await local.clear();
        return { ok: true };
    },
    async match({ hostname }) { return { logins: await matchFor(hostname) }; },
    async search({ query }) { return { logins: await search(query) }; },
    async folders() { await ensureSecrets(); return { folders: FOLDERS }; },
    // Public 2fa.directory dataset: domains that support app 2FA. Cached locally
    // (public data, not sensitive) so we hint where a login could add a code.
    async tfa() { return { domains: await tfaDomains() }; },
    // Stored cards, for autofilling payment forms (no domain match — cards work
    // on any checkout page).
    async cards() {
        const secrets = await ensureSecrets();
        return {
            cards: secrets.filter((s) => s.type === 'card').map((c) => ({
                id: c.id,
                title: c.title || '',
                cardholder: c.fields?.cardholder || '',
                number: c.fields?.number || '',
                expiry: c.fields?.expiry || '',
                cvv: c.fields?.cvv || '',
            })).sort(byTitle),
        };
    },
    // Current TOTP code for a login id (secret stays in the worker).
    async totp({ id }) {
        const secrets = await ensureSecrets();
        const s = secrets.find((x) => x.id === id);
        if (! s || ! s.fields?.totp) return { code: null };
        return (await totpCode(s.fields.totp)) || { code: null };
    },
};

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
    const fn = handlers[msg?.type];
    if (! fn) return false;
    Promise.resolve(fn(msg)).then((r) => sendResponse({ ok: true, ...r })).catch((e) => sendResponse({ ok: false, error: String(e?.message || e) }));
    return true; // async
});

// Auto-lock: drop the vault key when the OS screen locks or after idle. The
// paired token stays; only the decrypted state is cleared.
try {
    chrome.idle.setDetectionInterval(900); // 15 min idle
    chrome.idle.onStateChanged.addListener((state) => {
        if (state === 'locked' || state === 'idle') { SECRETS = null; chrome.storage.session.remove(['vk']); }
    });
} catch (e) { /* chrome.idle unavailable */ }
