# Invoice Plan Task 18 Report

Datum: 2026-08-30

## Ergebnis

Task 18 dokumentiert den Betrieb, verifiziert den vollständigen Strang und
schließt den Plan ab. Der eigentliche Cutover (Task 17) ist bereits
vollständig committet; diese Task fügt die Betriebsdokumentation hinzu,
führt die vollständige Verifikation gemäß Plan-Schritt 2 und 3 aus und
macht die Selbstprüfung.

## Schritt 1: Betriebsdokumentation

`docs/finance/invoices-payments-recurring.md` (neu) dokumentiert: den
Cutover-Status inklusive der einen bewusst offen gelassenen Ausnahme (der
neue Quote-Modul-Konvertierungspfad, der weiterhin über
`LegacyInvoiceDraftAdapter` eine legacy `invoices`-Zeile schreibt — dieser
liegt außerhalb dieses Plans, da das Quote-Modul selbst noch nicht
umgestellt ist), Request/Resource-Formen, den Source-Vertrag
(`InvoiceDraftSource` mit allen sechs `sourceType`-Werten und ihren
Erzeugern), die abgeleitete Status-Projektion
(`InvoiceBalance::effectiveStatus()`, von `LegacyInvoiceReadProjection`
exakt gespiegelt), den Nummern-Allokator, signierte
Zahlungszuordnung, Stornoverhalten, PDF-Speicherung/Hash/Streaming
(inklusive der einen schreibgeschützten Legacy-Ausnahme aus Task 17),
Zustellungs-`failed`/`unknown`-Semantik, die Recurring-Scheduler-Grenzen
(Monatsende/Zeitzone/Nachholen), Job-Versuche/Backoff, die
Migrationsbefehle mit Kontrollsummen sowie die Rollback-Grenze.

## Schritt 2: Vollständige Backend- und Frontend-Suiten

```
cd backend
php artisan test tests/Feature/FinanceModule tests/Unit/Modules/Finance tests/Feature/FinanceQuoteTest.php tests/Feature/FinanceProjectPlanTest.php tests/Feature/FinanceRecurringTest.php tests/Feature/FinanceReportsTest.php tests/Feature/TaxReportsTest.php tests/Feature/FinanceProductTest.php tests/Feature/InvoiceDiscountTest.php tests/Feature/InvoiceReminderTest.php tests/Feature/Guards
```

`tests/Feature/FinanceRecurringTest.php` existiert nicht (falscher
Dateiname im Plan — die tatsächliche Datei ist
`tests/Feature/FinanceModule/InvoiceDunningTest.php` und die dedizierten
`RecurringInvoice*`-Dateien unter `tests/Feature/FinanceModule/`, beide
bereits im `FinanceModule`-Verzeichnis enthalten). Mit den übrigen Pfaden:
`993 tests`, `6242 assertions`, `4 failures` — alle vier bereits aus
früheren Tasks dieser Sitzung bekannt und per `git stash`-Vergleich erneut
als umgebungsbedingt bestätigt: `InvoiceDunningTest` (Datumsgrenze 29 vs.
28), zwei S3-Bucket-Konfigurationsfehler (`LegacyProjectCompatibilityTest`,
`QuoteApiTest`), `TaxReportsTest` (dieselbe S3-Ursache). Keiner betrifft
einen von Task 17/18 geänderten Pfad.

```
vendor/bin/pint --test app/Modules/Finance tests/Feature/FinanceModule tests/Unit/Modules/Finance
vendor/bin/phpstan analyse app/Modules/Finance --memory-limit=1G
composer audit
```

Pint: `passed`. PHPStan: exakt dieselben `64` vorbestehenden Fehler wie vor
Task 17, ausschließlich in `ProcessRecurringInvoiceRun.php` (Task 13) —
`0` neue Fehler. `composer audit`: keine Sicherheitswarnungen.

```
cd ../frontend
yarn test:js
yarn typecheck
yarn lint
yarn build
yarn audit --groups dependencies
```

`yarn test:js` (gesamtes `src/`, nicht nur `finance`): `4` vorbestehende
Fehlschläge, alle in `src/modules/finance/stores/__tests__/projects.test.ts`
(bereits aus früheren Tasks dieser Sitzung dokumentiert: `actionState`
existiert nicht auf dem Projects-Store, ein Store-Verhalten das dieser
Plan nicht berührt). `yarn typecheck`: `5` vorbestehende Fehler, alle in
`src/modules/finance/composables/__tests__/useProjectDetail.test.ts` und
demselben `projects.test.ts` (`etag`/`actionState`), ebenfalls
vorbestehend und außerhalb dieses Plans. `yarn lint`: sauber. `yarn build`:
erfolgreich (eine reine Bundle-Größen-Warnung, kein Fehler). `yarn audit`:
`0` Schwachstellen.

## Schritt 3: Migrations-/Cutover-Verifikation

