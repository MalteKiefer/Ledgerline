// Test-only helper (shelled from MailSealerTest): generates a recipient
// identity keypair exactly as a browser client would hold it, and prints it as
// JSON on stdout. Lives inside the repo tree (not /tmp) so Node's node_modules
// resolution walks up to the repo root and finds libsodium-wrappers-sumo +
// @noble/* without any extra setup.
import sodium from 'libsodium-wrappers-sumo';
import { mlkemKeypair } from '../../../../resources/js/shared/pq-kem.js';

await sodium.ready;
const x = sodium.crypto_box_keypair();
const { ek, seed } = await mlkemKeypair();
const b64 = (b) => sodium.to_base64(b, sodium.base64_variants.ORIGINAL);

process.stdout.write(JSON.stringify({
    pub: b64(x.publicKey),
    priv: b64(x.privateKey),
    ek,
    seed: b64(seed),
}));
