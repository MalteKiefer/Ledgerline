# Project Plan Task 12 — project workspace interface implementation report

Date: 2026-08-30

## Outcome

Built the isolated Project v2 workspace UI on top of the Task 11 client-state
foundation: nine focused components (status badge, summary cards, work,
time, ledger, documents, document picker, notes, activity timeline), three
pages (list/detail/edit), and an unmounted `routes.ts`. Added `finance-projects`
translation groups in `en`/`de`/`ru`.

Before building the UI, the two Task 11 frontend test files left mid-edit in
the shared checkpoint commit (`useProjectDetail.test.ts`, `projects.test.ts`)
were confirmed to require store/composable changes, not just test fixes — they
exercised behavior the Task 11 implementation did not yet have. That gap was
closed as a prerequisite (see "Store and composable corrections" below) before
any Task 12 file was written, since the detail pages depend on that exact
contract.

## Store and composable corrections

- `useProjectsStore` gained per-scope (`action:id`) monotonic sequencing for
  every named mutation (`create`, `update`, `changeStatus`, `move`, `archive`,
  `restore`, plus the three idempotency-keyed actions). A stale in-flight
  mutation's resolution can no longer overwrite a scope's later state — either
  the global `actionError` or an action's cached idempotency key. `actionState(action, id)`
  exposes the isolated `{ loading, error }` for a given scope.
- `loadProject` now clears `current`/`currentEtag` synchronously the instant
  the requested id differs from the currently loaded project, before the
  network call resolves — a same-id refresh failure still preserves the prior
  successful data.
- `useProjectDetail`'s `project` panel gained an `etag` field and now
  delegates its network call through `projects.loadProject` (previously it
  called `projectApi.showResponse` directly, bypassing the store), so panel
  state and store state stay synchronized, including on project-switch
  cancellation.

## UI decisions

- Status transitions are exposed only as named buttons for the exact allowed
  targets of the project's current status (locked transition table); there is
  no generic status field anywhere in the editor.
- Work items, time entries, and ledger entries are separately paged panels,
  each independently loaded/loading/erroring; a time entry with
  `invoiced_at` set renders as locked (no edit/delete affordance) and offers
  no unbilling action.
- The document picker searches the Task 10 source-search endpoint with a
  role selector and emits an `attach` payload; the documents panel lists
  attached links with availability (`available|missing|deleted`), current vs.
  snapshot title, and a detach action that never deletes the link row.
- The notes composer has no edit/delete controls. Choosing "correct" on an
  existing note pre-fills a new entry with `supersedes_note_id` set and shows
  the correction target inline; the original entry stays visible unmodified.
- The activity timeline distinguishes project-sourced from linked-document-sourced
  events and exposes a manual "load more" bound to the opaque `next_cursor`
  — it never decodes or reconstructs the cursor.
- Every money/hour value renders through an exact string formatter
  (`components/projects/format.ts`) that never routes through JavaScript
  `Number`; a Task 12 test exercises an 18-digit minor-unit value end to end
  through `ProjectSummaryCards` to prove no precision is lost.
- A version conflict on status/move/archive/restore/save surfaces a distinct
  banner from a generic request failure and requires an explicit reload
  action before retrying; it never auto-applies the server's version.
- `routes.ts` exports the four project routes and is not wired into
  `frontend/src/router/index.ts`; `frontend/src/stores/finance.ts` and
  `frontend/src/views/Finance.vue` are untouched.

## TDD evidence

Four new Vitest files, each red before the corresponding page/component
existed: `ProjectListPage.test.ts` (URL filters, pagination, route export),
`ProjectEditPage.test.ts` (create-and-navigate, version-conflict reload),
`ProjectDetailPage.test.ts` (independent panel loads, exact large totals,
status-transition scoping, version-conflict banner, tab switching). The store/
composable corrections above were also driven red-to-green against the two
pre-existing (previously failing) Task 11 test files.

## Verification

- Focused Vitest (`src/modules/finance`): **13 files, 47 tests passed**.
- Strict frontend typecheck (`vue-tsc --noEmit`): **passed, zero errors**.
- Full frontend lint (`eslint src/modules/finance`): **passed, zero warnings**.
- Production build (`npm run build`): **succeeded**; only the pre-existing
  unrelated large-chunk warning (`es-*.js`, `ServerDetail-*.js`) is present.
- Backend translation guards
  (`TranslationUsageGuardTest`, `TranslationParityGuardTest`): **2/2 passed**
  — every literal `t('finance-projects.…')` key resolves and `en`/`de`/`ru`
  stay in exact parity.

## Scope hygiene

- Only the Task 12 file list (nine components, three pages, `routes.ts`,
  three lang files, four test files) plus the two Task 11 store/composable
  files it depended on are included. `format.ts` is one small additional
  helper factoring out exact-decimal display formatting shared by four
  components, to avoid duplicating that logic; it is pure, untyped-free of
  side effects, and covered indirectly by the page tests.
- No backend, router, global-store, `Finance.vue`, Quote, Invoice, or Payment
  file is touched.
- No push, tag, deployment, or integration action was performed.
