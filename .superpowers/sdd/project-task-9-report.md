# Project Plan Task 9 — Append-only Notes and Queryable Activity

## Ergebnis

Task 9 implementiert strikt typisierte Projekt- und Dokumentnotizen sowie eine owner-validierte, cursor-paginierte Project Activity. Korrekturen erzeugen neue Historienzeilen über `supersedes_note_id`; bestehende Note- und Activity-Zeilen bleiben unverändert.

## Notizen und Korrekturen

- `AppendProjectNoteData` und `AppendDocumentNoteData` erlauben nur `note|decision|call|email|meeting|correction`, Sichtbarkeit `internal|customer` und Bodies mit 1 bis 100.000 Zeichen.
- `correction` verlangt genau eine positive `supersedes_note_id`; alle anderen Typen verbieten sie. Document-Series-UUIDs werden kleingeschrieben und strikt validiert.
- Owner und Actor sind getrennt positive Eingaben. Da das aktuelle Finance-Auth-Modell keine delegierte Benutzerautorität besitzt, erzwingt das Repository derzeit `actorId === ownerId` und behandelt Abweichungen wie eine nicht sichtbare Ressource.
- Project-Note-Append sperrt das owner-validierte Projekt und den optionalen Vorgänger, schreibt Note und `project.note_added` atomar und speichert Mikrosekunden.
- Document-Note-Append sperrt die owner-validierte Series sowie optionale Revision und Vorgängernote. Eine Korrektur muss exakt denselben Owner, dieselbe Series, dieselbe nullable Revision und denselben Autor wie ihr Vorgänger besitzen. Project-Notizkorrekturen verlangen ebenfalls denselben Autor. Die Project Activity wird nicht dupliziert, weil die Timeline Document-Series-Aktivitäten direkt zusammenführt.
- `customer` ist ausschließlich gespeicherte Sichtbarkeitsmetadaten. Beide Sichtbarkeiten bleiben in dieser Version nur für den authentifizierten Owner abfragbar.
- Projekt- und Dokumentnotizen filtern owner-gebunden nach Text, Typ, Sichtbarkeit, Autor und Datum. Die Seitengröße ist auf 1 bis 100 begrenzt; die Reihenfolge lautet `created_at DESC, id DESC`.

## Append-only-Grenzen

- Project Notes und Project Activities behalten `AppendOnlyRecordMutation` mit den stabilen Kinds `project_note` und `project_activity`.
- Document Activities behalten ihren Foundation-Vertrag `PublishedRevisionMutation::activity()`.
- Document Notes verwenden neu `PublishedRevisionMutation::note()` mit der stabilen Meldung `Document notes are append-only.`.
- Normale, quiet, bulk, `updateOrInsert`, `upsert`, delete- und truncate-Pfade werden auf allen vier Record-Typen abgewiesen. Explizite Inserts und Datenbank-Owner-Cascades bleiben möglich.
- `DocumentNoteRecord` typisiert `supersedes_note_id` und stellt die Vorgängerrelation bereit.

## Project Activity

- Die Timeline vereinigt append-only Project Activities mit Document Activities aller aktiven und historisch getrennten `finance_series`-Links.
- Mehrfaches Verknüpfen derselben Series wird vor der Abfrage dedupliziert; Ergebniszeilen werden zusätzlich über `(source_kind, source_id)` dedupliziert.
- Die feste Reihenfolge ist `occurred_at DESC, source_kind ASC, source_id DESC`. Cursor speichern den vollständigen Mikrosekunden-Zeitwert und werden mit dem Application Key signiert sowie an Owner und Project UUID gebunden.
- Die erste Seite materialisiert die exakte sichtbare Mitgliedschaft aus Project Activities und den Activities aller zu diesem Zeitpunkt aktiv oder historisch verknüpften Series in einer einzigen `INSERT ... SELECT ... UNION`-Anweisung. Dadurch bleiben auch bei später committeten kleineren IDs weder neue Activities noch neue Links in einer laufenden Pagination sichtbar.
- Der signierte Cursor enthält nur Snapshot-UUID und den letzten vollständigen Sortiertupel. Snapshot-Seiten lesen ausschließlich die materialisierte Mitgliedschaft. Snapshots laufen nach einer Stunde ab; ein abgelaufener oder bereits bereinigter Snapshot liefert stabil `project_activity_cursor_expired` und startet niemals still eine neue Timeline.
- Vor einer neuen ersten Seite werden höchstens 100 abgelaufene Snapshot-Header geordnet entfernt; die Items folgen per Cascade. Nicht abgelaufene Snapshots bleiben erhalten. Benötigt die erste Seite keinen Fortsetzungscursor, wird ihr eigener kurzlebiger Snapshot sofort wieder entfernt.
- Project-, Link-, Series- und Document-Activity-Abfragen besitzen explizite Owner-Joins. Fremde Series-Aktivitäten können nicht über einen Link oder einen manipulierten Cursor sichtbar werden.
- Activity Payloads werden auf eine feste Liste fachlicher Schlüssel und JSON-skalare Werte begrenzt. Die tabellarischen Vertragsfälle umfassen die aktuellen Project-, Work-, Link-, Revision-, Quote-, Invoice- und Payment-Producer, darunter alte/neue Versionen und Status, Reopen-/Archive-Metadaten, UUID-/Operationsreferenzen, `based_on_revision_id`, Payment-/Batch-/Allocation-IDs, Delivery-Domains sowie stabile Error-Codes und Hashes. Passwörter, Secrets, Tokens, vollständige Empfänger, Storage-/PDF-/Blob-Pfade, OCR, Dokumenttexte, rohe Error-/Exception-Meldungen und Traces werden nicht ausgegeben.

