# Project Plan Task 9 — Append-only Notes and Queryable Activity

## Ergebnis

Task 9 implementiert strikt typisierte Projekt- und Dokumentnotizen sowie eine owner-validierte, cursor-paginierte Project Activity. Korrekturen erzeugen neue Historienzeilen über `supersedes_note_id`; bestehende Note- und Activity-Zeilen bleiben unverändert.

## Notizen und Korrekturen

- `AppendProjectNoteData` und `AppendDocumentNoteData` erlauben nur `note|decision|call|email|meeting|correction`, Sichtbarkeit `internal|customer` und Bodies mit 1 bis 100.000 Zeichen.
- `correction` verlangt genau eine positive `supersedes_note_id`; alle anderen Typen verbieten sie. Document-Series-UUIDs werden kleingeschrieben und strikt validiert.
- Owner und Actor sind getrennt positive Eingaben. Da das aktuelle Finance-Auth-Modell keine delegierte Benutzerautorität besitzt, erzwingt das Repository derzeit `actorId === ownerId` und behandelt Abweichungen wie eine nicht sichtbare Ressource.
- Project-Note-Append sperrt das owner-validierte Projekt und den optionalen Vorgänger, schreibt Note und `project.note_added` atomar und speichert Mikrosekunden.
- Document-Note-Append sperrt die owner-validierte Series sowie optionale Revision und Vorgängernote. Revision und Vorgänger müssen exakt derselben Owner-Series angehören. Die Project Activity wird nicht dupliziert, weil die Timeline Document-Series-Aktivitäten direkt zusammenführt.
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
- Die erste Seite friert `link_high_water`, `project_high_water` und `document_high_water` ein. Später erzeugte Links oder Activities erscheinen dadurch nicht mitten in einer laufenden Pagination; alle beim Start aktiven oder historischen Links bleiben erhalten.
- Project-, Link-, Series- und Document-Activity-Abfragen besitzen explizite Owner-Joins. Fremde Series-Aktivitäten können nicht über einen Link oder einen manipulierten Cursor sichtbar werden.
- Activity Payloads werden auf eine feste Liste fachlicher Schlüssel und JSON-skalare Werte begrenzt. Passwörter, Secrets, Tokens, Recipient-/SMTP-Daten, Storage-/PDF-/Blob-Pfade, OCR, Dokumenttexte, rohe Error-/Exception-Meldungen und Traces werden nicht ausgegeben. Stabile `error_code`-Werte und Hashes dürfen erhalten bleiben.

## Provider-Hold

`FinanceServiceProvider.php` wurde wegen paralleler Invoice-/Quote-Arbeit weder bearbeitet noch gestaged. Die Tests bauen `EloquentProjectHistoryRepository` und die Commands/Queries direkt. Nach Freigabe ist additiv folgende Bindung erforderlich, ohne bestehende Bindings zu ersetzen:

```php
$this->app->bind(ProjectHistoryRepository::class, EloquentProjectHistoryRepository::class);
```

## TDD und Verifikation

- Erster RED-Lauf: 6 fehlende Task-9-Klassen plus fehlender Document-Note-Guard.
- Note-/Guard-GREEN: 5 Tests, 47 Assertions.
- Activity-RED: beide Merge-/Cursor-Tests scheiterten am gezielten `project_activity_not_implemented`.
- Task-9-GREEN: 8 Tests, 73 Assertions.
- Plan-Fokus `ProjectHistoryTest + DocumentPersistenceTest + DocumentCoreSchemaTest`: **55 Tests, 55 bestanden, 260 Assertions**.
- Task-9-Pint im Check-Modus: **grün**.
- PHPStan auf allen Task-9-Produktionsdateien mit 1 GiB: **0 Fehler**.
- Weitere einzelne Project-Dateien: Application 24/24, Documents 36/37 mit einem opt-in Skip, Persistence 45/47 mit zwei opt-in Skips, Work 15/15 und Quote Target 8/9 mit einem opt-in Skip; zusammen **136 bestanden, 774 Assertions, 4 Skips**.

## Bekannte unabhängige Baseline

- Ein gemeinsamer Lauf des gesamten Projects-Ordners bricht beim Laravel-Application-Reboot ab, weil `phpunit.xml` das Speicherlimit erneut auf 128 MiB setzt; der Abbruch erfolgt beim Laden von `routes/api.php`, nicht in Task-9-Code.
- `LegacyProjectCompatibilityTest` hat den bereits dokumentierten, unabhängigen Gallery/S3-Fehler wegen eines fehlenden Test-Buckets (15/16 bestanden, 133 Assertions).
- `ProjectSchemaTest` enthält zwei bestehende rote SQLite-Erwartungen zur aktiven Document-Link-Eindeutigkeit beziehungsweise zum Detach-Paar (15/17 bestanden, 218 Assertions). Task 9 ändert weder Document-Link-Schema noch Migrationen.

## Scope

Keine Provider-, HTTP-, Route-, OpenAPI-, Quote-Workflow-, Invoice-Workflow- oder Schemaänderung. Fremde parallele Dateien wurden nicht bearbeitet und werden nicht committed. Kein Push, Tag oder Deployment.
