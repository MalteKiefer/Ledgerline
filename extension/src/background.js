// Background service worker: the only place the vault key and plaintext secrets
// live (in memory / chrome.storage.session — never written to disk). The popup
// and content scripts hold no secrets; they message this worker.
import * as api from './api.js';
import { deriveVaultKey, openManifest, sealManifest, b64, fromB64, unwrapIdentitySecret, unwrapMlkemSecret, unwrapVaultKey } from './crypto.js';
import * as pk from './passkey.js';
import { hostOf, hostsMatch } from './hosts.js';
import { IDENTITY_FIELDS } from './identity.js';
import { listBookmarks, getBookmark, importBrowserBookmarks } from './bookmarks.js';

// Decrypted secrets cache for this worker lifetime (re-derived from the session
// VK if the worker was recycled). Never persisted.
let SECRETS = null;
let FOLDERS = [];

const local = {
    get: (k) => new Promise((r) => chrome.storage.local.get(k, (v) => r(v))),
    set: (o) => new Promise((r) => chrome.storage.local.set(o, r)),
    clear: () => new Promise((r) => chrome.storage.local.remove(['serverUrl', 'token', 'storeCipher', 'vaultMeta', 'tfaEntries', 'tfaAt'], r)),
};
const session = {
    get: (k) => new Promise((r) => chrome.storage.session.get(k, (v) => r(v))),
    set: (o) => new Promise((r) => chrome.storage.session.set(o, r)),
    clear: () => new Promise((r) => chrome.storage.session.remove(['vk'], r)),
};

async function creds() { return local.get(['serverUrl', 'token']); }

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
    const vkBytes = await fromB64(vkB64);
    let cipher = '';
    let personalReachable = true;
    try {
        const store = await api.getStore(serverUrl, token, "passwords");
        cipher = store.ciphertext || '';
        if (cipher) await local.set({ storeCipher: cipher });
    } catch (e) {
        personalReachable = false;
        cipher = (await local.get('storeCipher')).storeCipher || '';
    }
    SECRETS = [];
    FOLDERS = [];
    if (cipher) {
        const manifest = await openManifest(cipher, vkBytes);
        SECRETS = (manifest.secrets || []).filter((s) => ! s.trashed);
        FOLDERS = (manifest.secretFolders || []).map((f) => ({ id: f.id, name: f.name || '' }));
    }
    // Also surface entries from shared Tresore the user is an active member of.
    // These live in per-vault sealed stores the web app writes; the extension
    // reads them for autofill (writes still target the personal manifest).
    const sharedOk = await loadSharedVaults(serverUrl, token, vkBytes);
    // Only hard-fail if we could reach neither the personal store (no cache)
    // nor the shared vaults — i.e. we are effectively offline with nothing.
    if (! cipher && ! personalReachable && ! sharedOk) throw new Error('store fetch failed');
    return SECRETS;
}

// Load every active shared-vault membership, decrypt its sealed store, and
// append its (non-trashed) items to SECRETS. Best-effort: any failure for a
// single vault (or the whole set) is swallowed so personal autofill still works.
// Returns true if the shared-vault endpoints were reachable at all.
async function loadSharedVaults(serverUrl, token, vkBytes) {
    let keys;
    try { keys = await api.getUserKeys(serverUrl, token); } catch (e) { return false; }
    // Store v3 hybrid identity: need BOTH the X25519 sk and the ML-KEM dk to
    // unwrap vault keys (they are hybrid-wrapped X25519+ML-KEM-768, §6.3).
    if (! keys || ! keys.public_key || ! keys.wrapped_secret_key || ! keys.wrapped_mlkem_secret_key) return true; // no (hybrid) identity → no shared vaults
    let sk, mlkemSeed;
    try {
        sk = await unwrapIdentitySecret(keys.wrapped_secret_key, vkBytes);
        mlkemSeed = await unwrapMlkemSecret(keys.wrapped_mlkem_secret_key, vkBytes);
    } catch (e) { return true; }
    let vaults;
    try { vaults = await api.getVaults(serverUrl, token); } catch (e) { return false; }
    for (const v of (vaults || [])) {
        if (v.status !== 'active') continue;
        try {
            const vk = await unwrapVaultKey(v.wrapped_vault_key, sk, mlkemSeed);
            const store = await api.getVaultStore(serverUrl, token, v.vault_id);
            if (! store || ! store.sealed_manifest) continue;
            const manifest = await openManifest(store.sealed_manifest, vk);
            for (const s of (manifest.items || [])) {
                if (s.trashed) continue;
                SECRETS.push({ ...s, shared: true, vaultId: v.vault_id });
            }
        } catch (e) { /* skip this vault; others still load */ }
    }
    return true;
}