## Provider und Invoice-Audit

- `ProjectHistoryRepository` ist additiv an `EloquentProjectHistoryRepository` gebunden. Ein Container-Vertragstest löst zugleich die bestehenden Project-, Document-, Invoice- und Quote-Ports sowie Commands/Queries auf und schützt deren Bindings vor versehentlichem Ersatz.
- Der Review-Fix für Invoice-Draft-Delete folgt als separater Task-9-Commit nach dem koordinierten Payment-Vorcommit: Nur die normale Invoice-Zeile wird entfernt, Series und Revision bleiben als owner-gebundenes Audit-Aggregat erhalten, die Series wird auf `deleted` tombstoned und `invoice.draft.deleted` append-only ergänzt. Normale Invoice-Abfragen sehen den Tombstone nicht; die erhaltene Activity trägt Owner, Series und Revision explizit.
- `FinanceSeriesDocumentSource` liefert eine tombstoned Series nur noch historisch als `availability=deleted` ohne Capability. Bestehende Links behalten ihren Snapshot und erscheinen mit `current=null`; Catalog-Search blendet die Series aus und neue Attach-Versuche werden abgewiesen.

## Schema

- `finance_project_history_snapshots` bindet UUID, Owner, Project, Erstellungszeit und Mikrosekunden-TTL per Composite-FK an das owner-validierte Project.
- `finance_project_history_snapshot_items` speichert die unveränderliche `(source_kind, source_id, occurred_at)`-Mitgliedschaft, dedupliziert Quellen und besitzt den vollständigen Paging-Index. Owner-Cascade und Down-Reihenfolge entfernen Items vor Headern.

## TDD und Verifikation

- Erster RED-Lauf: 6 fehlende Task-9-Klassen plus fehlender Document-Note-Guard.
- Note-/Guard-GREEN: 5 Tests, 47 Assertions.
- Activity-RED: beide Merge-/Cursor-Tests scheiterten am gezielten `project_activity_not_implemented`.
- Task-9-GREEN: 8 Tests, 73 Assertions.
- Plan-Fokus `ProjectHistoryTest + DocumentPersistenceTest + DocumentCoreSchemaTest`: **55 Tests, 55 bestanden, 260 Assertions**.
- Task-9-Pint im Check-Modus: **grün**.
- PHPStan auf allen Task-9-Produktionsdateien mit 1 GiB: **0 Fehler**.
- Review-Runde 1 RED: fehlendes Provider-Binding, zu breite Korrekturidentität, verworfene fachliche Payload-Felder, late-commit Aufnahme über numerische High-Water-Werte, fehlende Snapshot-Tabelle und gelöschte Invoice-Auditzeilen wurden jeweils durch fokussierte Regressionen bestätigt.
- Review-Runde 1 GREEN: neue Project-History-Regressionen **5/5, 30 Assertions**; Invoice-Delete-Regressions **2/2, 11 Assertions**; gemeinsamer History-/Invoice-Lauf **25/25, 151 Assertions**.
- Review-Runde 2 RED/GREEN: Tombstone-Auflösung scheiterte zunächst mit `available`, die Producer-Tabelle verlor `based_on_revision_id`, und Snapshot-Header/Items blieben ohne Cleanup erhalten. Nach den Fixes bestehen `ProjectHistoryTest + ProjectDocumentsTest` **53/54 mit 327 Assertions und einem opt-in Skip**.
- Review-Runde 2 Abschlusslauf `ProjectHistoryTest + ProjectDocumentsTest + InvoiceDraftApplicationTest + DocumentPersistenceTest`: **96/97 bestanden, 500 Assertions, ein opt-in Skip**. Fokussiertes Pint ist grün; PHPStan meldet **0 Fehler**.
- Weitere einzelne Project-Dateien: Application 24/24, Documents 36/37 mit einem opt-in Skip, Persistence 45/47 mit zwei opt-in Skips, Work 15/15 und Quote Target 8/9 mit einem opt-in Skip; zusammen **136 bestanden, 774 Assertions, 4 Skips**.

## Bekannte unabhängige Baseline

- Ein gemeinsamer Lauf des gesamten Projects-Ordners kann beim Laravel-Application-Reboot abbrechen, weil der aufgerufene CLI/Test-Runner-Prozess auf 128 MiB zurückfällt; der Wert stammt nicht aus `phpunit.xml`. Der Abbruch erfolgt beim Laden von `routes/api.php`, nicht in Task-9-Code.
- `LegacyProjectCompatibilityTest` hat den bereits dokumentierten, unabhängigen Gallery/S3-Fehler wegen eines fehlenden Test-Buckets (15/16 bestanden, 133 Assertions).
- `ProjectSchemaTest` enthält zwei bestehende rote SQLite-Erwartungen zur aktiven Document-Link-Eindeutigkeit beziehungsweise zum Detach-Paar (15/17 bestanden, 218 Assertions). Task 9 ändert weder Document-Link-Schema noch Migrationen.

## Scope

Task 9 ändert ausschließlich die gelisteten History-Komponenten und Tests, die additive Provider-Bindung, die beiden Snapshot-Tabellen sowie den minimalen Invoice-Draft-Delete-/Testpfad. HTTP, Routes, OpenAPI, Quote-Workflow und fremde parallele Dateien werden nicht committed. Kein Push, Tag oder Deployment.
