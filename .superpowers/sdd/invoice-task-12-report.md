# Invoice Plan Task 12 Report

Datum: 2026-08-30

## Ergebnis

Task 12 implementiert effektiv-datierte Versionierung von wiederkehrenden
Rechnungsvorlagen: `CreateRecurringInvoiceTemplate` legt Version 1 an,
`AddRecurringInvoiceTemplateVersion` fügt eine neue, ausschließlich zukünftig
wirksame Version hinzu, `PauseRecurringInvoiceTemplate`/
`ResumeRecurringInvoiceTemplate` schalten den Scheduler-Claim-Status um. Jede
Version trägt einen kanonischen JSON-Snapshot (sortierte Objekt-Keys, keine
Floats, exakte Centbeträge über `DocumentCalculator`) und dessen SHA-256; die
Versionsauswahl für ein Vorkommen wählt das höchste `(effective_from,
version_number)`-Paar, das nicht nach dem lokalen Belegdatum liegt.
`RecurrenceSchedule::fromLocal()`/`nextAfter()` berechnen den ersten und jeden
folgenden Lauf als UTC-Instant aus lokalem Datum/Uhrzeit/Zeitzone und bilden
DST-Lücken (vorwärts) und DST-Mehrdeutigkeiten (früherer Instant) korrekt ab;
der Monatsend-Anker bleibt über unterschiedlich lange Monate und Schaltjahre
stabil.

Alle vier Commands delegieren ausschließlich an den neuen
`RecurringInvoiceRepository`-Port; `EloquentRecurringInvoiceRepository`
implementiert Locking (`withLockedTemplate`/`withLockedRun`), optimistische
Konfliktprüfung über eine `version`-Spalte und Idempotenz über den bestehenden
`IdempotencyKey`-Mechanismus. Die Session begann als unverifizierter
WIP-Checkpoint aus einer unterbrochenen vorherigen Sitzung; diese Runde hat den
Code gelesen, die Tests tatsächlich ausgeführt (grün ohne Änderungen nötig),
Pint und ein projektweites PHPStan verifiziert, und den ursprünglichen
`wip(finance)`-Commit anschließend in einen sauberen
`feat(finance)`-Commit für Task 12 sowie einen separaten, inhaltlich
unveränderten Checkpoint-Commit für das nicht zu diesem Plan gehörende
Quote-UI-Material aufgeteilt (per `git reset --soft` auf denselben, noch nicht
gepushten Branch; kein Force-Push, keine Historie außerhalb dieses Worktrees
betroffen).

## Abgedeckte Invarianten

- Version 1 wird mit exaktem kanonischem Snapshot, dessen SHA-256 und dem
  ersten Lauf als UTC-Instant persistiert; das lokale Startdatum bleibt
  nachvollziehbar aus Zeitzone/Uhrzeit rekonstruierbar;
- ungültige Zeitzone (kein IANA-Ort außer UTC), Enddatum vor Startdatum und
  nicht unterstütztes Intervall werden vor jeder Persistenz abgelehnt;
- Kontroll-Summen (`control_net_minor`/`control_vat_minor`/
  `control_gross_minor`), sofern gesetzt, müssen exakt zum Server-Ergebnis
  passen, sonst `document_totals_mismatch`;
- neue Partner-/Projekt-/Produktreferenzen werden owner-scoped geprüft;
- eine neue Version hat ein explizites `effective_from`, mutiert keine
  bestehende Version und keinen bestehenden Run; bereits erzeugte Runs
  behalten ihre referenzierte Version auch nach Einfügen einer späteren
  effektiv-datierten Version;
- Pause erhält bereits erzeugte Rechnungen/Runs und den nächsten fälligen
  Termin; Resume berechnet nur die Claim-Berechtigung neu und überspringt
  keine überfälligen Vorkommen;
- optimistische Versionskonflikte liefern das aktuelle Template zurück statt
  eines stillen Überschreibens;
- derselbe Idempotency-Key mit gleichem Payload replayt das vorherige
  Ergebnis, ein abweichender Payload liefert `idempotency_conflict`, ein
  gleichzeitig laufender (pending) Key mit demselben Payload führt die
  Mutation kein zweites Mal aus;
- echter PostgreSQL-Zwei-Prozess-Pfad: zwei gleichzeitige Versions-Writer
  haben genau einen CAS-Gewinner (optional, `FINANCE_TEST_PGSQL_URL`).

