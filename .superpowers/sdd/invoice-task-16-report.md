# Invoice Plan Task 16 Report

Datum: 2026-08-30

## Ergebnis

Task 16 migriert den historischen Bestand — legacy `invoices` plus die
über `bank_transactions.invoice_id` verknüpften Zahlungen — nach
finance-v2, ohne je eine bereits vergebene Rechnungsnummer, ein bereits
ausgestelltes PDF oder eine GoBD-Korrekturkette zu verändern.

Der zentrale Entwurf dieser Task war eine architektonische Weiche, die
*vor* jeder Codezeile getroffen werden musste: die existierende Live-Pipeline
(`CreateInvoiceDraftFromSource` -> `FinalizeInvoice`) ist für Migration
ungeeignet, weil sie beim Finalisieren immer eine neue laufende Nummer
vergibt, das PDF neu rendert (dompdf) und neue Lagerbewegungen bucht — jedes
davon würde eine bereits verbuchte historische Tatsache verfälschen oder
verdoppeln. Der Entwurfsteil der Pipeline (`createDraftFromSource`) ist
dagegen unverändert wiederverwendbar, weil er nur einen Entwurf anlegt.
Für die Finalisierung wurde deshalb ein eigener, migrationsspezifischer Pfad
`InvoiceRepository::importFinalized()` eingeführt: er ruft dieselbe bereits
geprüfte `preparedFinalization()`-Logik auf (Summen aus den Zeilen
neu berechnen und gegen die vom Aufrufer übergebenen Kontrollsummen
validieren — exakt das Sicherheitsnetz, das auch die Live-Pipeline nutzt),
ersetzt aber die live vergebene Nummer/Jahr/Sequenz durch die vom Aufrufer
mitgegebenen historischen Werte, schreibt die vom Aufrufer mitgegebenen
historischen PDF-Bytes statt neu zu rendern, und bucht keine
Lagerbewegung. Die `DocumentRevisionRecord`-Guarded-Builder-Regel (nur
`status='draft', pdf_path=null, ...` darf eingefügt werden) bleibt dabei
vollständig respektiert — auch `importFinalized()` fügt zunächst einen
Draft-Revision ein und veröffentlicht ihn anschließend über exakt dasselbe
Compare-and-Set-UPDATE wie `PublishDocumentRevision`.

`LegacyInvoiceMapper` übersetzt eine legacy `Invoice`-Zeile in diesen
Vertrag (`InvoiceDraftSource` plus optional `LegacyInvoiceFinalization`) und
schlägt bei jeder Unklarheit fehl-geschlossen fehl (unlesbare Währung,
fehlender Kundenname, unparsbare Zeile, fehlendes/kein-PDF-Blob) statt
etwas zu erfinden — jeder Fehlercode ist stabil und wird im Checkpoint
sichtbar. `LegacyInvoiceMigration` fährt die eigentliche Migration
zweiphasig pro Owner (`originals` zuerst, `cancellations` danach, damit eine
Stornorechnung nie vor der Rechnung ankommt, die sie storniert) in
Chunks von 100 über `ImportLegacyInvoice`. `LegacyPaymentLinkMigration`
verknüpft danach jede `bank_transactions.invoice_id`-Zeile über
`RecordPayment`/`AllocatePayment` — dieselben Application-Commands, die ein
interaktiver Nutzer auch verwenden würde — und schließt abschließend jede
legacy `status='paid'`-Rechnung, deren migrierter offener Betrag nach allen
Bank-Links noch nicht Null ist, mit einer explizit als
`legacy_invoice_paid_marker` gekennzeichneten Restzahlung (nie als
erfundene Banktransaktion getarnt).

`MigrateFinanceInvoiceSlice` (`finance:migrate-invoice-slice`) orchestriert
beides resumierbar über `finance_invoice_migration_checkpoints`: jeder Owner
hat genau eine Checkpoint-Zeile mit Phase/letzter-ID/Status; ein Abbruch
mitten im Lauf setzt beim nächsten Aufruf exakt dort fort, und jede
darunterliegende Schreiboperation ist ohnehin idempotent (Idempotency-Keys
je Zeile), sodass ein Wiederholungslauf niemals dupliziert. Am Ende jedes
Owner-Laufs synchronisiert `syncSequenceCounters()` die
`finance_invoice_sequences`-Zähler auf `MAX(sequence)+1` je Jahr, damit die
live `LockedInvoiceNumberAllocator` nach der Migration nie mit einer
importierten Nummer kollidiert. `InvoiceControlTotals` vergleicht legacy-
und migrierte Summen je Owner (Anzahl, Netto/USt/Brutto in Minor-Units);
`InvoiceCutoverCheck` (`finance:check-invoice-cutover`) ist das für Task 17
maßgebliche Freigabegate: bereit ist ein Owner nur, wenn Checkpoint
`complete` UND Kontrollsummen exakt übereinstimmen.

