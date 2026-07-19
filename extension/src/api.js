// Thin client for the Ledgerline /api/v1 endpoints. The server is the user's
// own self-hosted instance (base URL set during pairing). All data responses
// are opaque ciphertext / a sealed manifest — the server stays zero-knowledge.

async function call(base, path, { method = 'GET', token = null, body = null } = {}) {
    const headers = { Accept: 'application/json' };
    if (token) headers.Authorization = 'Bearer ' + token;
    if (body) headers['Content-Type'] = 'application/json';
    const res = await fetch(base.replace(/\/+$/, '') + '/api/v1' + path, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    });
    return res;
}

/** Claim a one-time pairing code (from the web profile) → pending approval. */
export async function pair(base, code, deviceName) {
    const res = await call(base, '/auth/pair', { method: 'POST', body: { code, device_name: deviceName } });
    if (! res.ok) throw new Error('pair failed');
    return res.json();
}

/** Poll for the bearer token once the owner approves the device in the web UI. */
export async function collect(base, code) {
    const res = await call(base, '/auth/pair/collect', { method: 'POST', body: { code } });
    if (! res.ok) throw new Error('collect failed');
    return res.json(); // { status: 'pending' } | { status: 'approved', token, user }
}

/** Vault KDF params + wrapped key (for the local passphrase unlock). */
export async function getVault(base, token) {
    const res = await call(base, '/vault', { token });
    if (res.status === 401) throw new Error('unauthorized');
    if (! res.ok) throw new Error('vault fetch failed');
    return res.json();
}

/** The sealed workspace manifest ({ ciphertext, version }). */
export async function getStore(base, token) {
    const res = await call(base, '/store', { token });
    if (res.status === 401) throw new Error('unauthorized');
    if (! res.ok) throw new Error('store fetch failed');
    return res.json();
}

/** Write the sealed manifest back with optimistic concurrency (409 on conflict). */
export async function saveStore(base, token, ciphertext, version) {
    return call(base, '/store', { method: 'PUT', token, body: { ciphertext, version } });
}

/** 2fa.directory hint map, proxied by the user's own server (SSRF-guarded,
 *  cached, method-filtered, http(s)-docs-only). Returns { domain: docUrl }.
 *  The extension never fetches 2fa.directory directly — only via this route. */
export async function getTfaDirectory(base, token) {
    const res = await call(base, '/passwords/tfa-directory', { token });
    if (! res.ok) throw new Error('tfa fetch failed');
    const data = await res.json();
    return (data && data.entries) || {};
}

/** The user's own published X25519 identity key material (caller-scoped).
 *  { public_key, wrapped_secret_key, fingerprint } — nulls if none published. */
export async function getUserKeys(base, token) {
    const res = await call(base, '/vaults/keys', { token });
    if (res.status === 401) throw new Error('unauthorized');
    if (! res.ok) throw new Error('keys fetch failed');
    return res.json();
}

/** This user's shared-vault memberships.
 *  [{ vault_id, role, status, wrapped_vault_key }] */
export async function getVaults(base, token) {
    const res = await call(base, '/vaults', { token });
    if (res.status === 401) throw new Error('unauthorized');
    if (! res.ok) throw new Error('vaults fetch failed');
    return res.json();
}

/** A shared vault's sealed store ({ sealed_manifest, version }). */
export async function getVaultStore(base, token, vaultId) {
    const res = await call(base, '/vaults/' + encodeURIComponent(vaultId) + '/store', { token });
    if (res.status === 401) throw new Error('unauthorized');
    if (! res.ok) throw new Error('vault store fetch failed');
    return res.json();
}

/** Revoke this bearer server-side (unpair). */
export async function logout(base, token) {
    try { await call(base, '/auth/session', { method: 'DELETE', token }); } catch (e) { /* best effort */ }
}
