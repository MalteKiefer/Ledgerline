# Quote Task 10 Report

## Status

Implemented the additive `/api/v1/finance-v2/quotes` HTTP surface with UUID route constraints and the existing authenticated, device-capability, 2FA, Finance-module, and throttling middleware stack. The legacy API remains registered and documented.

The HTTP layer is limited to validation, owner identity construction, command/query dispatch, resource serialization, ETag/status selection, and documented exception mapping. Exact decimal inputs remain strings; totals are returned only as authoritative integer minor units.

## Delivered

- Four quote FormRequests covering exact decimals, dates, filters, pagination, optimistic versions, revision identities, and header-sourced idempotency keys.
- Seven thin quote controllers covering list/show/preview/create, draft/version actions, publish, send, accept/decline, duplicate, and invoice conversion.
- Stable quote, page, revision, and delivery resources. Storage paths, SMTP data, message IDs, operation payloads, and owner IDs are not serialized.
- Owner-scoped bulk read projections for latest delivery and conversion summaries without N+1 queries.
- ETag responses on resources and version conflicts, including the current resource on 409 action conflicts.
- A typed `SendQuoteResult` outcome. Existing application callers keep `SendQuote::handle(): QuoteView`; HTTP uses `handleResult()` so first queueing returns 202 and exact idempotent replay returns 200 without duplicating operation hashes in the controller.
- Parallel Finance v2 OpenAPI paths and `FinanceV2*` schemas while retaining the legacy contract. No v2 client-PDF upload is described.

## TDD and verification

- RED: `QuoteApiTest` initially failed with missing routes (404), missing resources, missing validation, and absent OpenAPI paths.
- RED: send replay regression proved the second exact request incorrectly returned 202 before the typed send outcome was added.
- PASS: `FILES_DISK=local vendor/bin/phpunit -d memory_limit=1G tests/Feature/FinanceModule/Quotes tests/Unit/Modules/Finance/Domain/Quotes` — 179 tests, 176 passed, 3 skipped, 1112 assertions.
- PASS: `QuoteApiTest`, quote persistence/decision suites, and `ApiSurfaceGuardTest` — 55 tests, 53 passed, 2 skipped, 464 assertions (earlier focused gate).
- PASS: `QuoteDeliveryTest` — 18 tests, 17 passed, 1 skipped, 131 assertions.
- PASS: task-scoped PHPStan — zero errors.
- PASS: task-scoped Pint check.
- PASS: `git diff --check`.
- PASS: OpenAPI parses with the repository's installed `yaml` package when the pre-existing duplicate-key check is relaxed; all 521 paths and the new `FinanceV2Quote` schema load. The standing `ApiSurfaceGuardTest` passes.
- PASS: route inspection lists all 15 quote-v2 routes with the complete middleware stack.

## Wider-suite observations

The combined FinanceModule/Finance Domain run reached 778 tests (762 passed, 14 skipped) and failed only two concurrently edited Project schema tests:

- `ProjectSchemaTest::test_document_links_enforce_source_owner_revision_pairing_and_active_uniqueness`
- `ProjectSchemaTest::test_detach_actor_requires_a_timestamp_but_system_detach_is_allowed`

Both failures are outside Quote Task 10 files. Running the combined suite through the normal Artisan wrapper also exhausts the repository's fixed 128 MB PHPUnit limit during repeated application boot; the direct PHPUnit run with 1 GB reaches the two unrelated Project failures above.

## Integration concern

`QuoteToInvoicePort` is still not bound in `FinanceServiceProvider`. Per coordination instructions, this task did not touch or stage the provider while Invoice Task 9 owns it. The conversion route validates and is documented, but a successful conversion request requires the eventual binding:

`QuoteToInvoicePort::class => LegacyInvoiceDraftAdapter::class`

The OpenAPI document also contains a pre-existing duplicate `kind` key around line 1015. Task 10 did not alter that unrelated legacy schema; the repository guard checks balanced flow mappings and remains green.