Ergänzend liest `LegacyInvoiceReadProjection` finance-v2-Rechnungen
schreibfrei zurück in die legacy Snapshot-Form (id, number, status,
customer, net/vat/gross, dates), die noch-legacy Home-/Report-Screens
erwarten — ihre Verdrahtung in diese Controller ist ausdrücklich
Task 17s Aufgabe. `InvoiceBlobSource`s Backup wurde um einen zweiten
archivierten Präfix (`finance/revisions`, zusätzlich zu `invoices/`)
erweitert, über einen neuen, standardmäßig leeren
`additionalPrefixes()`-Erweiterungspunkt auf `DiskArchiveSource` —
rückwärtskompatibel für jede andere existierende Backup-Quelle.

## Abgedeckte Invarianten

- ein `draft`-Status oder eine legacy Rechnung ohne Nummer migriert als
  reiner Entwurf (kein `importFinalized()`-Aufruf);
- eine finalisierte/versendete legacy Rechnung migriert mit exakt
  derselben Nummer, demselben Jahr, derselben Sequenz und denselben
  PDF-Bytes (SHA-256-Gleichheit geprüft) statt neu gerendert zu werden;
  ihr Workflow-Status wird `sent`, wenn die legacy Zeile ein `sent_at`
  (oder einen impliziten Versandzeitpunkt für `status IN (sent, paid)`)
  trägt, sonst `finalized`;
- eine vollständige Banktransaktions-Zahlung schließt die migrierte
  Rechnung auf offenen Betrag Null; eine Teilzahlung lässt den korrekten
  Restbetrag offen — nie über- noch unterallokiert, auch wenn der Link
  rechnerisch mehr deckt, als die Rechnung je offen war;
- eine legacy `status='paid'`-Rechnung ohne (oder mit unzureichenden)
  Bank-Links bekommt genau eine `legacy_invoice_paid_marker`-Zahlung über
  exakt den verbleibenden offenen Betrag; eine bereits durch Bank-Links
  vollständig gedeckte `paid`-Rechnung bekommt keine;
- ein Stornopaar migriert in der richtigen Reihenfolge (Original vor
  Gutschrift) und trägt `cancels_invoice_id` korrekt;
- ein wiederholter Migrationslauf über dieselben Zeilen dupliziert nichts
  — jede zugrundeliegende Schreiboperation ist idempotent, unabhängig vom
  Checkpoint-Zustand;
- `InvoiceControlTotals::compare()` erkennt eine absichtlich verfälschte
  migrierte Summe zuverlässig als Mismatch; bei exakter Übereinstimmung
  meldet sie `ok=true`;
- `InvoiceCutoverCheck::run()` meldet einen Owner erst dann `ready`, wenn
  sowohl der Checkpoint `complete` als auch die Kontrollsummen exakt
  übereinstimmen — ein Owner mit fehlgeschlagenem Checkpoint oder
  abweichenden Summen blockiert die Gesamtfreigabe.

## TDD-Evidenz

Beobachtete RED-Phasen während dieser Runde:

1. der erste Entwurf von `InvoiceControlTotals` enthielt eine
   redundante `whereNotNull('deleted_at')->orWhere(...)`-Klausel unter der
   irrigen Annahme, ein rohes `DB::table()` bräuchte einen expliziten
   Soft-Delete-Bypass; tatsächlich trägt ein `DB::table()`-Aufruf gar
   keinen Eloquent-Global-Scope — die Klausel war überflüssig und sogar
   falsch; vereinfacht auf ein einfaches `where('user_id', $ownerId)`;
2. die Test-Fixtures für die „Teilzahlung“- (200/38/238) und „Storno“-
   (-100/-19/-119) Szenarien scheiterten mit
   `document_totals_mismatch`, weil der `legacyInvoice()`-Testhelfer
   Standard-`lines` verwendete, die nicht zu den überschriebenen
   `net`/`vat`/`gross`-Werten passten; über einen temporär eingefügten
   `Artisan::call()` + `fwrite(STDERR, Artisan::output())` sichtbar
   gemacht (die Befehlsausgabe wird von PHPUnit sonst verschluckt), auf
   fehlende passende `lines`-Overrides zurückgeführt, behoben und der
   Debug-Code wieder entfernt;
3. `BankTransaction.payment_method_id` ist ein Pflicht-Fremdschlüssel;
   der Testhelfer übergab zunächst `null` und scheiterte an der
   FK-Constraint — behoben, indem der Helfer zuerst eine echte
   `PaymentMethod`-Instanz anlegt;
