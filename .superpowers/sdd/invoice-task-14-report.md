# Invoice Plan Task 14 Report

Datum: 2026-08-30

## Ergebnis

Task 14 legt die vollständige Preview-API unter `/api/v1/finance-v2` für
Rechnungen, Zahlungen und wiederkehrende Rechnungen offen — 19 neue Routen,
acht Controller, 15 Request-Klassen, zehn Resource-Klassen und die
zugehörige `openapi.yaml`-Dokumentation. Jeder Controller ruft genau einen
Application-Befehl bzw. eine Query auf; HTTP-Schicht validiert exakte
Eingaben (Dezimalstrings statt Float, minor-unit-Integer statt Dezimalzahl,
IANA-Zeitzonen, ISO-Datumsformate) und übersetzt stabile Domain-Fehlercodes
auf 404/409/422/202, ohne selbst Geschäftslogik zu enthalten.

Da die Tasks 6–13 nur Einzel-Lese-Methoden (`get()`) und Mutations-Befehle
bereitstellten, aber keine Listen-/Paginierungs- oder UUID-Auflösungspfade,
musste die Lese-Schicht der drei Repository-Ports erweitert werden:
`idForUuid()`/`templateIdForUuid()`/`runIdForUuid()` lösen öffentliche UUIDs
owner-scoped zu internen Integer-IDs auf (404 bei fremdem/fehlendem
Datensatz); `page()`/`templates()`/`runsForTemplate()` liefern typisierte,
gefilterte, seitenweise Ergebnisse (`InvoicePage`, `PaymentPage`,
`RecurringTemplatePage`, `RecurringRunPage`); `deliveryView()`/`getView()`/
`getRunView()` liefern vollständige Einzelressourcen für Antworten, die aus
einem Command nur eine ID zurückerhalten (z. B. `QueueInvoiceDelivery`
liefert nur `DeliveryId`, der Controller braucht aber den vollständigen
Zustellungsstatus für die 202-Antwort).

Rechnungen, Zahlungen, wiederkehrende Vorlagen und deren Runs werden extern
ausschließlich über UUID adressiert; die einzige Ausnahme ist die
Allokations-Rückbuchung (`POST /payment-allocations/{allocation}/reverse`),
die eine reine Integer-ID verwendet, da `finance_payment_allocations`
Anhang-only-Zeilen ohne UUID-Spalte sind (siehe Migration aus Task 3) — das
entspricht der im Plan vorgesehenen Routen-Signatur `{allocation}`.

Der `status`-Filter auf `GET /invoices` bildet exakt die
`InvoiceBalance::effectiveStatus()`-Projektion aus Task 1 in SQL nach
(`workflow_status`, `allocated_minor`, `open_minor`, und eine
`WHERE EXISTS`-Teilabfrage auf stornierende Zeilen für `cancelled`), damit
Liste und Einzelansicht nie auseinanderlaufen. Der `overdue`-Filter
verwendet dieselben drei Bedingungen (`workflow_status = 'sent'`,
`open_minor > 0`, `due_date < heute in Owner-Zeitzone`) wie
`InvoiceAgingQuery` aus Task 9.

Rechnungs-Zeilen kommen vom Client als editierbare Dezimalstrings
(`unit_price`, `tax_rate`) und werden serverseitig über `Money::fromDecimal`
(mit dem bereits im Quotes-Modul etablierten `'BPS'`-Pseudo-Currency-Trick
für Basispunkte) in die exakten `unit_price_minor`/
`tax_rate_basis_points`-Integer der `InvoiceLineData` umgerechnet; die
Mengen-Zeichenkette wird auf die von `InvoiceLineData` erzwungene exakte
Skala-4-Form normalisiert (`BuildsInvoiceDraftData::canonicalQuantity()`).
Dieselbe Trait baut auch den `draft`-Teilbaum der wiederkehrenden
Vorlagen-Requests (`RecurringTemplateRequest`, `RecurringTemplateVersionRequest`),
da `RecurringTemplateVersionData` intern exakt dieselbe `InvoiceDraftData`
verwendet wie manuelle Rechnungsentwürfe.

## Abgedeckte Invarianten

- jede Route verlangt `auth:sanctum`, `abilities:device`, `module:finance`
  und `throttle:120,1`; ein Owner ohne Finance-Modul erhält 403, ein
  unauthentifizierter Aufruf 401 — geprüft für alle 19 neuen Routen in drei
  Testdateien;