## TDD-Evidenz

Der WIP-Checkpoint enthielt bereits Produktivcode und Tests aus der
unterbrochenen Sitzung; diese Runde hat sie nicht blind übernommen, sondern
zuerst real ausgeführt (`php artisan test`), dann Pint und ein projektweites
PHPStan verifiziert, bevor irgendetwas als abgeschlossen behandelt wurde. Der
frühere RED/GREEN-Verlauf der ursprünglichen Sitzung ist nicht rekonstruierbar
(keine Zwischen-Commits), daher dokumentiert dieser Report ausschließlich die
Verifikation dieser Runde statt einer nachträglich erfundenen RED-Historie.

## Verifikation

- `RecurringTemplateApplicationTest` + `RecurrenceScheduleTest`:
  `18 tests`, `17 passed`, `83 assertions`, `1 skipped` (optionaler
  PostgreSQL-Zwei-Prozess-Test, sicher übersprungen ohne
  `FINANCE_TEST_PGSQL_URL`).
- `InvoiceProviderBindingsTest` (prüft `RecurringInvoiceRepository`-Bindung):
  `1 passed`, `10 assertions`.
- Fokussiertes Pint (`Application/Commands/Recurring`,
  `Application/DTOs/Recurring`, `RecurringTemplateApplicationTest.php`):
  `passed`.
- Projektweites PHPStan (`vendor/bin/phpstan analyse --memory-limit=1G`,
  Level 10, ganzes `app`-Verzeichnis): `0 errors`. Ein auf die Recurring-Pfade
  eingeschränkter PHPStan-Lauf meldete fälschlich einen "unmatched
  ignoreErrors pattern" für `nullsafe.neverNull`; das ist ein reines Artefakt
  der Pfad-Einschränkung (die global ignorierte Regel greift nur außerhalb der
  Recurring-Dateien) und verschwindet beim projektweiten Lauf, der als
  maßgeblich gilt.
- `git diff --check`: sauber.

## Scope und Betrieb

Zusätzlich zu den drei im Plan genannten Verzeichnissen/Dateien
(`Application/Commands/Recurring`, `Application/DTOs/Recurring`,
`RecurringTemplateApplicationTest.php`) enthält der Commit die bereits im
WIP-Checkpoint vorhandenen, für Task 12 notwendigen Erweiterungen:
`Application/Ports/RecurringInvoiceRepository.php`,
`Domain/Recurring/RecurrenceSchedule.php` (DST/Monatsend-Erweiterung plus
`RecurrenceScheduleTest.php`), `Infrastructure/Persistence/
EloquentRecurringInvoiceRepository.php`, die `FinanceServiceProvider`-Bindung,
die drei `lang/*/invoices.php`-Übersetzungen für neue stabile Fehlercodes,
`InvoiceProviderBindingsTest.php` (Bindungs-Assertion für den neuen Port) und
den `.gitignore`-Eintrag für das lokale `finance-document-locks`-Laufzeitlock-
Verzeichnis, das `withLockedTemplate`/`withLockedRun` verwenden.

Der `.gitignore`-Eintrag und die nicht zu diesem Plan gehörenden Quote-UI-
Dateien (Task 12 des separaten Quotes-Workflow-Plans, von einem anderen Agent
in einem anderen Worktree bearbeitet) lagen im selben ursprünglichen
`wip(finance)`-Checkpoint-Commit. Dieser Commit war noch nicht gepusht und
existierte ausschließlich auf diesem Branch in diesem Worktree; er wurde daher
lokal per `git reset --soft` in zwei inhaltlich unveränderte Commits
aufgeteilt: den vorliegenden `feat(finance): version recurring invoice
templates` (nur Recurring-Task-12-Dateien) und einen unveränderten
`wip(finance): checkpoint in-progress quote UI` für das Fremd-Material, das
laut Auftrag nicht anzufassen, aber auch nicht zu verlieren war.

Kein Push, kein Tag, kein Deployment, keine Route/OpenAPI-/Migrationsänderung.
Der optionale PostgreSQL-Zwei-Prozess-Test wurde nicht lokal ausgeführt
(keine konfigurierte `FINANCE_TEST_PGSQL_URL`); er ist als Skip klar sichtbar
und nicht Teil dieser Verifikation.
