/**
 * Dedup signature (§6.5 / §4.1) — the cross-client contract used to detect that
 * two uploads are the same file without buffering the whole thing.
 *
 * Format: `"{size}:{sha256hex(head1MiB ‖ tail1MiB)}"`
 *   - size  = total byte length of the file.
 *   - head  = first  min(1 MiB, size) bytes.
 *   - tail  = last   1 MiB bytes, but ONLY when size > 1 MiB (otherwise empty,
 *             so head and tail don't overlap and re-hash the same region).
 *   - The two slices are concatenated (head first) and SHA-256'd; the digest is
 *     lower-case hex.
 *
 * Bounded memory (never buffers a whole video) and collisions for two genuinely
 * different files are astronomically unlikely.
 *
 * Extracted here as a PURE function over raw bytes so it is deterministic and
 * headless-testable; gallery.js wraps it for the streaming Blob/File path.
 */

// 1 MiB head/tail cap. This constant is part of the cross-client contract — all
// clients must slice at exactly this boundary or their signatures diverge.
export const SIG_CAP = 1024 * 1024;

function hex(bytes) {
    return [...bytes].map((x) => x.toString(16).padStart(2, '0')).join('');
}

/**
 * Compute the dedup signature over a full in-memory byte buffer.
 *
 * @param {Uint8Array} bytes  the complete file bytes.
 * @returns {Promise<string>} `"{size}:{sha256hex(head‖tail)}"`.
 */
export async function fileSig(bytes) {
    const size = bytes.length;
    const head = bytes.subarray(0, Math.min(SIG_CAP, size));
    const tail = size > SIG_CAP ? bytes.subarray(size - SIG_CAP) : new Uint8Array(0);
    const buf = new Uint8Array(head.length + tail.length);
    buf.set(head, 0);
    buf.set(tail, head.length);
    const dig = await crypto.subtle.digest('SHA-256', buf);
    return `${size}:${hex(new Uint8Array(dig))}`;
}

/**
 * Streaming variant for a Blob/File: reads only the head and tail slices so a
 * multi-GB video never lands in memory. Byte-identical output to fileSig() over
 * the same content.
 *
 * @param {Blob} file
 * @returns {Promise<string>} the signature, or '' on read error.
 */
export async function fileSigFromBlob(file) {
    try {
        const head = new Uint8Array(await file.slice(0, Math.min(SIG_CAP, file.size)).arrayBuffer());
        const tail = file.size > SIG_CAP
            ? new Uint8Array(await file.slice(file.size - SIG_CAP).arrayBuffer())
            : new Uint8Array(0);
        const buf = new Uint8Array(head.length + tail.length);
        buf.set(head, 0);
        buf.set(tail, head.length);
        const dig = await crypto.subtle.digest('SHA-256', buf);
        return `${file.size}:${hex(new Uint8Array(dig))}`;
    } catch {
        return '';
    }
}
