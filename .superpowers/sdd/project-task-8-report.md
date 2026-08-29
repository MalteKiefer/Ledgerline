# Project Plan Task 8 — Document Source Catalog and Historical Associations

## Ergebnis

Task 8 ist als owner-validierter, read-only Source Catalog mit append-only Project-Document-Historie umgesetzt. Die Implementierung umfasst die sieben Quellentypen `finance_series`, `legacy_invoice`, `file`, `gallery_photo`, `finance_receipt`, `bank_transaction` und `bank_transaction_receipt`.

## Sicherheits- und Konsistenzregeln

- Jede Auflösung und Suche ist explizit über `user_id` eingeschränkt; fremde und fehlende Quellen werden gleichartig als nicht gefunden behandelt.
- `ProjectDocumentSourceRef` erzwingt typspezifische kanonische Referenzen. Finance-Series benötigen immer eine positive gepinnte Revision; eingebettete Bankbelege benötigen eine echte UUID.
- Snapshots besitzen eine feste Allowlist: Quellentyp/-referenz, Titel, MIME, Größe, SHA-256, Dokumenttyp/-label und Ereigniszeit. Storage-/PDF-Pfade, OCR, Volltext, EXIF/GPS, freie Notizen und rohe Receipt-Daten werden nicht persistiert oder ausgegeben.
- Fähigkeiten enthalten nur Route-Identifier plus skalare, opake Parameter. Gelöschte Quellen erhalten keine Fähigkeit.
- Finance-Series werden gegen Owner, Series und exakt angeforderte Revision aufgelöst. Die Revision wird im Link über `document_series_id` und `pinned_revision_id` verankert.
- Attach sperrt das Projekt, prüft Rolle und Quelle, verhindert aktive Duplikate und schreibt Link plus Activity in einer Transaktion.
- Detach ändert ausschließlich `detached_by`/`detached_at`, schreibt eine Activity und löscht weder Link noch Quelldatensatz. Reattach erzeugt eine neue Linkzeile.
- Idempotenz bindet den Schlüssel an den vollständigen kanonischen Command-Input einschließlich Project, Quelle/Revision, Rolle, Actor und Zeitpunkt. Replay lädt exakt das gespeicherte Link-Ergebnis; Key-Reuse mit anderem Input wird vom Project-Operation-Repository abgewiesen.
- Link-Listen sind owner- und project-begrenzt, sortieren nach `attached_at DESC, id DESC` und zeigen bei gelöschten/fehlenden Quellen weiterhin den historischen Snapshot. Source-Suche ist pro Adapter begrenzt; der Composite Catalog führt deterministisch nach Ereigniszeit, Typ und Referenz zusammen.

## Erforderliche Container-Bindings (bewusst nicht eingetragen)

`FinanceServiceProvider.php` wurde gemäß Auftrag weder geändert noch gestaged. Für die spätere Integration sind erforderlich:

1. `ProjectDocumentRepository::class` auf `EloquentProjectDocumentRepository::class` binden.
2. `ProjectDocumentCatalog::class` als Singleton mit `CompositeProjectDocumentCatalog` registrieren und genau diese sieben Adapter übergeben:
   - `FinanceSeriesDocumentSource`
   - `LegacyInvoiceDocumentSource`
   - `LegacyFileDocumentSource`
   - `LegacyGalleryPhotoDocumentSource`
   - `LegacyFinanceReceiptDocumentSource`
   - `LegacyBankTransactionDocumentSource`
   - `LegacyBankReceiptDocumentSource`
3. `ProjectDocumentSource::class` auf dasselbe `ProjectDocumentCatalog`-Singleton aliasen/binden, damit Commands und Queries denselben vollständigen Katalog erhalten.

## Verifikation

- `ProjectDocumentsTest + FinanceRelationalTest + FilesRelationalTest`: **56 Tests, 362 Assertions, grün**.
- Task-8-Pint im Check-Modus: **grün**.
- PHPStan auf allen Task-8-Produktionsdateien mit 1 GiB: **0 Fehler**.
- Gesamter Project-Testordner: nicht vollständig ausführbar, weil `phpunit.xml` beim Application-Reboot wieder auf 128 MiB begrenzt und bereits beim Laden von `routes/web.php` erschöpft. Ein separater Baseline-Lauf zeigte zusätzlich den unabhängigen bestehenden Legacy-Gallery/S3-Konfigurationsfehler (fehlender Bucket). Der fokussierte Task-8- und relationale Satz ist davon nicht betroffen.

## Scope

Keine Änderungen an Provider, HTTP-Workflow, Quote-Workflow, Migrationen oder Quellmodellen. Fremde parallele Quote/PDF-Dateien im gemeinsamen Worktree wurden nicht bearbeitet und werden nicht committed.
