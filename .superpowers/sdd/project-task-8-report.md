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

## Review-Runde 1

- `FinanceServiceProvider` bindet `ProjectDocumentRepository` und genau einen geteilten `CompositeProjectDocumentCatalog` mit allen sieben Adaptern; `ProjectDocumentCatalog` und `ProjectDocumentSource` zeigen auf dieselbe Singleton-Instanz. Bestehende Bindings bleiben erhalten.
- Attach/Detach besitzen einen dauerhaften resumierbaren Checkpoint: Falls der Prozess nach Link+Activity, aber vor Operation-Completion ausfällt, findet nur der identische reservierte/fehlgeschlagene Request seine exakte Historienzeile, schließt die Operation ab und gibt denselben Link zurück. Neue Idempotency Keys behalten Duplicate-/Already-detached-Semantik.
- Replay und Detach liefern wieder aufgelöste `current`-Metadaten und korrekte `available|deleted|missing`-Availability; der historische Snapshot bleibt unverändert.
- Linkfilter verwenden explizit `state=active|detached|all`. Auch historische/migrierte Snapshot-JSON-Werte werden beim Lesen strikt auf die öffentliche Allowlist und skalare Werte reduziert.
- UUID-basierte Series- und eingebettete Receipt-Referenzen werden vor Validierung, Hashing und Speicherung kleingeschrieben.
- Der Composite Catalog verwendet einen filtergebundenen k-way Cursor mit unabhängiger Adapterposition statt eines globalen Offsets. Dadurch gibt es keine 100er-Grenze oder Wiederholungen; auch eingebettete Bankbelege streamen über beliebig viele Transaktionen.
- Finance-Series veröffentlichen PDF-Capabilities nur für tatsächlich registrierte Routen. Die Invoice-Route lautet exakt `api.finance-v2.invoices.revisions.pdf`; bei fehlender Route bleiben Capability und Parameter leer.
- Ein opt-in PostgreSQL-Zweiprozess-Test prüft die Serialisierung gleicher und verschiedener Idempotency Keys auf genau einen aktiven Link.

## Review-Runde 2

- Attach- und Detach-Linkzeilen tragen nullable `attached_operation_id` beziehungsweise `detached_operation_id`. Owner-komposite Foreign Keys und Unique Constraints binden jeden Checkpoint an genau eine Operation desselben Owners; einzelne Operationen können dadurch nicht aus der unveränderlichen Link-Historie gelöscht werden, während das Löschen des Owners weiterhin den gesamten Graphen kaskadiert.
- Recovery sucht ausschließlich nach der eigenen Operation-ID. Ein vor der Mutation abgestürzter oder fehlgeschlagener Request kann deshalb keinen später von einem anderen Idempotency Key erzeugten Attach/Detach übernehmen. In-progress bleibt `operation_in_progress`; failed Retries behalten `document_already_attached` beziehungsweise `document_already_detached`.
- Der Embedded-Receipt-Adapter liest pro Aufruf genau einen festen Batch von höchstens 50 Transaktionen und erzeugt Metadaten aus diesem Batch ohne erneute Receipt-Blob-Abfragen. Ein vollständig ausgefilterter Batch darf eine leere Seite mit Fortsetzungscursor liefern; der Composite propagiert diesen Fortschritt, ohne im selben Aufruf weitere Batches zu scannen.
- Catalog-Cursor speichern den vollständigen letzten Sortierschlüssel einschließlich Mikrosekunden, Quellentyp und kanonischer Referenz. Composite und alle sieben Adapter verwenden `occurred_at DESC`, `source_type ASC` und innerhalb eines Typs die natürliche kanonische Referenz aufsteigend (numerische Quell-ID; bei Embedded Receipts zusätzlich UUID; bei Series UUID lexikalisch). Die separate Activity-Timeline-Sortierung wurde nicht verändert.
- Alle DTO-, Snapshot-, Filter- und Idempotenz-Zeitwerte erhalten Mikrosekunden im Format `Y-m-d\\TH:i:s.uP`. Attach und Detach unterscheiden dadurch Requests, die sich nur innerhalb derselben Sekunde unterscheiden.
- Legacy-Invoices klassifizieren PDF nur bei einem nichtleeren `pdf_path`, verwenden für Filter und Sortierung `COALESCE(issue_date, created_at)` und liefern fehlende PDFs als `other`. Finance-Series veröffentlichen PDF-Metadaten und Capability ebenfalls nur bei realem PDF-Objekt. Dateien und Finance-Receipts mit NULL-MIME werden korrekt der Gruppe `other` zugeordnet.
- Die additive Migration besitzt getestete Up-/Down-Pfade sowie Tests für Cross-Owner-Ablehnung, unveränderliche Link-Historie und Owner-Cascade.

## Verifikation

- `ProjectDocumentsTest + FinanceRelationalTest + FilesRelationalTest`: **82 Tests, 81 bestanden, 509 Assertions, grün**; der PostgreSQL-Concurrency-Test ist ohne `FINANCE_TEST_PGSQL_URL` erwartungsgemäß übersprungen.
- Task-8-Pint im Check-Modus: **grün**.
- PHPStan auf allen Task-8-Produktionsdateien mit 1 GiB: **0 Fehler**.
- Gesamter Project-Testordner: nicht vollständig ausführbar, weil `phpunit.xml` beim Application-Reboot wieder auf 128 MiB begrenzt und bereits beim Laden von `routes/web.php` erschöpft. Ein separater Baseline-Lauf zeigte zusätzlich den unabhängigen bestehenden Legacy-Gallery/S3-Konfigurationsfehler (fehlender Bucket). Der fokussierte Task-8- und relationale Satz ist davon nicht betroffen.

## Scope

Keine Änderungen an HTTP-Workflow, Quote-Workflow oder Provider-Bindings in Runde 2. Die einzige neue Schemaänderung ist die additive, portable Checkpoint-Migration; Quellmodelle bleiben unverändert. Fremde parallele Quote/Invoice-/Frontend-Dateien im gemeinsamen Worktree wurden nicht bearbeitet und werden nicht committed.
