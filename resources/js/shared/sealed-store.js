// Shared base for sealed ZK manifest stores (LLStore / LLGalleryStore).
// Factors the two bits that are truly identical: newId() and the debounced
// touch() scheduling. The 409/429 retry and sharding logic are deliberately
// kept in each store — they diverge enough that merging risks behaviour changes.

// A random 128-bit hex client-side id. Used for every new item so the server
// never assigns ids and the client never needs to round-trip for them.
export function newId() {
    const b = new Uint8Array(16);
    crypto.getRandomValues(b);
    return [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
}

// Returns a `touch()` function that schedules a debounced save via the provided
// `flush` callback. Each call clears the previous timer.
export function makeTouchFn(getTimer, setTimer, flush, delayMs = 800) {
    return function touch() {
        clearTimeout(getTimer());
        setTimer(setTimeout(flush, delayMs));
    };
}
