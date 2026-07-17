// Background service worker: the only place the vault key and plaintext secrets
// live (in memory / chrome.storage.session — never written to disk). The popup
// and content scripts hold no secrets; they message this worker.
import * as api from './api.js';
import { deriveVaultKey, openManifest, b64, fromB64 } from './crypto.js';

// Decrypted secrets cache for this worker lifetime (re-derived from the session
// VK if the worker was recycled). Never persisted.
let SECRETS = null;

const local = {
    get: (k) => new Promise((r) => chrome.storage.local.get(k, (v) => r(v))),
    set: (o) => new Promise((r) => chrome.storage.local.set(o, r)),
    clear: () => new Promise((r) => chrome.storage.local.remove(['serverUrl', 'token'], r)),
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
    const store = await api.getStore(serverUrl, token);
    if (! store.ciphertext) { SECRETS = []; return SECRETS; }
    const manifest = await openManifest(store.ciphertext, await fromB64(vkB64));
    SECRETS = (manifest.secrets || []).filter((s) => ! s.trashed);
    return SECRETS;
}

// Hostname of a login's first URL.
function hostOf(url) {
    try { return new URL(/^https?:\/\//.test(url) ? url : 'https://' + url).hostname.replace(/^www\./, ''); } catch (e) { return ''; }
}
// A page host matches a stored host if either is a suffix of the other on a
// dot boundary (so accounts.example.com matches example.com and vice versa).
function hostsMatch(page, stored) {
    if (! page || ! stored) return false;
    page = page.replace(/^www\./, ''); stored = stored.replace(/^www\./, '');
    return page === stored || page.endsWith('.' + stored) || stored.endsWith('.' + page);
}

function loginView(s) {
    return {
        id: s.id,
        title: s.title || '',
        username: s.fields?.username || '',
        password: s.fields?.password || '',
        urls: (s.fields?.urls || []).filter(Boolean),
    };
}

async function matchFor(hostname) {
    const secrets = await ensureSecrets();
    return secrets
        .filter((s) => s.type === 'login')
        .filter((s) => (s.fields?.urls || []).some((u) => hostsMatch(hostname, hostOf(u))))
        .map(loginView);
}

async function search(query) {
    const secrets = await ensureSecrets();
    const q = (query || '').toLowerCase();
    return secrets
        .filter((s) => s.type === 'login')
        .filter((s) => ! q || (s.title || '').toLowerCase().includes(q) || (s.fields?.username || '').toLowerCase().includes(q) || (s.fields?.urls || []).some((u) => u.toLowerCase().includes(q)))
        .slice(0, 50)
        .map(loginView);
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
        const vault = await api.getVault(serverUrl, token);
        if (! vault.configured) throw new Error('no vault');
        const vk = await deriveVaultKey(passphrase, vault); // throws on wrong passphrase
        await session.set({ vk: await b64(vk) });
        SECRETS = null;
        await ensureSecrets();
        return { ok: true };
    },
    async lock() { SECRETS = null; await session.clear(); return { ok: true }; },
    async unpair() {
        const { serverUrl, token } = await creds();
        if (serverUrl && token) await api.logout(serverUrl, token);
        SECRETS = null; await session.clear(); await local.clear();
        return { ok: true };
    },
    async match({ hostname }) { return { logins: await matchFor(hostname) }; },
    async search({ query }) { return { logins: await search(query) }; },
};

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
    const fn = handlers[msg?.type];
    if (! fn) return false;
    Promise.resolve(fn(msg)).then((r) => sendResponse({ ok: true, ...r })).catch((e) => sendResponse({ ok: false, error: String(e?.message || e) }));
    return true; // async
});
