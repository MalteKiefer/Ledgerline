// Node crypto round-trip test for the zero-knowledge Files vault (resources/js/vault.js).
// Proves: content encrypt/decrypt integrity across sizes (empty -> multi-chunk),
// metadata sealing, no plaintext leak in ciphertext, wrong-key rejection, and the
// full passphrase + recovery-code unlock lifecycle. Run: node tests/js/vault-crypto.mjs

// --- Minimal browser shims so vault.js loads + runs under Node ---
let vaultRow = null; // the "server" row captured from setup()
globalThis.document = { querySelector: () => null };
globalThis.sessionStorage = { _m: {}, getItem(k) { return this._m[k] ?? null; }, setItem(k, v) { this._m[k] = String(v); }, removeItem(k) { delete this._m[k]; } };
globalThis.fetch = async (url, opts = {}) => {
    const method = opts.method || 'GET';
    if (method === 'GET') {
        return { ok: true, status: vaultRow ? 200 : 200, json: async () => vaultRow ? { configured: true, has_recovery: true, ...vaultRow } : { configured: false } };
    }
    // POST (setup) / PUT (rotate): capture the ciphertext the client would persist.
    const body = JSON.parse(opts.body);
    vaultRow = { salt: body.salt, kdf_ops: body.kdf_ops, kdf_mem: body.kdf_mem, wrapped_vault_key: body.wrapped_vault_key, wrap_nonce: body.wrap_nonce, wrapped_vault_key_recovery: body.wrapped_vault_key_recovery ?? vaultRow?.wrapped_vault_key_recovery, recovery_nonce: body.recovery_nonce ?? vaultRow?.recovery_nonce };
    return { ok: true, status: 201, json: async () => ({ configured: true }) };
};

const { Vault } = await import('../../resources/js/vault.js');

let passed = 0, failed = 0;
const ok = (cond, msg) => { if (cond) { passed++; } else { failed++; console.error('  FAIL:', msg); } };
const eqBytes = (a, b) => a.length === b.length && a.every((x, i) => x === b[i]);

async function blobBytes(blob) { return new Uint8Array(await blob.arrayBuffer()); }

// --- Setup the vault (sets Vault.vk; fetch shim captures the row) ---
const PASS = 'correct horse battery staple';
const recoveryCode = await Vault.setup(PASS);
ok(typeof recoveryCode === 'string' && recoveryCode.replace(/\s/g, '').length === 64, 'setup returns a 32-byte hex recovery code');
ok(Vault.unlocked(), 'vault is unlocked after setup');

// --- Content round-trip across sizes, incl. the 4 MiB chunk boundary ---
const CHUNK = 4 * 1024 * 1024;
const sizes = [0, 1, 1000, CHUNK - 1, CHUNK, CHUNK + 1, 3 * CHUNK + 12345];
for (const n of sizes) {
    const data = new Uint8Array(n);
    for (let i = 0; i < n; i++) data[i] = (i * 131 + 7) & 0xff; // deterministic pseudo-random
    const enc = Vault.encryptContent(data, { name: `f${n}.bin`, mime: 'application/octet-stream' });
    const cipher = await blobBytes(enc.blob);
    ok(cipher.length > 0, `size ${n}: ciphertext is non-empty`);
    const back = Vault.decryptFile(cipher, enc.encFileKey);
    ok(eqBytes(back, data), `size ${n}: decrypt round-trips exactly (${n} bytes, ${Math.ceil(Math.max(1, n) / CHUNK)} chunk(s))`);
    // Sealed metadata carries the real name/mime/size; ciphertext must not.
    const meta = Vault.decryptFileMeta(enc.encMeta);
    ok(meta.name === `f${n}.bin` && meta.size === n, `size ${n}: sealed metadata (name+size) round-trips`);
}

// --- Slice-based encryptFile(File) round-trips (streaming read, multi-chunk) ---
for (const n of [0, 100, CHUNK + 500, 2 * CHUNK + 99]) {
    const data = new Uint8Array(n);
    for (let i = 0; i < n; i++) data[i] = (i * 97 + 3) & 0xff;
    const file = new File([data], 'x.bin', { type: 'application/octet-stream' });
    const enc = await Vault.encryptFile(file);
    const back = Vault.decryptFile(await blobBytes(enc.blob), enc.encFileKey);
    ok(eqBytes(back, data), `encryptFile(File) round-trips at ${n} bytes (slice read)`);
    ok(Vault.decryptFileMeta(enc.encMeta).size === n, `encryptFile(File) seals the real size at ${n} bytes`);
}

// --- Streaming encryptor -> streaming decryptor round-trip (constant-memory path) ---
for (const n of [0, 10, CHUNK, 2 * CHUNK + 777]) {
    const data = new Uint8Array(n);
    for (let i = 0; i < n; i++) data[i] = (i * 61 + 17) & 0xff;
    const enc = Vault.newContentEncryptor();
    // Produce the exact byte stream the streaming upload would send.
    const frames = [enc.header];
    let total = enc.header.length;
    for (let off = 0; off < n || off === 0;) {
        const end = Math.min(off + CHUNK, n);
        const last = end >= n;
        const f = enc.encryptChunk(data.subarray(off, end), last);
        frames.push(f); total += f.length;
        off = end; if (last) break;
    }
    ok(total === Vault.ciphertextSize(n), `ciphertextSize(${n}) predicts the exact stream length`);
    // Concatenate + decrypt via the streaming decryptor (header then framed msgs).
    const wire = concatAll(frames);
    const dec = Vault.beginDecrypt(enc.sealKey());
    dec.start(wire.subarray(0, dec.headerLen));
    let off = dec.headerLen; const out = [];
    for (;;) {
        const len = wire[off] | (wire[off + 1] << 8) | (wire[off + 2] << 16) | (wire[off + 3] << 24);
        off += 4;
        const { message, final } = dec.pull(wire.subarray(off, off + len));
        off += len; out.push(message);
        if (final) break;
    }
    ok(eqBytes(concatAll(out), data), `streaming encrypt->decrypt round-trips at ${n} bytes`);
}
function concatAll(arrs) { const size = arrs.reduce((a, b) => a + b.length, 0); const o = new Uint8Array(size); let p = 0; for (const a of arrs) { o.set(a, p); p += a.length; } return o; }