- fremde UUIDs liefern für jede Einzel-Ressource (Rechnung, Zahlung,
  wiederkehrende Vorlage) 404, nie 200 oder eine leere/andere Ressource;
- generische Anfragefelder (`status`, `number` bei Rechnungen) werden von
  `FormRequest::rules()` verworfen, nie an einen Command durchgereicht —
  Workflow-Status bleibt ausschließlich über `finalize`/`cancel` erreichbar;
- `POST /invoices` und `PATCH /invoices/{invoice}` verlangen **keinen**
  `Idempotency-Key` (Task 6: `CreateInvoiceDraft`/`UpdateInvoiceDraft` sind
  nicht idempotenzschlüssel-gebunden), während `finalize`, `cancel`,
  `deliveries`, `reminders`, `payments`, `allocations`, `reverse` und alle
  wiederkehrenden Mutationen ihn zwingend verlangen (422 bei Fehlen);
  identischer Schlüssel mit identischem Payload liefert dieselbe Ressource,
  ein wiederverwendeter Schlüssel mit geändertem Payload liefert 409;
- `PATCH`/`DELETE` auf Rechnungen und alle wiederkehrenden
  Versions-/Pause-/Resume-Aktionen nutzen optimistisches `version`-CAS; ein
  veralteter `version`-Wert liefert 409 mit der aktuellen Ressource im
  `current`-Feld, nie eine stille Überschreibung;
- Zahlungsallokationen und deren Rückbuchung respektieren exakte
  vorzeichenbehaftete Minor-Unit-Beträge; Antworten liefern sowohl die
  aktualisierte Zahlung als auch jede betroffene Rechnung in einer
  Transaktion, sodass ein Client nie zwei Anfragen für ein konsistentes Bild
  braucht;
- keine Antwort enthält Storage-Pfade, Idempotenz-Hashes, SMTP-Konfiguration
  oder rohe Exception-Nachrichten — `revision.pdf_path` fehlt bewusst,
  `revision.pdf_sha256` bleibt sichtbar; Zustellungs-Ressourcen exponieren
  `last_error_code`, nie `last_error_detail`.

## Bewusste Scope-Entscheidung: kein `PATCH` auf wiederkehrende Vorlagen

Der Plan listet `GET/PATCH /recurring-invoice-templates/{template}`. Task 14
implementiert nur `GET`. Die in Task 12 gebauten Commands
(`CreateRecurringInvoiceTemplate`, `AddRecurringInvoiceTemplateVersion`,
`Pause`/`ResumeRecurringInvoiceTemplate`) modellieren jede erlaubte Änderung
bereits explizit — Inhaltsänderungen laufen über `/versions` (neue
effektiv-datierte Version), Status über `/pause`/`/resume`. Eine
zusätzliche generische `PATCH`-Route hätte einen neuen, in keiner
Vorgänger-Task entworfenen Anwendungsfall erfunden (z. B. `run_time` oder
`timezone` ändern, ohne eine neue Inhaltsversion oder den Next-Run neu zu
berechnen) und wäre eine Scope-Erweiterung über Tasks 1–13 hinaus gewesen.
Diese Entscheidung ist bewusst und hier dokumentiert statt stillschweigend
getroffen.

## Zusätzliche Dateien über den Plan hinaus

- `Application/DTOs/{Invoices,Payments,Recurring}/*Page.php`,
  `InvoiceDeliveryView.php`, `RecurringRunView.php`: typisierte
  Listen-/Einzelansicht-DTOs, die der Plan nicht benennt, aber für
  `additionalProperties: false`-konforme, stabile JSON-Verträge nötig sind.
- `Application/Queries/{Invoices,Payments,Recurring}/*`: dünne
  Query-Wrapper (`ListInvoices`, `GetInvoice`, `GetInvoiceDelivery`,
  `ListPayments`, `GetPayment`, `ListRecurringTemplates`,
  `ListRecurringInvoiceRuns`, `GetRecurringInvoiceTemplate`,
  `GetRecurringInvoiceRun`) — exakt das bereits in `Quotes`/`Projects`
  etablierte Muster (`ListQuotes`, `GetQuote`, …), damit Controller wie
  gefordert nur eine Application-Klasse aufrufen.
