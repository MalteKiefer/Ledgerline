# Invoice Plan Task 13 Report

Datum: 2026-08-30

## Ergebnis

Task 13 implementiert den idempotenten Scheduler für wiederkehrende
Rechnungen: `finance:run-recurring-invoices` (jede Minute, `withoutOverlapping`
+ `onOneServer`) beansprucht in einer einzigen, gebundenen Transaktion jede
fällige Vorkommnis über alle Owner hinweg (global maximal 1.000, pro Vorlage
maximal 100 pro Tick), erzeugt je Vorkommnis genau eine `pending`-Run-Zeile
und rückt `next_run_at` nur um die tatsächlich beanspruchten Vorkommnisse vor
— ein späterer Tick setzt exakt dort fort, ohne je eine fällige Vorkommnis zu
überspringen. Nach dem Commit der Claim-Transaktion wird für jeden neuen Run
`ProcessRecurringInvoiceRunJob` dispatcht; zusätzlich fegt derselbe Tick über
jeden noch nicht abgeschlossenen Run (`inFlightRuns`), damit ein verlorener
Dispatch (Absturz zwischen Commit und Dispatch) oder ein noch unaufgelöster
asynchroner Mail-Schritt spätestens beim nächsten Tick weiterläuft.

`ProcessRecurringInvoiceRun` treibt einen Run um genau einen ausführbaren
Schritt weiter und wird von seinem Job wiederholt aufgerufen, bis er entweder
terminal ist oder auf den asynchronen Mail-Versand wartet. Der Resume-Punkt
ist ausschließlich der persistierte `last_completed_step` — nicht der rohe
Status —, weil ein per `RetryRecurringInvoiceRun` zurückgesetzter Run immer
wieder bei Status `pending` startet, aber exakt dort weiterlaufen muss, wo er
stand. `draft`-Vorlagen halten terminal bei `draft_created`; `auto_send`
läuft über Entwurf -> Finalisierung/PDF -> Zustellung-Staging -> `sending`.
Der tatsächliche Mail-Versand bleibt asynchron (`SendInvoiceDeliveryJob`
existierte bereits aus Task 9); dieser Befehl beobachtet nur dessen Ergebnis
über den neuen `InvoiceRepository::deliveryStatus()`-Port und wartet bei
`unknown` weiter, statt Erfolg oder Fehlschlag zu erfinden.

Jeder Zustandsübergang läuft durch den neuen
`RecurringInvoiceRepository::transitionRun()`-Port unter Zeilensperre und wird
gegen eine PHP-Übergangstabelle geprüft, die exakt die bereits in der
Recurring-Migration (Task 4) vorhandenen Datenbank-Trigger
(`finance_recurring_runs_integrity_check`,
`finance_recurring_run_progress_guard`) spiegelt — diese bleiben die
maßgebliche, verteidigungstiefe Prüfung. `ProcessRecurringInvoiceRunJob` ist
`ShouldBeUnique` je Run-UUID, 5 Versuche, Backoff `[60, 300, 1800, 7200]`;
Auth wird pro Job über `Auth::onceUsingId()` gesetzt und in einem `finally`
wiederhergestellt (nicht pauschal vergessen), damit ein Worker-Prozess, der
Jobs verschiedener Owner nacheinander abarbeitet, keinen Owner-Kontext in den
nächsten Job durchsickern lässt.

## Abgedeckte Invarianten

- zwei Claim-Aufrufe zum selben eingefrorenen Zeitpunkt erzeugen genau eine
  Run-Zeile für `(Vorlage, scheduled_for)`;
- ein Tick beansprucht höchstens 100 Vorkommnisse je Vorlage; ein 250 Monate
  rückständiges Monatstemplate braucht exakt drei Ticks (100/100/50), ohne
  Lücke und ohne Duplikat, `next_run_at` zeigt danach exakt auf die 251.
  Vorkommnis;
- pausierte Vorlagen liefern keine Runs; eine Vorlage mit Enddatum wird nach
  ihrer letzten Vorkommnis `completed` (`next_run_at`/`paused_at` NULL) und
  liefert danach keine weiteren Runs;
- `draft`-Modus erzeugt genau einen Entwurf und bleibt bei jedem erneuten
  Sweep terminal — kein zweiter Entwurf, keine Finalisierung;
- `auto_send` durchläuft Entwurf -> finalisiert/PDF -> Zustellung -> `sent`;
  die Rechnung selbst landet im Workflow-Status `sent`;
- ein injizierter Fehler in der Finalisierung markiert den Run `failed` mit
  stabilem Fehlercode, ohne die Rechnung zu verlieren; nach `Retry` wird
  dieselbe Rechnung fertiggestellt — kein zweiter Entwurf, keine zweite
  Rechnung;
- ein injizierter Mail-Dispatch-Fehler wiederholt ausschließlich den
  Zustellungsschritt: die Finalisierung (und damit `pdf`-Renderer-Aufrufe)
  läuft exakt einmal, ein zweiter `Retry`-Durchlauf sendet lediglich erneut.

## TDD-Evidenz

Beobachtete RED-Phasen während dieser Runde:

1. `$asOf` wurde in `claimDueRuns()`s Transaktions-Closure verwendet, aber
   nicht in die `use`-Klausel aufgenommen — `ErrorException: Undefined
   variable $asOf` bei jedem Claim-Test;
2. die ursprüngliche Übergangsprüfung kannte nur den rohen Status, nicht
   `last_completed_step` — ein nach einem Fehlschlag zurückgesetzter Run
   (`failed` -> `pending`) versuchte fälschlich, erneut einen Entwurf
   anzulegen, statt bei der Finalisierung fortzusetzen; behoben durch
   Umstellung des Resume-Punkts auf `last_completed_step` und Erweiterung von
   `assertRunTransition()` um genau die (Status, Step)-Paare, die die
   Datenbank-Trigger bereits zulassen;
