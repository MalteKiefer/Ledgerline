# Project schema SQLite guard repair report

Date: 2026-08-29

## Outcome

Added an additive migration after `2027_03_04_130000_add_operation_identity_to_project_document_links.php` that restores every `finance_project_document_links` integrity guard lost when Laravel rebuilds that table on SQLite. PostgreSQL is an explicit no-op and continues using the native constraints, validation trigger, and partial unique index from the original project workflow migration.

## Root cause

The original project workflow migration represents document-link integrity differently by database:

- PostgreSQL uses native CHECK constraints, composite foreign keys, a source-reference validation trigger, and a partial unique index for active links.
- SQLite uses composite foreign keys plus insert/update triggers for CHECK semantics, source-reference validation, and active-link uniqueness. Its original `enum()` columns also receive SQLite CHECK clauses.

The later operation-identity migration adds `attached_operation_id` and `detached_operation_id` with `Schema::table`. Laravel implements that SQLite alteration by rebuilding `finance_project_document_links`. The rebuilt table preserved columns, composite owner/series/revision foreign keys, and ordinary indexes, but did not preserve user-created triggers or the original enum CHECK clauses.

A fresh pre-fix SQLite migration inspection showed only five indexes and the table definition for `finance_project_document_links`; no link trigger survived. This explains both tracked failures and also exposed the missing source-type/role allowlists behind the first failure.

## Repair

`2027_03_04_150000_restore_sqlite_project_document_link_guards.php` recreates, on SQLite only:

- source type allowlist, on insert and update;
- role allowlist, on insert and update;
- finance-series/document-series/revision nullability pairing, on insert and update;
- detached actor requires detached timestamp, on insert and update;
- attached and detached actors must match the owner, on insert and update;
- finance-series owner/id/UUID source-reference validation, on insert and update;
- active-link uniqueness, on insert and update.

The migration `down()` drops only these 16 restored link triggers. It leaves the separate `finance_project_document_series_guard_uuid` trigger and every PostgreSQL object untouched.

## TDD evidence

Red evidence before the repair:

- `test_document_links_enforce_source_owner_revision_pairing_and_active_uniqueness`: failed because a forbidden document-link mutation was accepted.
- `test_detach_actor_requires_a_timestamp_but_system_detach_is_allowed`: failed because `detached_by` without `detached_at` was accepted.
- New `test_sqlite_document_link_guards_survive_operation_identity_table_rebuild`: failed at the first expected active-link constraint violation.
- After restoring the four explicit checks/source/active triggers, the new regression failed after six assertions and revealed that the table rebuild had also removed the source-type and role enum allowlists. Those two guards were then added in a second TDD slice.

Green evidence:

- Three targeted document-link regressions: **3 passed, 22 assertions**.
- Complete `ProjectSchemaTest`: **18 passed, 238 assertions**.
- Complete Project feature directory: **192 tests, 188 passed, 1,678 assertions, 4 optional skips**.
- Focused migration PHPStan: **passed, 0 errors**.
- Focused Pint: **passed**.

Fresh SQLite inspection after the repair found all **16** link triggers. Direct migration round-trip inspection produced:

- before repair `down()`: 16 link triggers;
- after repair `down()`: 0 link triggers;
- unrelated series UUID guard after repair `down()`: 1 trigger, preserved;
- after repair `up()`: 16 link triggers restored.

PostgreSQL pretend execution produced zero statements for both repair `up()` and `down()`, confirming the explicit no-op.

## Scope

Only the additive migration, the Project schema regression test, and this report are included. OpenAPI, providers, application repositories, and concurrent Invoice/Quote files are untouched. No push, tag, or deployment was performed.