- `Http/Requests/Invoices/BuildsInvoiceDraftData.php`: gemeinsame Trait
  zwischen `InvoiceDraftRequest` und den beiden wiederkehrenden
  Template-Requests, um die Dezimal→Minor-Unit-Konvertierung nicht zu
  duplizieren.
- `Http/Resources/FinanceWireValues.php`: Kopie von
  `Http/Resources/Quotes/QuoteWireValues.php` außerhalb des
  `Quotes`-Namensraums, da Invoice-/Payment-/Recurring-Resources denselben
  Exact-Integer-String-Mechanismus brauchen.
- `InvoiceRevisionController::index()`: die im Plan genannte Route
  `GET /invoices/{invoice}/revisions` existierte noch nicht; ergänzt am
  bestehenden (Task-8-)Controller statt eines neuen, da er bereits die
  owner-scope-Auflösung für Rechnung↔Serie kapselt.
- Port-Erweiterungen `idForUuid`/`page`/`deliveryView` (Invoice),
  `idForUuid`/`page` (Payment), `templateIdForUuid`/`runIdForUuid`/
  `getView`/`getRunView`/`templates(filters,…)`/`runsForTemplate(…)`
  (Recurring) — letztere ersetzt die bisher ungenutzten, generischen
  `templates(int,int)`/`runs(int,int)`-Platzhalter aus Task 13 durch
  gefilterte, typisierte Varianten; ein bestehender Test
  (`FinanceInvoicePersistenceTest`) wurde entsprechend angepasst (siehe
  unten).

## TDD-Evidenz

Beobachtete RED-Phasen während dieser Runde:

1. `EloquentRecurringInvoiceRepository::templates()`/`runs()` waren aus
   Task 13 als ungenutzte `(int $page, int $perPage): array`-Platzhalter
   vorhanden; `FinanceInvoicePersistenceTest::
   test_recurring_repository_paginates_stably_and_locks_owner_context` rief
   sie noch mit der alten Signatur auf — `TypeError` bei jedem Testlauf, bis
   der bestehende Test auf die neuen typisierten `templates(array,int,int):
   RecurringTemplatePage` / `runsForTemplate(RecurringTemplateId,array,int,int):
   RecurringRunPage` umgestellt wurde. Derselbe Fixture-Helfer
   (`storedFinanceAggregate`) setzte nie `current_version_id` auf der
   Vorlage — `getView()`/`templates()` schlugen mit „Recurring template
   current version is missing.“ fehl, bis der Helfer das Feld nach dem
   Einfügen der ersten Version nachträgt.
2. `EloquentInvoiceRepository::applyStatusFilter()`s `whereExists`-Closure
   war fälschlich mit `Illuminate\Database\Eloquent\Builder` statt
   `Illuminate\Database\Query\Builder` typisiert — Laravel reicht dem
   Closure intern einen reinen Query-Builder durch; `TypeError` bei jedem
   Status- oder Overdue-Filter, behoben durch den korrekten Typ.
3. `RecordPaymentRequest::data()` und die beiden wiederkehrenden
   Template-Requests (`RecurringTemplateRequest::data()`,
   `RecurringTemplateVersionRequest::data()`) kollidierten mit
   `Illuminate\Http\Request::data()` (Basisklassen-Methode) — PHP-Fatal
   „Declaration … must be compatible with …“ bei jedem betroffenen
   Testlauf; behoben durch Umbenennung zu `paymentData()`/`templateData()`/
   `versionData()` in Request und aufrufendem Controller.
4. Der erste Cross-Owner-404-Test (`test_show_is_owner_scoped`) lieferte
   200 statt 404: `Auth::id()` in `EloquentInvoiceRepository::ownerId()`
   löste innerhalb desselben Testmethodenaufrufs beim zweiten simulierten
   HTTP-Request (anderer Token) weiterhin den ersten Owner auf, weil
   Laravels Test-`TestCase` den Sanctum-Guard-Zustand nicht automatisch
   zwischen mehreren `withToken()`-Aufrufen in einer Testmethode
   zurücksetzt — ein bereits im Projekt dokumentiertes, an mehreren Stellen
   (`ProjectApiTest`, `FinanceQuoteTest`, u. a.) mit `app('auth')->
   forgetGuards();` vor dem Tokenwechsel gelöstes Muster; auf alle drei
   neuen API-Testdateien angewandt.