3. `RetryRecurringInvoiceRun`, direkt im Test nach einem abgeschlossenen
   Scheduler-Tick aufgerufen, scheiterte mit „Recurring invoice persistence
   requires an authenticated owner“ — der Job hatte `Auth::forgetGuards()`
   im `finally` aufgerufen und damit den `actingAs()`-Kontext des Tests
   mitgelöscht; behoben, indem der Job den vorherigen Auth-Nutzer merkt und
   wiederherstellt, statt pauschal zu vergessen;
4. die Fake-`DocumentStorage` im Testfile erzeugte Pfade im Format
   `finance/revisions/{uuid}/{token}.pdf` statt des von
   `EloquentInvoiceRepository::assertDeliveryReady()` erzwungenen
   `finance/revisions/{2-hex}/{64-hex}.pdf`; jeder Zustellungsschritt schlug
   mit `delivery_pdf_unavailable` fehl, bis der Fake denselben SHA-256-basiert
   gesharded Pfad wie der echte `LaravelDocumentStorage` erzeugte.

Jeder Fehler wurde einzeln beobachtet, auf die kleinste produktive Ursache
zurückgeführt und danach erneut grün ausgeführt.

## Verifikation

- `RecurringInvoiceSchedulerTest`: `7 tests`, `7 passed`, `41 assertions`.
- `RecurringInvoiceSchedulerTest` + `RecurringTemplateApplicationTest`:
  `15 tests`, `14 passed`, `96 assertions`, `1 skip` (optionaler
  PostgreSQL-Zwei-Prozess-Pfad, ohne `FINANCE_TEST_PGSQL_URL`).
- `RecurringInvoiceSchedulerTest` + `RecurringTemplateApplicationTest` +
  `RecurrenceScheduleTest` + `InvoiceDeliveryTest` +
  `InvoiceProviderBindingsTest` (Regressionsschutz für den neuen
  `deliveryStatus()`-Port und die Scheduler-Bindungen): `42 tests`,
  `40 passed`, `212 assertions`, `2 skips`.
- Vollständiges `tests/Feature/FinanceModule` (mit `-d memory_limit=1G`, weil
  ein vorbestehender, von dieser Task unabhängiger Fehler in `InvoicePdfTest`
  echtes dompdf-Font-Rendering über dem Standard-CLI-`memory_limit=128M`
  auslöst): `686 tests`, `4863 assertions`, `3 failures`, `19 skips`. Alle
  drei Failures (`QuoteApiTest` zweimal, `LegacyProjectCompatibilityTest`
  einmal) sind derselbe vorbestehende, umgebungsbedingte Fehler — ein fehlender
  S3-Bucket-Konfigurationswert lässt `Storage::disk('files')` beim Aufbau des
  `files`-Adapters scheitern — und betreffen keinen von dieser Task berührten
  Pfad; keiner der Recurring-Tests ist unter den Failures.
- Fokussiertes Pint (`Application/Commands/Recurring`,
  `Infrastructure/Scheduling`, betroffene Ports/Repositories,
  `routes/console.php`, `bootstrap/app.php`,
  `RecurringInvoiceSchedulerTest.php`): `passed` (ein Lauf normalisierte
  Import-Reihenfolge/Klassen-Layout in der neuen Testdatei automatisch,
  anschließend erneut grün verifiziert).
- Projektweites `vendor/bin/phpstan analyse --memory-limit=1G` (Level 10,
  ganzes `app`-Verzeichnis): `0 errors`.

## Scope und Betrieb

Zusätzlich zu den im Plan genannten Dateien waren folgende dokumentierte
Erweiterungen nötig:

- `Application/Ports/RecurringInvoiceRepository.php` /
  `Infrastructure/Persistence/EloquentRecurringInvoiceRepository.php`: drei
  neue Methoden — `claimDueRuns()` (owner-übergreifend, für den Scheduler,
  da kein Auth-Kontext existiert), `inFlightRuns()` (Sweep-Liste) und
  `transitionRun()` (die einzige erlaubte Schreibstelle für Run-Zustände,
  gegen die PHP-Spiegel-Übergangstabelle geprüft).
- `Application/Ports/InvoiceRepository.php` /
  `Infrastructure/Persistence/EloquentInvoiceRepository.php`: neue
  schreibfreie `deliveryStatus()`-Methode, damit die Recurring-Verarbeitung
  den asynchronen Mail-Ausgang beobachten kann, ohne eine neue
  Zustellungs-Ausnahme im Invoice-Port zu erfinden.
- `bootstrap/app.php`: `->withCommands([app_path('Modules/Finance/
  Infrastructure/Scheduling')])` ergänzt. Laravels Standard-Kommandopfad ist
  ausschließlich `app/Console/Commands`; ohne diese Ergänzung wäre
  `finance:run-recurring-invoices` nie auffindbar gewesen. Kein anderer
  Ordner wird zusätzlich gescannt; `ProcessRecurringInvoiceRunJob` in
  demselben Verzeichnis ist kein `Illuminate\Console\Command` und wird von
  der Auto-Discovery stillschweigend ignoriert (verifiziert über
  `php artisan list finance`).

Kein Push, kein Tag, kein Deployment, keine Route/OpenAPI-Änderung. Der
optionale PostgreSQL-Zwei-Prozess-Pfad wurde nicht lokal ausgeführt (keine
`FINANCE_TEST_PGSQL_URL` konfiguriert); alle betroffenen Tests markieren ihn
sauber als Skip statt ihn stillschweigend zu übergehen.