// --- No plaintext leak: a recognizable marker never appears in the ciphertext ---
const marker = new TextEncoder().encode('SECRET_MARKER_1234567890');
const secret = new Uint8Array(200000);
secret.set(marker, 0);
const encS = Vault.encryptContent(secret, { name: 'top-secret-filename.txt', mime: 'text/plain' });
const cipherS = await blobBytes(encS.blob);
const hay = Buffer.from(cipherS).toString('latin1');
ok(!hay.includes('SECRET_MARKER'), 'plaintext content does not appear in the ciphertext');
ok(!encS.encMeta.includes('top-secret-filename'), 'plaintext filename does not appear in the sealed metadata');

// --- Tags are sealed INSIDE the metadata, never a plaintext column ---
const metaWithTags = Vault.encryptMeta({ name: 'doc.txt', mime: 'text/plain', size: 5, tags: ['secret-tag', 'private'] });
const tagsSealed = JSON.stringify({ c: metaWithTags.cipher, n: metaWithTags.nonce });
ok(!tagsSealed.includes('secret-tag'), 'plaintext tags do not appear in the sealed metadata');
ok(JSON.stringify(Vault.decryptFileMeta(tagsSealed).tags) === JSON.stringify(['secret-tag', 'private']), 'tags round-trip through the sealed metadata');

// --- Metadata / folder-name sealing round-trip ---
const sealed = Vault.sealName('Vertrauliche Ordner/Name äöü');
ok(!sealed.includes('Vertrauliche'), 'sealed folder name is opaque');
ok(Vault.decryptFileMeta(sealed).name === 'Vertrauliche Ordner/Name äöü', 'folder name unseals exactly (incl. unicode)');

// --- Wrong key rejection: a different vault key must not decrypt ---
const encW = Vault.encryptContent(new Uint8Array([1, 2, 3, 4, 5]), { name: 'x', mime: 'x' });
const cipherW = await blobBytes(encW.blob);
const goodVk = Vault.vk;
try {
    Vault.vk = new Uint8Array(goodVk.length); // all-zero wrong key
    let threw = false;
    try { Vault.decryptFile(cipherW, encW.encFileKey); } catch (e) { threw = true; }
    ok(threw, 'decrypting with the wrong vault key throws (no silent garbage)');
} finally { Vault.vk = goodVk; }

// --- Lifecycle: lock, then unlock with the passphrase reconstructs the SAME key ---
const vkHex = Buffer.from(Vault.vk).toString('hex');
Vault.lock();
ok(!Vault.unlocked(), 'lock() clears the key');
await Vault.unlock(PASS);
ok(Buffer.from(Vault.vk).toString('hex') === vkHex, 'unlock(passphrase) recovers the exact same vault key');

// --- Wrong passphrase must fail ---
Vault.lock();
let wrongThrew = false;
try { await Vault.unlock('wrong passphrase'); } catch (e) { wrongThrew = true; }
ok(wrongThrew && !Vault.unlocked(), 'a wrong passphrase fails and leaves the vault locked');

// --- Recovery code path reconstructs the SAME key ---
await Vault.recover(recoveryCode);
ok(Vault.unlocked() && Buffer.from(Vault.vk).toString('hex') === vkHex, 'recover(code) recovers the exact same vault key');

// --- Files encrypted before a passphrase change stay decryptable after it ---
const encBefore = Vault.encryptContent(new Uint8Array([9, 8, 7, 6]), { name: 'keep', mime: 'x' });
await Vault.changePassphrase(PASS, 'a brand new passphrase!!');
const cipherBefore = await blobBytes(encBefore.blob);
ok(eqBytes(Vault.decryptFile(cipherBefore, encBefore.encFileKey), new Uint8Array([9, 8, 7, 6])), 'files stay decryptable after a passphrase change (VK unchanged)');
Vault.lock();
await Vault.unlock('a brand new passphrase!!');
ok(Vault.unlocked(), 'unlock works with the new passphrase after rotation');

// --- Recovery re-wrap: setPassphrase mints a FRESH recovery code; the old one dies ---
const newRecovery = await Vault.setPassphrase('third passphrase here');
ok(typeof newRecovery === 'string' && newRecovery.replace(/\s/g, '').length === 64, 'setPassphrase returns a fresh 32-byte recovery code');
ok(newRecovery.replace(/\s/g, '') !== recoveryCode.replace(/\s/g, ''), 'the new recovery code differs from the old one');
Vault.lock();
let oldRecFailed = false;
try { await Vault.recover(recoveryCode); } catch (e) { oldRecFailed = true; }
ok(oldRecFailed && ! Vault.unlocked(), 'the OLD recovery code no longer opens the vault after a re-wrap');
await Vault.recover(newRecovery);
ok(Vault.unlocked() && Buffer.from(Vault.vk).toString('hex') === vkHex, 'the NEW recovery code opens the vault (same VK, files intact)');

console.log(`\nvault-crypto: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