function itemView(s) {
    // Expose embedded passkeys without private key material — only metadata
    // the popup needs to display and remove entries (rpId, userName, credentialId).
    const passkeys = Array.isArray(s.fields?.passkeys)
        ? s.fields.passkeys.map((p) => ({ rpId: p.rpId || '', userName: p.userName || p.userDisplayName || '', credentialId: p.credentialId || '' }))
        : [];
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
        // Identity fields (type === 'identity').
        ...Object.fromEntries(IDENTITY_FIELDS.map((k) => [k, s.fields?.[k] || ''])),
        tags: s.tags || [],
        folder: s.folder || null,
        shared: ! ! s.shared,
        passkeys,
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

// Domains that support app-based 2FA. Fetched EXCLUSIVELY through the user's own
// server (GET /api/v1/passwords/tfa-directory) — never directly from a third
// party. The server proxies 2fa.directory (SSRF-guarded, cached) and returns an
// already-filtered { domain: docUrl } map (app-2FA methods only, http(s) docs
// only). Cached in local storage for a day.
async function tfaEntries() {
    const cache = await local.get(['tfaEntries', 'tfaAt']);
    if (cache.tfaEntries && Object.keys(cache.tfaEntries).length && cache.tfaAt && Date.now() - cache.tfaAt < 86400000) return cache.tfaEntries;
    const { serverUrl, token } = await creds();
    if (! serverUrl || ! token) return cache.tfaEntries || {};
    try {
        const entries = await api.getTfaDirectory(serverUrl, token); // { domain: docUrl }
        const map = {};
        const registrable = (d) => {
            const p = d.split('.'); const n = p.length;
            if (n <= 2) return d;
            const sld = ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu', 'gob', 'go'];
            const take = (p[n - 1].length === 2 && sld.includes(p[n - 2])) ? 3 : 2;
            return p.slice(-take).join('.');
        };
        for (const domain in entries) {
            const doc = typeof entries[domain] === 'string' ? entries[domain] : '';
            const d = domain.toLowerCase();
            map[d] = doc; // full key + bare domain
            const reg = registrable(d);
            if (! map[reg]) map[reg] = doc;
        }
        await local.set({ tfaEntries: map, tfaAt: Date.now() });
        return map;
    } catch (e) { return cache.tfaEntries || {}; }
}

// Fetch the sealed manifest, apply a mutation, re-seal with the session VK and
// write back with optimistic concurrency — retrying on a version conflict. The
// single write path for create / trash / update from the extension.
async function mutateManifest(module, fn) {
    const vkB64 = (await session.get('vk')).vk;
    if (! vkB64) throw new Error('locked');
    const { serverUrl, token } = await creds();
    if (! serverUrl || ! token) throw new Error('unpaired');
    const vk = await fromB64(vkB64);

    for (let attempt = 0; attempt < 4; attempt++) {
        const store = await api.getStore(serverUrl, token, module); // { ciphertext, version }
        const manifest = store.ciphertext ? await openManifest(store.ciphertext, vk) : {};
        if (module === 'passwords' && ! Array.isArray(manifest.secrets)) manifest.secrets = [];
        const result = fn(manifest);
        const ciphertext = await sealManifest(manifest, vk);
        const res = await api.saveStore(serverUrl, token, module, ciphertext, store.version || 0);
        if (res.ok) {
            if (module === 'passwords') {
                await local.set({ storeCipher: ciphertext });
                SECRETS = (manifest.secrets || []).filter((s) => ! s.trashed);
                FOLDERS = (manifest.secretFolders || []).map((f) => ({ id: f.id, name: f.name || '' }));
            }
            return result ?? { ok: true };
        }
        if (res.status !== 409) throw new Error('save failed');
        // 409: another device wrote in between — loop refetches and re-applies.
    }
    throw new Error('conflict');
}

// Read-only module-store fetch: derive the session VK, pull the sealed store, and
// open it — without writing anything back. Used by bookmark read handlers.
async function readManifest(module) {
    const vkB64 = (await session.get('vk')).vk;
    if (! vkB64) throw new Error('locked');
    const { serverUrl, token } = await creds();
    if (! serverUrl || ! token) throw new Error('unpaired');
    const vk = await fromB64(vkB64);
    const store = await api.getStore(serverUrl, token, module);
    return store.ciphertext ? await openManifest(store.ciphertext, vk) : {};
}

// A new login record, mirroring the web item shape.
function createLogin(rec) {
    const now = new Date().toISOString();
    const item = {
        id: crypto.randomUUID(),
        type: 'login',
        title: rec.title || rec.username || rec.url || 'Login',
        favorite: false, folder: null, tags: [], custom: [], icon: '',
        fields: { username: rec.username || '', password: rec.password || '', urls: (rec.url ? [rec.url] : []).filter(Boolean), totp: '', note: '' },
        created: now, updated: now, versions: [],
    };
    return mutateManifest("passwords", (m) => {
        item.folder = (m.secretFolders && m.secretFolders[0] && m.secretFolders[0].id) || null; // default to the first vault
        m.secrets.unshift(item);
        return { id: item.id };
    });
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

// Deserialize { __b64u } markers in a message payload back to Uint8Array.
// Shared by both passkey.create and passkey.get so the logic cannot diverge.
function des(v) {
    if (v && typeof v === 'object' && typeof v.__b64u === 'string') return pk.b64uDecode(v.__b64u);
    if (Array.isArray(v)) return v.map(des);
    if (v && typeof v === 'object') { const o = {}; for (const k of Object.keys(v)) o[k] = des(v[k]); return o; }
    return v;
}

// Extract passkey candidates for an rpId from the secrets list.
// Returns full candidate objects (including privateKey) for signing.
// Used by passkey.get, passkey.conditionalSign, and passkey.conditional.available.
function passkeyCandidates(secrets, rpId) {
    const candidates = [];
    for (const s of secrets) {
        if (s.type === 'passkey' && s.fields?.rpId === rpId) {
            candidates.push({
                credentialId: s.fields.credentialId,
                privateKey: s.fields.privateKey,
                userHandle: s.fields.userHandle || null,
                userName: s.fields.userName || s.fields.userDisplayName || '',
                label: s.title || rpId,
                sourceItemId: s.id,
            });
        } else if (s.type === 'login' && Array.isArray(s.fields?.passkeys)) {
            for (const pkEntry of s.fields.passkeys) {
                if (pkEntry.rpId === rpId) {
                    candidates.push({
                        credentialId: pkEntry.credentialId,
                        privateKey: pkEntry.privateKey,
                        userHandle: pkEntry.userHandle || null,
                        userName: pkEntry.userName || pkEntry.userDisplayName || '',
                        label: s.title || rpId,
                        sourceItemId: s.id,
                    });
                }
            }
        }
    }
    return candidates;
}

// Short-lived cache for passkey.conditional.available to avoid repeated decrypt per focus event.
let _pkAvailCache = null; // { origin, rpId, at, result }

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
        if (typeof passphrase !== 'string' || passphrase.length === 0 || passphrase.length > 4096) throw new Error('invalid passphrase');
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
    async search({ query }) { return { logins: await search(typeof query === 'string' ? query.slice(0, 200) : '') }; },
    async folders() { await ensureSecrets(); return { folders: FOLDERS }; },
    // Public 2fa.directory dataset: domains that support app 2FA. Cached locally
    // (public data, not sensitive) so we hint where a login could add a code.
    async tfa() { return { entries: await tfaEntries() }; },
    async createLogin({ login }) { return createLogin(login || {}); },
    async trashItem({ id }) { return mutateManifest("passwords", (m) => { const s = m.secrets.find((x) => x.id === id); if (s) s.trashed = new Date().toISOString(); }); },
    // All stored identity items, for the content-script identity-autofill picker.
    async identities() {
        const secrets = await ensureSecrets();
        return {
            identities: secrets
                .filter((s) => s.type === 'identity')
                .map(itemView)
                .sort(byTitle),
        };
    },

    async 'passkey.create'({ request, origin }, sender) {
        // Require message from a real content-script tab (not just any extension page).
        if (! sender?.tab?.id) return { ok: false, error: 'no tab' };

        // Require vault unlocked.
        const vkB64 = (await session.get('vk')).vk;
        if (! vkB64) return { ok: false, error: 'locked' };

        request = des(request);

        // rpId enforcement: must equal or be a registrable parent of the origin host.
        // origin was set by the content script to location.origin — trusted.
        // Uses the tested pure function rpIdAllowed() from passkey.js (mirrors hostsMatch).
        const pageHost = hostOf(origin);
        const rpId = (request.rp && request.rp.id) ? request.rp.id : pageHost;
        if (! pk.rpIdAllowed(pageHost, rpId)) return { ok: false, error: 'rpId mismatch' };

        // pubKeyCredParams must be a non-empty array that includes ES256 (alg -7).
        if (! Array.isArray(request.pubKeyCredParams) || request.pubKeyCredParams.length === 0
            || ! request.pubKeyCredParams.some((p) => p.alg === -7)) {
            return { ok: false, error: 'NotSupported' };
        }

        // excludeCredentials: reject if we already hold a matching passkey for this rpId.
        // Scan both standalone passkey items AND passkeys embedded in login items so
        // a previously registered credential is never duplicated regardless of how it
        // was stored. ensureSecrets() is called here so SECRETS is never stale/null.
        if (Array.isArray(request.excludeCredentials) && request.excludeCredentials.length > 0) {
            const stored = await ensureSecrets();
            const existingIds = new Set(passkeyCandidates(stored, rpId).map((c) => c.credentialId).filter(Boolean));
            for (const ex of request.excludeCredentials) {
                const exId = ex.id instanceof Uint8Array ? pk.b64uEncode(ex.id) : (ex.id ? String(ex.id) : '');
                if (existingIds.has(exId)) return { ok: false, error: { name: 'InvalidStateError', message: 'credential already registered' } };
            }
        }

        // Generate keypair and credential ID.
        const credentialId = pk.randomCredentialId();
        const { privateJwk, publicJwk } = await pk.generateEs256();

        const now = new Date().toISOString();
        const userHandle = request.user && request.user.id instanceof Uint8Array ? pk.b64uEncode(request.user.id) : '';
        const userName = (request.user && request.user.name) ? request.user.name : '';
        const userDisplayName = (request.user && request.user.displayName) ? request.user.displayName : '';
        const rpName = (request.rp && request.rp.name) ? request.rp.name : rpId;
        const credIdB64u = pk.b64uEncode(credentialId);

        // Build the passkey object to store (not sealed yet — we need the target first).
        const passkeyFields = {
            rpId,
            credentialId: credIdB64u,
            alg: -7,
            privateKey: JSON.stringify(privateJwk),
            publicKey: JSON.stringify(publicJwk),
            userHandle,
            userName,
            userDisplayName,
            signCount: 0,
            createdAt: now,
        };

        // Find login items whose URLs match this rpId so the user can attach the
        // new passkey to an existing login instead of creating a standalone entry.
        // Match is bidirectional (parent↔child on a dot boundary) — this is only a
        // suggestion list, not a security gate; the credential still binds to rpId.
        const allSecrets = await ensureSecrets();
        const logins = allSecrets
            .filter((s) => s.type === 'login' && ! s.shared)
            .filter((s) => (s.fields?.urls || []).some((u) => hostsMatch(hostOf(u), rpId) || hostsMatch(rpId, hostOf(u))))
            .map((s) => ({ id: s.id, title: s.title || '', username: s.fields?.username || '' }));

        // Ask the active tab's content script to show a save-target prompt.
        const [activeTab] = await new Promise((r) => chrome.tabs.query({ active: true, currentWindow: true }, r));
        const tabId = (activeTab && activeTab.id != null) ? activeTab.id : sender.tab.id;
        const saveRes = await new Promise((resolve) => {
            chrome.tabs.sendMessage(tabId, { type: 'passkey.savePrompt', rpId, userName, logins }, (r) => resolve(r || null));
        });

        // null, missing target, or empty-string target = user cancelled.
        // A valid target is the literal string 'new' (standalone) or a non-empty
        // login-id string (attach). Anything else — including undefined, null, or
        // {} — is treated as cancel so we never silently create without intent.
        if (! saveRes || (saveRes.target !== 'new' && typeof saveRes.target !== 'string') || saveRes.target === '') {
            return { ok: false, error: { name: 'NotAllowedError', message: 'cancelled' } };
        }

        if (saveRes.target !== 'new') {
            // Attach the passkey to an existing personal login item.
            // Verify the target exists in the personal manifest BEFORE building the
            // attestation — if it is missing (e.g. the user somehow selected a shared
            // login that slipped through), abort the entire ceremony so no orphaned
            // credential is ever handed to the RP.
            let attachFailed = false;
            await mutateManifest("passwords", (m) => {
                const loginItem = m.secrets.find((x) => x.id === saveRes.target);
                if (! loginItem) { attachFailed = true; return; }
                if (! Array.isArray(loginItem.fields.passkeys)) loginItem.fields.passkeys = [];
                loginItem.fields.passkeys.push(passkeyFields);
                loginItem.updated = now;
            });
            if (attachFailed) {
                return { ok: false, error: { name: 'NotAllowedError', message: 'attach target not found' } };
            }
        } else {
            // Create a standalone passkey item.
            await mutateManifest("passwords", (m) => {
                const item = {
                    id: crypto.randomUUID(),
                    type: 'passkey',
                    title: rpId,
                    favorite: false, folder: null, tags: [], custom: [], icon: '',
                    fields: { rpName, ...passkeyFields },
                    created: now, updated: now, versions: [],
                };
                m.secrets.unshift(item);
            });
        }

        // Build the attestation response.
        const cose = pk.coseFromPublicJwk(publicJwk);
        const authData = await pk.buildAuthData({
            rpId,
            flags: { up: true, uv: true, at: true },
            signCount: 0,
            attested: { aaguid: new Uint8Array(16), credentialId, cosePublicKey: cose },
        });
        const attObj = pk.attestationObjectNone(authData);
        const cdj = pk.clientDataJSON({ type: 'webauthn.create', challenge: request.challenge, origin });

        // Invalidate the secrets cache so the new passkey shows on next read.
        SECRETS = null;

        return {
            ok: true,
            result: {
                credentialId: pk.b64uEncode(credentialId),
                attestationObject: pk.b64uEncode(attObj),
                clientDataJSON: pk.b64uEncode(cdj),
                transports: ['internal', 'hybrid'],
            },
        };
    },
    async 'passkey.get'({ request, origin }, sender) {
        // Require message from a real content-script tab (not just any extension page).
        if (! sender?.tab?.id) return { ok: false, error: 'no tab' };

        // Require vault unlocked.
        const vkB64 = (await session.get('vk')).vk;
        if (! vkB64) return { ok: false, error: 'locked' };

        request = des(request);

        // rpId enforcement: must equal or be a registrable parent of the origin host.
        // origin was set by the content script to location.origin — trusted.
        // Uses the tested pure function rpIdAllowed() from passkey.js (mirrors hostsMatch).
        const pageHost = hostOf(origin);
        const rpId = request.rpId ? request.rpId : pageHost;
        if (! pk.rpIdAllowed(pageHost, rpId)) return { ok: false, error: 'rpId mismatch' };

        // Load secrets and find candidates matching this rpId — both standalone
        // passkey items and passkeys embedded in login items' fields.passkeys[].
        const secrets = await ensureSecrets();
        const candidates = passkeyCandidates(secrets, rpId);

        // If allowCredentials is specified, intersect to only those credential IDs.
        // Ignore entries whose type is not 'public-key' — browsers skip them too.
        let filtered = candidates;
        if (Array.isArray(request.allowCredentials) && request.allowCredentials.length > 0) {
            const allowedIds = new Set(
                request.allowCredentials
                    .filter((ac) => ! ac.type || ac.type === 'public-key')
                    .map((ac) => {
                        const id = ac.id;
                        return id instanceof Uint8Array ? pk.b64uEncode(id) : (id ? String(id) : '');
                    }).filter(Boolean)
            );
            filtered = candidates.filter((c) => allowedIds.has(c.credentialId || ''));
        }

        // 0 candidates → fall through to native.
        if (filtered.length === 0) return { ok: false, error: 'no-credential' };

        // Always show a picker (even for a single candidate) for explicit user confirmation.
        const pickerCandidates = filtered.map((c) => ({
            credentialId: c.credentialId,
            label: c.label,
            userName: c.userName,
        }));
        const pickRes = await new Promise((resolve) => {
            chrome.tabs.sendMessage(sender.tab.id, { type: 'passkey.pick', candidates: pickerCandidates, rpId }, (r) => resolve(r || null));
        });
        if (! pickRes || pickRes.cancel || ! pickRes.credentialId) return { ok: false, error: { name: 'NotAllowedError', message: 'cancelled' } };
        const chosen = filtered.find((c) => c.credentialId === pickRes.credentialId);
        if (! chosen) return { ok: false, error: { name: 'NotAllowedError', message: 'cancelled' } };

        // Sign the assertion.
        const priv = JSON.parse(chosen.privateKey);
        const authData = await pk.buildAuthData({ rpId, flags: { up: true, uv: true, at: false }, signCount: 0 });
        const cdj = pk.clientDataJSON({ type: 'webauthn.get', challenge: request.challenge, origin });
        const cdjHash = new Uint8Array(await crypto.subtle.digest('SHA-256', cdj));
        const len = authData.length + cdjHash.length;
        const message = new Uint8Array(len);
        message.set(authData, 0);
        message.set(cdjHash, authData.length);
        const signature = await pk.signEs256(priv, message);

        return {
            ok: true,
            result: {
                credentialId: chosen.credentialId,
                authenticatorData: pk.b64uEncode(authData),
                clientDataJSON: pk.b64uEncode(cdj),
                signature: pk.b64uEncode(signature),
                userHandle: chosen.userHandle || null,
            },
        };
    },

    // Lightweight availability check for conditional passkey mediation.
    // Returns { unlocked, count, candidates } so the content script can decide
    // whether to surface the inline passkey suggestion on field focus.
    // candidates is a metadata-only list (no private keys) for the picker display.
    // No sender.tab.id gate — the content script calls this proactively on every
    // focusin; we return quickly with unlocked:false if the vault is locked.
    // Uses a 3-second TTL cache keyed by origin+rpId to avoid decrypt on every focus event.
    async 'passkey.conditional.available'({ rpId, origin }, sender) {
        if (! origin || ! pk.rpIdAllowed(hostOf(origin), rpId)) return { ok: true, unlocked: false, count: 0, candidates: [] };
        const vkB64 = (await session.get('vk')).vk;
        if (! vkB64) return { ok: true, unlocked: false, count: 0, candidates: [] };
        if (_pkAvailCache && _pkAvailCache.origin === origin && _pkAvailCache.rpId === rpId && Date.now() - _pkAvailCache.at < 3000) {
            return _pkAvailCache.result;
        }
        try {
            const secrets = await ensureSecrets();
            const full = passkeyCandidates(secrets, rpId);
            const candidates = full.map((c) => ({ credentialId: c.credentialId, userName: c.userName, label: c.label }));
            const result = { ok: true, unlocked: true, count: candidates.length, candidates };
            _pkAvailCache = { origin, rpId, at: Date.now(), result };
            return result;
        } catch (e) {
            return { ok: true, unlocked: false, count: 0, candidates: [] };
        }
    },

    // Sign a conditional passkey assertion for a pre-chosen credential. Unlike
    // passkey.get this does NOT show a modal picker — the user already chose the
    // credential via the inline autofill suggestion in the content script.
    // chosenCredentialId is validated against the actual candidate list to prevent
    // a forged pick from signing an arbitrary stored credential.
    async 'passkey.conditionalSign'({ request, origin, chosenCredentialId }, sender) {
        if (! sender?.tab?.id) return { ok: false, error: 'no tab' };
        const vkB64 = (await session.get('vk')).vk;
        if (! vkB64) return { ok: false, error: 'locked' };

        request = des(request);

        const pageHost = hostOf(origin);
        const rpId = request.rpId ? request.rpId : pageHost;
        if (! pk.rpIdAllowed(pageHost, rpId)) return { ok: false, error: 'rpId mismatch' };

        const secrets = await ensureSecrets();
        const candidates = passkeyCandidates(secrets, rpId);

        // If allowCredentials is specified, intersect with those IDs first.
        let filtered = candidates;
        if (Array.isArray(request.allowCredentials) && request.allowCredentials.length > 0) {
            const allowedIds = new Set(
                request.allowCredentials
                    .filter((ac) => ! ac.type || ac.type === 'public-key')
                    .map((ac) => {
                        const id = ac.id;
                        return id instanceof Uint8Array ? pk.b64uEncode(id) : (id ? String(id) : '');
                    }).filter(Boolean)
            );
            filtered = candidates.filter((c) => allowedIds.has(c.credentialId || ''));
        }

        // Validate chosenCredentialId is actually in our candidate list.
        const chosen = filtered.find((c) => c.credentialId === chosenCredentialId);
        if (! chosen) return { ok: false, error: { name: 'NotAllowedError', message: 'credential not found' } };

        const priv = JSON.parse(chosen.privateKey);
        const authData = await pk.buildAuthData({ rpId, flags: { up: true, uv: true, at: false }, signCount: 0 });
        const cdj = pk.clientDataJSON({ type: 'webauthn.get', challenge: request.challenge, origin });
        const cdjHash = new Uint8Array(await crypto.subtle.digest('SHA-256', cdj));
        const len = authData.length + cdjHash.length;
        const message = new Uint8Array(len);
        message.set(authData, 0);
        message.set(cdjHash, authData.length);
        const signature = await pk.signEs256(priv, message);

        return {
            ok: true,
            result: {
                credentialId: chosen.credentialId,
                authenticatorData: pk.b64uEncode(authData),
                clientDataJSON: pk.b64uEncode(cdj),
                signature: pk.b64uEncode(signature),
                userHandle: chosen.userHandle || null,
            },
        };
    },

    async updateItem({ id, patch }) {
        return mutateManifest("passwords", (m) => {
            const s = m.secrets.find((x) => x.id === id);
            if (s) {
                s.versions = s.versions ?? [];
                s.versions.unshift({ at: s.updated || s.created || new Date().toISOString(), title: s.title, fields: JSON.parse(JSON.stringify(s.fields || {})), custom: JSON.parse(JSON.stringify(s.custom || [])) });
                if (s.versions.length > 100) s.versions.length = 100;
                s.fields = { ...(s.fields || {}), ...(patch || {}) };
                s.updated = new Date().toISOString();
            }
        });
    },
    // Remove a single passkey from a login by credentialId, operating on the
    // stored (unstripped) passkeys array so private keys are never lost.
    async removePasskey({ id, credentialId }) {
        return mutateManifest("passwords", (m) => {
            const s = m.secrets.find((x) => x.id === id);
            if (s && Array.isArray(s.fields?.passkeys)) {
                s.fields.passkeys = s.fields.passkeys.filter((p) => p.credentialId !== credentialId);
                s.updated = new Date().toISOString();
            }
        });
    },
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
    // ── Bookmarks ──────────────────────────────────────────────────────────────
    async 'bookmarks.list'() {
        const m = await readManifest("bookmarks");
        return listBookmarks(m);
    },
    async 'bookmarks.get'({ id }) {
        if (typeof id !== 'string' || id.length > 64) throw new Error('bad input');
        const m = await readManifest("bookmarks");
        return { bookmark: getBookmark(m, id) };
    },
    // Bookmarks are read-only in the extension — the ONLY write is a one-way bulk
    // import of the browser's own bookmarks (folders rebuilt from each item's path,
    // http(s)-only, exact duplicates skipped). No per-item create/edit/delete.
    async 'bookmarks.importBrowser'({ items }) {
        const list = Array.isArray(items) ? items.slice(0, 10000) : [];
        return mutateManifest("bookmarks", (m) => importBrowserBookmarks(m, list));
    },
    // ── Passwords/TOTP ─────────────────────────────────────────────────────────
    // Current TOTP code for a login id (secret stays in the worker).
    async totp({ id }) {
        const secrets = await ensureSecrets();
        const s = secrets.find((x) => x.id === id);
        if (! s || ! s.fields?.totp) return { code: null };
        return (await totpCode(s.fields.totp)) || { code: null };
    },
};

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    // Only accept messages from our own extension pages/content scripts. Web
    // pages can't reach a runtime listener without externally_connectable (we
    // don't set it), but this is cheap defence-in-depth.
    if (sender.id !== chrome.runtime.id) return false;
    const fn = handlers[msg?.type];
    if (! fn) return false;
    Promise.resolve(fn(msg, sender)).then((r) => sendResponse({ ok: true, ...r })).catch((e) => sendResponse({ ok: false, error: String(e?.message || e) }));
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
