// Test-only helper (shelled from MailSealerTest): decrypts a sealed_key+blob
// pair produced by MailSealer with the REAL client crypto (hybridUnwrap +
// ShareCrypto.decrypt from vault.js) — the same modules resources/js/mail-
// sealer/__tests__/seal-interop.test.js uses to prove browser interop. Proves
// end-to-end that what PHP's MailSealer wrapper hands back is byte-exact
// decryptable, not just that the Node CLI it shells produces well-formed
// output in isolation.
//
// Usage: node unwrap.mjs <priv_b64> <seed_b64> <sealed_key_json_path> <blob_path>
// Writes the recovered plaintext to stdout.
import { readFileSync } from 'node:fs';
import sodium from 'libsodium-wrappers-sumo';
import { hybridUnwrap } from '../../../../resources/js/shared/pq-kem.js';
import { ShareCrypto } from '../../../../resources/js/vault.js';

await sodium.ready;

const [, , privB64, seedB64, sealedKeyPath, blobPath] = process.argv;

const envelope = JSON.parse(readFileSync(sealedKeyPath, 'utf8'));
const blob = new Uint8Array(readFileSync(blobPath));
const seed = sodium.from_base64(seedB64, sodium.base64_variants.ORIGINAL);

const key = await hybridUnwrap(envelope, privB64, seed, '');
const plaintext = await ShareCrypto.decrypt(blob, key);

process.stdout.write(Buffer.from(plaintext));
