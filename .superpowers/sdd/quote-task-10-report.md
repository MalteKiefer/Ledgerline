# Quote Task 10 Report

## Status

Implemented and review-hardened the additive `/api/v1/finance-v2/quotes` HTTP surface. Controllers remain thin adapters over Quote commands and queries; UUID/owner scoping, authentication, device capability, Finance module gating, throttling, ETags, optimistic versions, revision identities, and header-sourced idempotency keys are enforced at the boundary.

The review findings are resolved:

- `QuoteToInvoicePort` is bound to `LegacyInvoiceDraftAdapter`; a container/API regression publishes, accepts, and converts an immutable quote revision into a real owner-owned legacy invoice draft.
- Send hashes now include canonical nullable `change_reason` and the raw nullable recipient. Completed exact replays are resolved before SMTP configuration and mutable workflow preflight; a changed reason with the same key returns `idempotency_key_reused`.
- Unsupported `expense` lines are rejected by request validation and removed from the v2 OpenAPI contract.
- Non-workflow `InvalidArgumentException` failures map to stable machine codes and never expose exception prose.
- Preview, quote, draft, canonical snapshot, revision, delivery, conversion, and page schemas specify exact fields, nullable required keys, types, and closed top-level objects. Quote 422 responses use the documented validation-or-domain-error union.
- Every mutation has a successful HTTP regression; every mutation hides foreign-owner aggregates with 404; revision history is included in the route matrix and exercised.
- The pre-existing duplicate legacy `kind` YAML key was removed, and OpenAPI now parses with strict duplicate-key checks.

## TDD evidence

- RED: expense preview returned a domain prose body instead of a field validation error.
- RED: invalid tax input returned exception prose instead of `invalid_tax_rate`.
- RED: a completed Send replay consulted changed SMTP/state first, and `change_reason` reuse was not detected.
- RED: strict YAML parsing stopped on the duplicate legacy `kind` key.
- RED: `QuoteToInvoicePort` was not instantiable from the container.
- RED: the full Quote suite exposed one Task 8 crash-recovery fixture still using the pre-review Send hash; the fixture was updated to the canonical nullable request shape.
- GREEN: all focused and full verification listed below.

## Verification

- `QuoteApiTest` plus `ApiSurfaceGuardTest`: 22 tests passed, 297 assertions.
- Full Quote feature, Quote domain, and API surface suite with `php -d memory_limit=1G`: 190 tests; 187 passed, 3 PostgreSQL/environment skips, 1202 assertions.
- Strict OpenAPI parsing uses the repository-installed `yaml` package with default duplicate-key rejection and passes.
- Task production files PHPStan (`--memory-limit=1G`): zero errors.
- Task files Pint: passed.
- `git diff --check`: passed.

The normal Artisan wrapper retains the repository-wide 128 MB test-process limit and exhausted it in Dompdf during the combined suite. Re-running the identical suite through PHPUnit with a 1 GB process limit completed successfully; this is a runner-memory constraint, not a functional failure.

## Scope and integration

Only the reviewed Quote command/request/controller tests, the one Task 8 Send recovery fixture, the Quote conversion binding, OpenAPI, and this report are included. Concurrent Payment work visible in the shared worktree is excluded from the explicit-path commit. No push, tag, deploy, or history rewrite was performed.

No unresolved functional concern remains. The three full-suite skips are the existing opt-in PostgreSQL/runtime concurrency paths and remain available through their documented environment configuration.

## Exact-integer wire-contract follow-up

The Task 11 review exposed a JavaScript precision boundary in the original Task 10 schema: Quote money, scaled quantity, and basis-point values were emitted as JSON numbers. The HTTP contract now emits every controlled `*_minor`, `minor`, `*_scaled`, `*_basis_points`, and `basis_points` business integer as a canonical decimal string at the shared Quote Resource boundary. UUIDs remain strings; database IDs, optimistic versions, delivery attempts, and pagination/count metadata remain integers.

The serializer covers root totals, mutable drafts, immutable revision snapshots, revision totals, list rows, detail resources, mutation responses, Send replays, and conflict `current` resources. Regression fixtures persist values above JavaScript's `2^53` safe range and prove exact detail, list, and revision-history JSON output.

Optional control totals now accept only signed canonical decimal integer strings: no JSON numbers, plus signs, leading zeros, or negative zero. Request validation rejects values outside the PHP integer range before conversion. Exact `PHP_INT_MIN`, `PHP_INT_MAX`, and `9007199254740993` inputs reach the Application layer without precision loss and are then rejected by the existing narrower Money domain with stable `invalid_money`; valid positive and negative controls retain existing semantics.

OpenAPI uses matching string types, canonical signed/non-negative patterns, and quoted string examples for every Quote money/scaled/basis-point property. Strict YAML parsing and a field-by-field schema matrix guard the contract.

### Follow-up TDD and verification

- RED: create, preview, nested detail/list/revision, and OpenAPI matrix tests observed JSON integers.
- RED: numeric and noncanonical control totals passed the old Laravel `integer` rule.
- GREEN: focused Quote API suite with `FILES_DISK=local`: 20 tests passed, 414 assertions.
- GREEN: Quote API plus global API surface guard on the shared Project/Quote OpenAPI HEAD: 24 tests passed, 418 assertions.
- GREEN: strict OpenAPI parsing is included in that suite and passes with 95 schema assertions in its focused test.
- GREEN: focused Quote HTTP PHPStan reports zero errors; focused Pint passes.
- The combined OpenAPI file, including these Quote schema changes, was committed by the coordinated Project Task 10 commit `dd441a6a`; this Quote follow-up does not duplicate it.

This follow-up changes only Quote HTTP request/resource serialization, its API/contract tests, OpenAPI, and this report. It does not change frontend code, route mounting/cutover, Domain calculations, persistence, provider bindings, or legacy endpoints.
