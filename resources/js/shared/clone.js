// JSON-safe deep clone for sealed-store data.
//
// The per-module + sharded store manifests are JSON by contract — they are sealed
// as canonical JSON. `structuredClone` is the WRONG tool for them: once Alpine has
// wrapped a record in its reactive Proxy, `structuredClone` throws a DataCloneError
// ("[object Object] could not be cloned"), which silently aborts a debounced flush
// and loses the write (a created todo/note/contact never persists). JSON round-trip
// goes through the proxy get-traps, only serialises the enumerable data we actually
// seal, and never throws on reactive objects — so it is both correct and safe here.
//
// Semantics match the seal: `undefined` props drop, NaN/Infinity → null (canonical
// JSON handles these identically). Never use this for values holding Dates/Maps/etc.
// — the sealed stores hold none (dates are stored as strings).
export function jsonClone(v) {
    return v == null ? v : JSON.parse(JSON.stringify(v));
}