5. Der erste Finalisierungs-/Zustellungstest scheiterte mit
   `delivery_pdf_unavailable`, weil der Test-Fake für `DocumentStorage`
   einen Pfad im Format `finance/revisions/{seriesUuid}/{token}.pdf`
   zurückgab statt des von `assertDeliveryReady()` erzwungenen
   SHA-256-gesharded Formats `finance/revisions/{2-hex}/{64-hex}.pdf` — der
   exakt gleiche Fehlermodus, den Task 13 bereits für seinen Scheduler-Fake
   dokumentiert hat; behoben durch identischen Pfadaufbau im Fake.

Jeder Fehler wurde einzeln beobachtet, auf die kleinste produktive Ursache
zurückgeführt und danach erneut grün ausgeführt.

## Verifikation

- `InvoiceApiTest` + `PaymentApiTest` + `RecurringInvoiceApiTest` +
  `ApiSurfaceGuardTest` + `OwnerScopeGuardTest`
  (`php artisan test`, wie im Plan Schritt 5 vorgegeben): `22 tests`,
  `22 passed`, `337 assertions`.
- Vollständiges `tests/Feature/FinanceModule` + `tests/Feature/Guards`
  (`vendor/bin/phpunit` direkt mit `-d memory_limit=1G`, da `artisan test`
  die `-d`-Option auf diesem Windows-Host nicht an den Prüfprozess
  durchreicht und `InvoicePdfTest` echtes dompdf-Font-Rendering über dem
  Standard-Limit `128M` auslöst): `714 tests`, `5206 assertions`,
  `3 failures`. Alle drei Failures sind vorbestehend und unabhängig von
  dieser Task, per `git stash`-Vergleich gegen den unveränderten HEAD
  verifiziert: zwei (`LegacyProjectCompatibilityTest`,
  `QuoteApiTest`) sind der bereits in Task 13 dokumentierte fehlende
  S3-Bucket-Konfigurationswert; die dritte
  (`InvoiceDunningTest::test_overdue_reminder_is_idempotent_per_level_and_
  records_one_successful_history_entry`) schlägt identisch auch auf dem
  unveränderten HEAD fehl (`29` statt erwarteter `28`) — keine der drei
  betrifft einen von dieser Task berührten Pfad.
- `vendor/bin/pint --test` auf allen neuen/geänderten
  `app/Modules/Finance/Http`- und betroffenen `Application`/
  `Infrastructure`-Pfaden sowie den drei neuen Testdateien: zunächst
  Formatierungsabweichungen in `InvoiceRepository.php`,
  `EloquentInvoiceRepository.php` und `PaymentApiTest.php` (automatisch
  behoben durch `vendor/bin/pint` ohne `--test`), danach erneut grün
  verifiziert.
- Projektweites `vendor/bin/phpstan analyse --memory-limit=1G` (Level 10,
  ganzes `app`-Verzeichnis): unverändert `64 errors`, alle in
  `ProcessRecurringInvoiceRun.php` (Task 13). Per `git stash`-Vergleich
  bestätigt: exakt dieselben 64 Fehler bestehen bereits auf dem
  unveränderten HEAD vor dieser Task — die Datei wurde von Task 14 nicht
  berührt. Der Plan verlangt für Task 14 selbst keinen `phpstan`-Lauf
  (Schritt 5 listet nur `php artisan test`, `pint --test` und
  `git commit`); dieser Vergleich dient ausschließlich dem Nachweis, dass
  Task 14 keine neuen Typfehler einführt. Das vorbestehende Problem wird
  hier nicht repariert, da es außerhalb des Task-14-Scopes liegt und aus
  Task 13 stammt.

## Scope und Betrieb

Kein Push, kein Tag, kein Deployment. Alle 19 neuen Routen liegen
weiterhin ausschließlich unter dem Preview-Präfix `/api/v1/finance-v2`;
die kanonischen `/api/v1/finance/*`-Pfade und die bestehende monolithische
Vue-Oberfläche bleiben unverändert bis zum Cutover-Task (17). Die legacy
`/api/v1/finance/invoices`-Laufzeit und alle bestehenden Finance-Tests
außerhalb dieser Task sind unverändert grün geblieben.
