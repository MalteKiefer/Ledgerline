# Project Plan Task 10 — HTTP/API implementation report

Date: 2026-08-29

## Outcome

Implemented the additive `/api/v1/finance-v2` project HTTP boundary from Task 10: 32 authenticated operations across 20 unique paths for projects, work items, time entries, invoice-draft creation, totals, ledger entries, document associations/source search, project notes/activity, and document-series notes.

Controllers remain thin application boundaries. They validate input, construct the existing Task 5–9 DTOs, invoke the corresponding command/query, and serialize through dedicated Resources. No persistence logic or provider binding was added here.

## Contract and security decisions

- Every project/series operation starts from the authenticated owner boundary; missing and foreign resources remain indistinguishable 404 responses.
- All routes retain the finance module, Sanctum device ability, two-factor, request logging, and `120/min` throttle middleware stack.
- Project, work-item, time-entry, and ledger route identifiers are UUID-constrained; document link identifiers are numeric.
- Versions, IDs, counters, page metadata, and revision identifiers are JSON integers.
- Every `*_minor` and `*_scaled` value is an exact JSON decimal string. Inputs reject floating-point values. OpenAPI declares these values as `type: string` with integer-string patterns.
- Optimistic conflicts and stable domain failures are mapped to stable 409/422 error codes; create/update responses preserve version/ETag semantics.
- `Idempotency-Key` is required for invoice-draft creation and document attach/detach operations.
- Note and activity routes are append/list only. No update/delete route was introduced.
- Project document Resources expose only allowlisted metadata and authorized API capability URLs. Storage paths, OCR payloads, owner IDs, raw errors, and secrets are excluded.
- History payloads are recursively allowlisted; legitimate audit identifiers are retained while exact money/quantity fields are serialized as strings.
- Pagination metadata, filter-preserving project page links, document-source cursors, and activity cursors are part of the HTTP/OpenAPI contract.

## OpenAPI

Added the full Project v2 request/response/error component set and all Task 10 paths without removing legacy project schemas or paths. The current shared `openapi.yaml` also includes the concurrently authored Quote Task 10 exact-integer-string contract edits (582 additions / 27 replacements in the combined file); those Quote OpenAPI changes were intentionally included under root coordination because `openapi.yaml` cannot be committed hunk-isolated safely. No Quote controller, request, resource, or test file is part of this Project commit.

Strict YAML parsing succeeded as OpenAPI 3.1.0 with 541 paths.

## Existing bindings consumed

The current `FinanceServiceProvider` already binds or aliases all required ports: `ProjectRepository`, `ProjectWorkRepository`, `ProjectDocumentRepository`, singleton `ProjectDocumentCatalog`, `ProjectDocumentSource`, `ProjectHistoryRepository`, and `ProjectToInvoicePort`. Task 10 does not modify the provider.

## TDD evidence

The implementation was driven through these red/green slices:

1. Exact route surface, route names, middleware, UUID/numeric constraints, and append-only route absence.
2. Project create/list/show/update, owner 404, validation, pagination, ETag/version conflict, and transition errors.
3. Work/time/ledger/totals exact-value serialization.
4. Document source search, attach/detach idempotency, filtering, ownership, and metadata scrubbing.
5. Project/document notes and merged activity pagination/scrubbing.
6. Move/archive/restore and idempotent invoice-draft creation.
7. Complete OpenAPI path/component/exact-value/capability contract.

## Verification

- Focused Project API plus API surface guard: **13 tests passed, 462 assertions**.
- OpenAPI contract slice: **1 test passed, 37 assertions** (included in the focused result).
- Focused Pint check over only Task 10 PHP paths: **passed**.
- Task 10 HTTP/Request/Resource PHPStan level 10 scope: **no code findings**. The limited-path invocation itself reports the repository configuration's unmatched global `nullsafe.neverNull` ignore because that unrelated diagnostic is absent from the reduced scope.
- Full backend PHPStan level 10 currently reports exactly two pre-existing Task 6 iterable-shape findings outside the Task 10 file list: `ReorderWorkItems::handle()` and `ListProjectWork::handle()` have native `array` returns without value/shape PHPDoc. Task 10 validates those untyped arrays at its HTTP boundary, and no Task 10 PHPStan finding remains.
- Strict YAML parse: **passed**, OpenAPI 3.1.0, 541 paths.
- `git diff --check` over the Task 10 and combined OpenAPI paths: **passed**.

The prescribed broad FinanceModule matrix was run through `php -d memory_limit=512M vendor/bin/phpunit` because the earlier Artisan helper process remained constrained to 128 MiB; no `phpunit.xml` memory claim or change is involved. Result on the current shared worktree: **681 tests, 659 passed, 5,207 assertions, 19 skipped, 2 failures, 1 error**.

The broad run is not claimed green. Its non-Task10 results are:

- Existing approved SQLite schema baseline: `ProjectSchemaTest::test_document_links_enforce_source_owner_revision_pairing_and_active_uniqueness` — expected constraint violation was not raised.
- Existing approved SQLite schema baseline: `ProjectSchemaTest::test_detach_actor_requires_a_timestamp_but_system_detach_is_allowed` — expected constraint violation was not raised.
- Existing HEAD Invoice invariant baseline, independently reproduced by the Invoice Task 11 owner: `InvoiceFinalizationTest::test_zero_net_hardware_quantity_is_omitted_after_exact_aggregation` — finalized invoice cannot perform `calculate_balance` because the existing zero-gross `InvoiceBalance` invariant rejects that read path.

None of those three paths was changed for Project Task 10. The focused Task 10 suite remains green.

## Scope hygiene

- No `FinanceServiceProvider` change.
- No Invoice, Payment, or Quote HTTP/test implementation file included.
- No push, tag, deployment, or integration action performed.
