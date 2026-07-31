// Padmé blob padding. Pads a ciphertext blob up to the next Padmé bucket (leaks
// O(log log n) bits, ≤~12% overhead) so the stored/on-ledger size can't
// fingerprint the exact plaintext length. Shared by the gallery and files blob
// paths. The random pad sits AFTER the self-delimiting secretstream frames, so
// it is never parsed and decryption is unaffected — no download-side stripping.
export function padmeSize(n) {
    if (n < 2) return n;
    const e = Math.floor(Math.log2(n));
    const s = Math.floor(Math.log2(e)) + 1;
    // Float arithmetic (matches vault.js _padme): a 32-bit bitwise mask would
    // overflow and silently disable padding for blobs >= 2 GiB.
    const step = Math.pow(2, e - s);
    return Math.ceil(n / step) * step;
}

export async function padBlob(blob) {
    let pad = padmeSize(blob.size) - blob.size;
    if (pad <= 0) return blob;
    const parts = [blob];
    while (pad > 0) {
        const chunk = new Uint8Array(Math.min(pad, 65536));
        crypto.getRandomValues(chunk);
        parts.push(chunk);
        pad -= chunk.length;
    }
    return new Blob(parts, { type: 'application/octet-stream' });
}
