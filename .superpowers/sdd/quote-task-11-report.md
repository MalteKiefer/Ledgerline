# Quote Task 11 Report

## Status

Implemented the isolated frontend Quote client layer below `frontend/src/modules/finance` without mounting or replacing any legacy route. The module now provides exact wire models, the complete additive Quote API surface, a paginated Pinia store, and URL-owned list filters.

The implementation keeps request decimals and every exact money/scaled integer wire value as decimal strings. It does not use `parseFloat`, JavaScript number conversion, or client-authoritative total calculations. Quote UUIDs remain strings; identifiers, versions, and pagination counters retain their separately defined wire types.

The store separates list, detail, and action loading/error state. List and detail requests use independent cancellation and stale-response guards; page responses replace prior page data, selected resources are upserted, and typed version conflicts replace stale state from the server's owner-scoped `current` resource while preserving its ETag.

Idempotency keys are generated with `crypto.randomUUID()` at the store's user-action boundary. A failed action retains its key for retry; success or explicit final cancellation releases it. Send distinguishes the initial `202` dispatch from an exact `200` replay without duplicating server-side request-hash logic.

URL filters round-trip `q`, `status`, `effective_status`, `sort`, `direction`, and `page`, preserve unrelated query parameters, normalize invalid values, reset pagination when filters change, and retain filters during explicit pagination.

## Review round 1

- Money minor units, scaled quantities, unit-price minor units, tax basis points, discounts, tax breakdowns, and optional control totals are decimal integer strings throughout the frontend contract. API regressions use values above `2^53` to prove they survive JSON request and response boundaries exactly.
- Revision-history requests now have a dedicated `AbortController`, monotonic request sequence, and `revisionsQuoteId`. A late response for Quote A cannot replace Quote B's selected history.
- List responses still replace the requested page and metadata, but each returned row is merged by optimistic version against newer detail/list cache entries. A late stale list can no longer downgrade a newer detail resource.
- Idempotency reservations now pair the generated UUID with a deterministic canonical payload signature. Object keys are recursively sorted, array order is retained, and undefined object members follow JSON omission semantics. Same-payload retries reuse the key; changed payloads allocate a new key; success and explicit cancellation release it.

## TDD evidence

- RED: the shared API client discarded the conflict `current` resource and ETag and could not expose a response status for the `202`/`200` Send distinction.
- RED: Quote API/model tests failed because the isolated Quote model and API modules did not exist.
- RED: store/filter tests failed because Quote state, request cancellation, retry-key retention, and URL serialization did not exist.
- RED: the conflict regression exposed that the selected resource ETag was not retained in the store.
- RED (review): TypeScript rejected decimal integer strings above `2^53` because exact business integers were modeled as JavaScript numbers.
- RED (review): a superseded revision request had no abort signal and Quote A could overwrite Quote B's revision history.
- RED (review): a late list response replaced a version-4 detail row with a stale version-3 row.
- RED (review): changing a failed Publish payload retained the earlier payload's idempotency key.
- GREEN: all focused tests and quality gates listed below pass.

## Verification

- `yarn test:js src/modules/finance src/api/__tests__/client.test.ts`: 4 files passed, 17 tests passed.
- `yarn typecheck`: passed with no TypeScript errors.
- `yarn lint`: passed with no ESLint errors.
- `git diff --check`: passed before commit.

## Scope and integration

Task 11 changes are limited to the listed frontend Quote models, API, store, URL-filter composable, their focused tests, the shared API-client extension required for typed conflicts/cancellation/status/ETag, and this report. No backend, provider, legacy-route mounting, legacy cutover, push, tag, or deployment is included.

Concurrent backend Project/Invoice work visible in the shared worktree is intentionally excluded from the explicit-path commit. The feature branch and worktree remain in place for the later Quote UI and cutover tasks.

## Concerns

The frontend now expects exact Quote money/scaled integer strings, but the current committed Quote schemas in `openapi.yaml` still declare those fields as JSON `integer/int64`. Backend resources and OpenAPI must be updated together before end-to-end integration; that change is intentionally outside this frontend-only review commit and has been reported for the separate Task 10 contract fix.

Task 12 still owns visible Quote pages and route export; later migration/cutover work owns mounting that route and replacing the legacy Quote UI.
