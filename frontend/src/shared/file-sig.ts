// Content-based dedup signature for an uploaded receipt file — lets the server
// silently return the existing row instead of creating a byte-identical
// duplicate. Real case: a user dropped 27 receipts into the inbox, several hit
// the OCR rate limit and failed, so they re-dropped the same 27 files to retry
// — duplicating every file that HAD already succeeded the first time, since
// nothing checked for that. SHA-256 over the whole file via the Web Crypto API;
// receipts are capped well under the server's 25 MiB limit, so hashing the
// entire file (not just a slice) is cheap and avoids any partial-hash collision.
export async function fileSig(file: File): Promise<string> {
  const buf = await file.arrayBuffer();
  const digest = await crypto.subtle.digest('SHA-256', buf);
  const hex = Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
  return `${file.size}:${hex}`;
}