4. „The S3 document storage disk is incomplete“ beim ersten Testlauf —
   das `DocumentStorage`-Binding des Finance-Moduls ist unabhängig vom
   `config('files.disk')`-Fake; behoben, indem `setUp()` einen Fake
   `DocumentStorage` direkt bindet, der zusätzlich auf den gefakten Files-
   Disk schreibt, damit derselbe Test die PDF-Bytes später zurücklesen
   kann.

Jeder Fehler wurde einzeln beobachtet, auf die kleinste produktive Ursache
zurückgeführt und danach erneut grün ausgeführt.

## Verifikation

- `LegacyInvoiceMigrationTest`: `7 tests`, `7 passed`, `41 assertions`.
- Task-16-Verifikationssuite laut Plan (`LegacyInvoiceMigrationTest`,
  `FinanceInvoiceBlobSourceTest`, `LegacyFinanceBaselineTest`,
  `InvoiceVersionPdfTest`, `FinanceReportsTest`, `TaxReportsTest`, mit
  `-d memory_limit=1G` wegen des vorbestehenden dompdf-Speicherbedarfs):
  `35 tests`, `32 passed`, `259 assertions`, `1 failure`, `2 errors`.
  - Die 2 Errors (`FinanceInvoiceBlobSourceTest`, beide `tar failed`)
    sind der bereits aus Task 13/14 bekannte vorbestehende Zustand
    dieser Windows-Umgebung: `tar` ist von PHPs `proc_open` aus nicht
    erreichbar. Verifiziert per `git stash` — die bestehende
    Schwesterdatei `InvoiceBlobSourceTest.php` (ohne jede Task-16-
    Änderung) scheitert identisch am selben `tar`-Problem.
  - Die 1 Failure (`TaxReportsTest::
    test_small_business_flag_persists_via_web_and_api_company`) ist
    ebenfalls vorbestehend und umgebungsbedingt (fehlender
    S3-Bucket-Konfigurationswert lässt `Storage::disk('files')`
    scheitern) — verifiziert per `git stash`: derselbe Test schlägt mit
    exakt derselben Exception fehl, wenn sämtliche Task-16-Änderungen
    entfernt sind. Kein von dieser Task berührter Pfad.
- Fokussiertes Pint (`Infrastructure/Compatibility`, `Console/Commands`,
  `LegacyInvoiceMigrationTest.php`): ein Lauf normalisierte
  `LegacyPaymentLinkMigration.php` automatisch (ungenutzter
  `LogicException`-Import, kleinere Abstand-/Klammer-Regeln),
  anschließend erneut grün verifiziert; `LegacyInvoiceMigrationTest`
  danach erneut `7/7` grün.
- Projektweites `vendor/bin/phpstan analyse --memory-limit=1G` (Level 10,
  ganzes `app`-Verzeichnis) nach umfangreicher Level-10-Nachbesserung an
  allen neuen Dateien dieser Task (typsichere
  `intValue()`/`nullableIntValue()`/`stringValue()`/
  `nullableStringValue()`-Hilfsmethoden statt roher Casts auf `mixed`-
  typisierte `DB::table()`-Spaltenwerte; `JoinClause`-Typannotation an
  jeder Join-Closure; korrigierte `list<int>`-Rückgabetypen;
  Nicht-`\`-präfixierte Imports): exakt dieselben `64` vorbestehenden,
  ausschließlich in `ProcessRecurringInvoiceRun.php` (Task 13)
  konzentrierten Fehler wie vor dieser Task — `0` neue Fehler durch
  Task 16.

## Scope und Betrieb

Zusätzlich zu den im Plan genannten Dateien waren folgende dokumentierte
Erweiterungen nötig:

- `Application/Ports/InvoiceRepository.php` /
  `Infrastructure/Persistence/EloquentInvoiceRepository.php`: neue
  Methode `importFinalized()` — der zentrale architektonische Beitrag
  dieser Task (siehe Ergebnis oben); reine Erweiterung, keine Änderung an
  bestehenden Methoden.
- `Services/Backup/Sources/DiskArchiveSource.php`: neuer, standardmäßig
  leerer Erweiterungspunkt `additionalPrefixes(): array` —
  rückwärtskompatibel für jede andere Backup-Quelle.
- `Services/Backup/Sources/InvoiceBlobSource.php`: nutzt den neuen
  Erweiterungspunkt, um zusätzlich zum legacy `invoices/`-Präfix auch
  `finance/revisions` in einem Tar zu archivieren.

Kein Push, kein Tag, kein Deployment, keine Route/OpenAPI-Änderung, keine
Ausführung der Migration gegen echte Produktionsdaten — dies ist Task 17s
Aufgabe. `LegacyInvoiceReadProjection` wird von dieser Task eingeführt,
aber bewusst noch nirgends verdrahtet (siehe Klassenkommentar); das
Verdrahten in Home-/Report-Controller ist ausdrücklich Task 17
zugeordnet, weil es zusammen mit dem Entfernen der legacy Schreibpfade
geschieht.
