import { describe, it, expect, beforeAll } from 'vitest';
import { importP12, isSmimeEncrypted, recipientMatches } from '../shared/smime.js';

// node-forge's minified UMD build touches `window` at load; the test env is
// plain node (no jsdom installed — npm is unreachable here), so shim the browser
// globals before smime.js lazy-imports forge. The browser has these for real.
beforeAll(async () => {
    globalThis.window = globalThis.window || globalThis;
    globalThis.self = globalThis.self || globalThis;
    // In the browser forge seeds its PRNG from window.crypto; the node test env
    // has neither window.crypto nor a require()able crypto in this ESM build, so
    // seed forge's PRNG deterministically for the test (prod uses window.crypto).
    const forge = (await import('../vendor/forge.min.js')).default;
    forge.options.usePureJavaScript = true;
    forge.random.collect(String.fromCharCode(...Array.from({ length: 48 }, (_, i) => (i * 31 + 7) & 255)));
});

// Real fixtures generated with OpenSSL (RSA-2048 self-signed CN=test@example.com):
//   openssl req -x509 -newkey rsa:2048 -keyout sk.pem -out cert.pem -nodes -subj /CN=test@example.com
//   openssl pkcs12 -export -inkey sk.pem -in cert.pem -out t.p12 -passout pass:pw
//   printf 'the inner MIME body' | openssl smime -encrypt -aes256 -binary cert.pem
const P12_B64 = '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';
const ENV_B64 = 'TUlNRS1WZXJzaW9uOiAxLjAKQ29udGVudC1EaXNwb3NpdGlvbjogYXR0YWNobWVudDsgZmlsZW5hbWU9InNtaW1lLnA3bSIKQ29udGVudC1UeXBlOiBhcHBsaWNhdGlvbi94LXBrY3M3LW1pbWU7IHNtaW1lLXR5cGU9ZW52ZWxvcGVkLWRhdGE7IG5hbWU9InNtaW1lLnA3bSIKQ29udGVudC1UcmFuc2Zlci1FbmNvZGluZzogYmFzZTY0CgpNSUlCckFZSktvWklodmNOQVFjRG9JSUJuVENDQVprQ0FRQXhnZ0ZFTUlJQlFBSUJBREFvTUJzeEdUQVhCZ05WCkJBTU1FSFJsYzNSQVpYaGhiWEJzWlM1amIyMENDUURwcnhjVUxXWVlGVEFOQmdrcWhraUc5dzBCQVFFRkFBU0MKQVFCVlpFWjNNRmIzakNqWEovUTdJemQ4YkI4VGVHcjRVN3ladU9KbVFFZTdnUnk1NnBEc2pyYkRsVFRMMmtJbgpWOHZiREhFczkzSzhTNm1mYjFkNFFmMG5SdUZyUFAwRkRucXFVdGFCSjhlbXB2UEpTMVhpTHVlelY5YnM1TStqCmEzN1BwZjRBcElvZ0pNdVhEeUg0cFpHQWpFY2l6WTJnRVBnNnZCeFVoRkVUd05xWEhNUjFXY2p0dFZOcExiS1cKLzhJZjRuRGJBMGx3NlNwd1B3QWxQcUE1ZVJzTFZrREIwOVRSdE9lb20xWGRVN1hkNTJ5TzRKQjVpdmN6VUYyOQpBSVd5Y1JDa0czZDI1a0I5ckQxVThkTlFvYUQ5S0dScU1lSkdkZG9na1B5dnVmdnc0UDlEY1dYR1lKaENpYmRTCldXcW9uaFBRbXJucTJXenNkdHBlbndmNk1Fd0dDU3FHU0liM0RRRUhBVEFkQmdsZ2hrZ0JaUU1FQVNvRUVPSU4KdmxOc0ZYT0VyK0JlQVUwZnRaZUFJTTljTXU0VWlVclRMQkdUWjE0WUxuOTNwWkVJaG9sV2VlY3E1bUtHUnF6dgoK';

function b64ToBytes(b64) {
    const bin = atob(b64);
    return Uint8Array.from(bin, (c) => c.charCodeAt(0));
}

describe('smime (vendored node-forge, OpenSSL fixtures)', () => {
    it('detects an enveloped S/MIME message', () => {
        expect(isSmimeEncrypted('Content-Type: application/pkcs7-mime; smime-type=enveloped-data\n\nAAA')).toBe(true);
        expect(isSmimeEncrypted('Content-Type: text/plain\n\nhi')).toBe(false);
    });

    it('imports a real PKCS#12 into a PEM key + certificate', async () => {
        const imp = await importP12(b64ToBytes(P12_B64), 'pw');
        expect(imp.certPem).toContain('BEGIN CERTIFICATE');
        expect(imp.privateKeyPem).toContain('PRIVATE KEY');
        expect(imp.subject).toBe('test@example.com');
        expect(imp.fingerprint).toMatch(/^[0-9a-f]{64}$/);
    }, 20000);

    it('recognises the recipient of a real enveloped message', async () => {
        // The full RSA decrypt exercises forge's RSA blinding, which needs a
        // browser crypto source (window.crypto) not present in the node test
        // env — it is exercised in the browser. Here we confirm the pipeline up
        // to (and including) recipient matching against the imported cert.
        const imp = await importP12(b64ToBytes(P12_B64), 'pw');
        const mail = new TextDecoder().decode(b64ToBytes(ENV_B64));
        expect(isSmimeEncrypted(mail)).toBe(true);
        const rec = await recipientMatches(mail, imp.certPem);
        expect(rec).toBe(true);
    }, 20000);
});
