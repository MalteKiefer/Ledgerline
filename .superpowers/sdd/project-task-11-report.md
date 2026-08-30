# Project Plan Task 11 — frontend client-state implementation report

Date: 2026-08-29

## Outcome

Implemented the isolated Project v2 frontend foundation: exact wire models, a typed API boundary, Pinia list/detail/action state, URL-owned project filters, and an independently loaded project-detail panel coordinator.

The implementation consumes only `/api/v1/finance-v2` Task 10 endpoints. It adds no pages, frontend routes, legacy cutover, backend code, or service-provider bindings.

## Contract decisions

- UUIDs, document source references, cursors, and target references remain opaque strings.
- Every `*_minor` and `*_scaled` response field remains an exact decimal string; the client never converts those fields through JavaScript `Number`.
- Database identifiers, versions, sort positions, counts, and offset-pagination metadata remain integers.
- Project reads expose response ETags. Typed version conflicts adopt the server's current Project and ETag into both current and list state.
- Stable Project domain errors are allowlisted. Arbitrary response strings, raw exceptions, and secret-bearing payload fields are not promoted to frontend error codes.
- `Idempotency-Key` is sent exactly on the three Task 10 operations that require it: invoice-draft creation and document attach/detach.
- Idempotency keys are generated at the user-action boundary. They are keyed by action and Project, bound to a canonical payload signature, retained across failed identical retries, replaced when the payload changes or the user cancels, and released after success.

## State and cancellation

- Project list loading, Project detail loading, and mutation state use independent loading/error channels.
- Superseded list/detail requests are aborted and guarded by monotonically increasing sequences so a late response cannot replace newer state.
- The detail coordinator owns separate state, query, controller, loading, and error channels for Project, totals, work, time, ledger, documents, notes, and activity.
- Changing Project aborts and resets every old panel. A targeted refresh preserves every unrelated panel's data, error, page, filter, cursor, and continuation state.
- Offset pages remain local to work, time, ledger, documents, and notes. Activity uses its own opaque cursor and `next_cursor` without decoding or rewriting either value.
- Mutations refresh only their affected read models (for example time + totals + activity), never the complete finance snapshot.

## URL filters

The filter composable parses, normalizes, serializes, and round-trips `q`, `status`, `kind`, `parent_id`, `partner_reference`, `archived`, both start-date bounds, both due-date bounds, `sort`, `direction`, and `page`. Business-filter changes reset `page` to 1; explicit pagination preserves the remaining filters and unrelated route query keys.

## TDD evidence

The implementation was driven through four red/green slices:

1. Exact Task 10 wire types, complete Project API path/method matrix, query encoding, ETags, stable errors, and header placement.
2. Project store list/detail/action isolation, abort/stale suppression, current-resource conflict adoption, and canonical idempotency-key reuse.
3. Full project-filter URL round-trip, invalid-value normalization, and page-reset behavior.
4. Independent detail panels, Project-switch cancellation, local page/cursor state, isolated failures, and mutation-specific refresh.

## Verification

- Focused Project Task 11 Vitest: **12 tests passed across 4 files**.
- Full Finance frontend Vitest: **33 tests passed across 10 files**.
- Strict frontend typecheck (`vue-tsc --noEmit`): **passed**.
- Full frontend lint (`eslint src`): **passed**.
- An earlier shared-worktree Finance run observed transient failures only in concurrently authored, uncommitted Quote page/test files. This task did not modify them; after their owner stabilized that independent slice, the final broad run above was fully green.

## Scope hygiene

- Only the eleven Task 11 Project frontend model/API/store/composable/test paths and this report are included.
- No page, component, frontend route, shared API client, OpenAPI, backend, provider, Invoice, Payment, or Quote file is included.
- No push, tag, deployment, or integration action was performed.
