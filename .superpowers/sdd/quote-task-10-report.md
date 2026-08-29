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
