# Quote Task 13 Report

## Status

Implemented `LegacyQuoteMapper` and `LegacyQuoteDiagnostic` in
`backend/app/Modules/Finance/Infrastructure/Compatibility`, plus
`docs/finance/quotes-workflow.md`. The mapper is a pure, side-effect-free,
deterministic translation of one legacy `App\Models\FinanceQuote` row into the
shape a later, separate `finance-legacy-migration` plan will write as a
`source_type=legacy.finance_quote`/`source_id={legacy id}` aggregate. It
performs no database writes of its own and runs no bulk migration, matching
the plan's "does not run a bulk migration" boundary exactly.

An unnumbered legacy row (`number IS NULL`) maps to a mutable draft whose
payload shape matches `QuoteDraftFactory`'s output exactly (`title`,
`customer`, `partner_id`, `issue_date`, `valid_until`, `currency`, `lines`,
`discount`, `totals`, `intro_text`, `outro_text`, `internal_note`), so the
later migration can hand it straight to the same persistence layer Tasks 4-5
already built. A numbered row maps to exactly one published revision
(`revision_number: 1`) preserving the legacy `number`/`year`/`seq`, `sent_at`
(falling back to `created_at`) as `published_at`, the original `pdf_path`, and
a freshly computed SHA-256 read from the actual stored bytes on the shared
blob disk — the legacy row carries no hash of its own to trust.
`converted_invoice_id`/`converted_project_id` become unresolved external
references (`resolved: false`) tagged `legacy-invoice:{id}`/`legacy-project:{id}`,
left for the later invoice/project migrations to resolve. Soft-deleted and
expired-by-date rows map exactly like any other row — `deleted_at` is
forwarded as data, and expiry is derived at read time by the existing
`QuoteWorkflow`, never stored.

Every legacy decimal string and JSON numeric token is converted through the
same `Money`/`DecimalQuantity`/`DocumentCalculator` the live quote commands
use, so no float ever enters the pipeline (this module's global "no
floating-point in Domain/Application code" constraint holds inside the mapper
too, even though `Infrastructure/Compatibility` is exempt from the module
boundary guard). A legacy value that cannot round-trip exactly — unsupported
numeric scale, a non-three-letter currency, a partner/product the owner does
not own, a stored total that does not match the exact recalculation, or a
missing/unsafe/non-PDF stored file — returns a blocking `LegacyQuoteDiagnostic`
instead of a mapped row, with `code` drawn from a fixed vocabulary
(`unknown_currency`, `foreign_partner`, `foreign_product`,
`unsupported_numeric_scale`, `server_total_mismatch`, `missing_pdf`,
`invalid_pdf_path`, `invalid_pdf_mime`) so the later migration can produce
exact per-failure-kind counts, as its activation gate requires.

## TDD evidence

- RED: `LegacyQuoteMapperTest` initially failed with "Class LegacyQuoteMapper
  not found" (mapper and diagnostic classes did not exist yet).
- RED (during authoring): the accepted/declined decision-timestamp tests failed
  with "Failed asserting that null is not null" — `accepted_at`/`declined_at`
  are intentionally absent from `FinanceQuote::$fillable` (the server owns
  them), so `->fill()` silently dropped them; fixed by setting them through
  `forceFill()` in the test fixture, matching how the rest of the suite treats
  server-owned legacy columns.
- RED (during authoring): the conversion-reference test failed with a SQLite
  foreign-key violation — `converted_project_id` has a real FK into
  `finance_projects`, so the fixture needed an actual `FinanceProject` row, not
  an arbitrary integer.
- RED (quality gate): PHPStan flagged `(int) $legacy->getKey()` as an
  unsupported cast from `mixed` — `Model::getKey()` has no per-model return
  type. Fixed by reading `$legacy->id` instead, which resolves to the model's
  own `@property int $id` docblock type.
- GREEN: all 16 `LegacyQuoteMapperTest` cases pass, alongside the full legacy
  `FinanceQuoteTest` regression suite, Pint, and a targeted PHPStan run over
  both new files.

## Verification

- `FILES_DISK=local php artisan test tests/Feature/FinanceModule/Quotes/LegacyQuoteMapperTest.php tests/Feature/FinanceQuoteTest.php`: 34 passed, 167 assertions.
- `vendor/bin/pint --test app/Modules/Finance/Infrastructure/Compatibility tests/Feature/FinanceModule/Quotes`: passed.
- `vendor/bin/phpstan analyse app/Modules/Finance/Infrastructure/Compatibility/LegacyQuoteMapper.php app/Modules/Finance/Infrastructure/Compatibility/LegacyQuoteDiagnostic.php --memory-limit=1G`: 0 errors.

## Scope and integration

Task 13 changes are limited to the two new Compatibility classes, the new
`LegacyQuoteMapperTest`, and `docs/finance/quotes-workflow.md`. No bulk
migration runs, no legacy route is touched, no new-module row is written by
this class, and no cutover happens — the seven-step cutover gate is
documented, not executed. `finance-legacy-migration` calls this mapper per row
and must not duplicate its rules; `finance-projects-rewrite` and
`finance-invoices-payments-rewrite` are unaffected.

## Concerns

None new. The Task 11 report's open concern (OpenAPI still declaring Quote
money fields as `integer/int64` instead of decimal strings) remains
unresolved and is unaffected by this task.