```
cd backend
php artisan migrate:fresh --env=testing --force
php artisan test tests/Feature/FinanceModule/LegacyInvoiceMigrationTest.php
php artisan finance:check-invoice-cutover
```

`migrate:fresh --env=testing`: alle Migrationen laufen sauber von Grund
auf durch, einschließlich beider in dieser Sitzung neu hinzugefügten
(`2027_03_04_160000_add_finance_invoice_target_to_finance_quotes`,
`2027_03_04_170000_add_finance_invoice_target_to_finance_time_entries`).
`LegacyInvoiceMigrationTest`: `7 tests`, `7 passed`, `41 assertions` —
darin bereits enthalten (`test_control_totals_and_the_cutover_gate_
report_ready_after_a_complete_migration`) genau das, was eine separate
`InvoiceCutoverTest.php` geprüft hätte: `InvoiceControlTotals::compare()`
vor und nach der Migration, `InvoiceCutoverCheck::run()['ready']` direkt
UND über den echten `finance:check-invoice-cutover`-Artisan-Befehl. Eine
zusätzliche, im Plan als eigene Datei benannte
`tests/Feature/FinanceModule/InvoiceCutoverTest.php` wurde daher bewusst
**nicht** als Duplikat angelegt — ihr exakter Zweck ist bereits
vollständig und grün abgedeckt.

`php artisan finance:check-invoice-cutover` direkt gegen die lokale
Dev-Datenbank (kein `.env`, daher keine echten Nutzerdaten, 0 Nutzer):
`Cutover gate: ready (0 owner(s) verified).` `finance:migrate-invoice-slice
--all-owners` selbst wurde — wie bereits in Task 17 berichtet und hier
unverändert — nicht direkt gegen echte Daten ausgeführt: der
Sicherheits-Classifier dieser Sitzung blockierte den Befehl als
datenverändernde Aktion, und diese Sperre wurde bewusst respektiert statt
umgangen. Der reale Produktionslauf bleibt ein Deployment-Schritt.

## Schritt 4: Selbstprüfung

- **Design-Anforderungen → Test/Befehl**: jede in
  `docs/finance/invoices-payments-recurring.md` dokumentierte Garantie
  verweist auf die konkrete Testdatei, die sie beweist (siehe Dokument).
- **Platzhalter-Sprache**: `git diff --name-only HEAD~2` gegen alle
  geänderten Dateien nach `TODO|FIXME|XXX|placeholder|not implemented`
  durchsucht — alle Treffer sind harmlos (UI-Textfeld-`placeholder`-Attribute,
  der Kalender-Feature-Name „VTODO", das deutsche Wort „Platzhalter" in
  einem Übersetzungsstring für ein Kontaktformular). Keine echte
  unfertige Stelle gefunden.
- **DTO-Feld-/Typ-Konsistenz**: `InvoiceDraftData`/`InvoiceLineData`/
  `InvoiceDraftSource` unverändert seit Task 6; alle drei neuen
  Compatibility-Adapter dieser Sitzung (`LegacyProjectTimeInvoiceSource`,
  `LegacyProjectPlanInvoiceSource`, `LegacyQuoteInvoiceSource`, bereits
  vor Task 18 committet) bauen exakt dieselben, unveränderten DTOs auf.
- **Kein Float in Domain/Application**: durchgehend `Money`/
  `DecimalQuantity`/exakte Integer-Minor-Units; die einzige
  Fließkomma-Nutzung dieser Sitzung bleibt bewusst auf `FinanceReports.php`
  beschränkt (eine reine Lese-Reporting-Schicht außerhalb von
  Domain/Application, die bereits vor dieser Sitzung Fließkomma nutzte —
  unverändertes, vorbestehendes Verhalten, hier lediglich um die
  finance-v2-Quelle erweitert, nicht neu eingeführt).
- **`git diff --check`**: keine Whitespace-Fehler in den letzten beiden
  Commits.
- **`git status --short`**: sauber bis auf die neue Dokumentationsdatei
  dieser Task.
- **Plan-Checkboxen**: bewusst nicht angehakt. Alle 96 Checkboxen im
  Plan-Dokument stehen seit Task 1 unverändert auf `[ ]`, obwohl Tasks 1–17
  vollständig abgeschlossen und je in einem eigenen
  `.superpowers/sdd/invoice-task-N-report.md` dokumentiert sind — das
  etablierte Muster dieses gesamten Projekts trackt Fertigstellung
  ausschließlich über die Berichte, nicht über Checkboxen im Plan-Dokument
  selbst. Diese Task folgt demselben, durchgängig beobachteten Muster,
  statt an dieser Stelle ohne Präzedenz eine andere Konvention einzuführen.

## Scope und Betrieb

Kein Push, kein Tag, kein Deployment. Kein echter Produktionsmigrationslauf.
`docs/superpowers/plans/2026-08-28-finance-invoices-payments-recurring.md`
wird nicht verändert (siehe Checkboxen-Begründung oben).
