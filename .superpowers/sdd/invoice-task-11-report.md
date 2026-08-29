# Invoice Plan Task 11 Report

Datum: 2026-08-29

## Ergebnis

Task 11 implementiert Storno als eigenes, nummeriertes und unveränderliches
`credit_note`-Dokument. Das Original wird owner-scoped unter der Reihenfolge
Serie -> Rechnung -> veröffentlichte Revision gesperrt. Unter diesem Lock wird
genau ein dauerhafter Storno-Entwurf über die bestehenden Unique-Relationen
`(user_id, cancels_invoice_id)` und `(user_id, source_type, source_key)` angelegt.
Die anschließende Finalisierung nutzt unverändert `FinalizeInvoice`, damit
Nummer, veröffentlichte Revision, PDF und Lagerbewegungen dieselben atomaren
Grenzen und dieselbe Storage-Kompensation wie normale Rechnungen behalten.

Der Storno-Source-Vertrag enthält den UUID-Key, die veröffentlichte
Original-Revisions-ID und den SHA-256 des kanonischen Original-Snapshots. Mengen
werden als kanonische scale-4-Strings vorzeicheninvertiert; es gibt keine
Float-Arithmetik und keine Übernahme negativer Aggregatspalten. Prozentuale
Rabatte werden erneut auf den negativen Zeilen berechnet, fixe Rabatte werden
vorzeicheninvertiert. Der gemeinsame `DocumentCalculator` akzeptiert dadurch
symmetrische signed discounts nur dann, wenn Rabatt und Gesamtnetto dasselbe
Vorzeichen haben und der Betrag das Gesamtnetto nicht überschreitet.

Ausstellungs- und Fälligkeitsdatum des Gegen-Dokuments sind `Clock`-heute. Ein
fehlgeschlagener Finalisierungsversuch behält nur den eindeutigen, unnummerierten
Storno-Checkpoint. Er kann weder über die generische Draft-API verändert noch
gelöscht werden; Retry finalisiert dieselbe ID. Synchron geworfene Fehler nach
dem PDF-Write löschen das eigene Objekt und rollen Nummer, Revision und
Inventory-Effekt zurück.

## Abgedeckte Invarianten

- rabattierte Rechnung mit gemischten 19-/7-Prozent-Steuern wird centgenau zu
  negativen Zeilen, Netto, Steuer und Brutto gespiegelt;
- eigene Serie, Nummer, Revision, PDF-Pfad und PDF-SHA-256;
- Hardwareverkauf `-2.0000` wird durch die negative Storno-Menge als
  Lagergegenbewegung `+2.0000` ausgeglichen; Service erzeugt keine Bewegung;
- Original-Rechnungsrow, Original-Revision, Original-PDF, Allocation-Batches
  und Allocation-Entries bleiben bytegleich;
- teilweise und vollständig bezahlte Rechnungen bleiben stornierbar; das
  negative offene Gegen-Dokument zeigt den Rückerstattungsbedarf, ohne eine
  Zahlung zu erfinden;
- Entwürfe und Credit Notes werden mit stabilen Codes abgewiesen;
- gleicher Key replayt, ein neuer Transport-Key findet dieselbe Storno-ID, und
  Wiederverwendung eines Keys für ein anderes Original liefert
  `idempotency_conflict`;
- Fremd-Owner-ID liefert 404-Semantik und hinterlässt keinen Checkpoint;
- echter PostgreSQL-Zwei-Prozess-Pfad lässt zwei verschiedene Keys gleichzeitig
  auf dasselbe Original los und verlangt dieselbe Storno-ID sowie genau eine
  Relation/Aktivität.

## TDD-Evidenz

Beobachtete RED-Phasen:

1. fehlende `CancelInvoice`-Klasse;
2. Draft-Storno wurde ohne Guard erfolgreich angelegt;
3. Credit Note konnte ohne Kind-Guard erneut storniert werden;
4. Key-Payloadwechsel lieferte den internen Store-Code statt des stabilen
   Cancellation-Codes;
5. ein nach fehlgeschlagener Finalisierung persistierter Storno-Draft ließ sich
   über `updateDraft` verändern.

Jeder Fehler wurde anschließend mit dem kleinsten produktiven Guard bzw. dem
Cancellation-Boundary behoben und erneut grün ausgeführt.

## Verifikation

- Task11 + Shared Calculator + Legacy Storno/Rabatt:
  `38 tests`, `37 passed`, `247 assertions`, `1 skipped`.
- Der Skip ist ausschließlich der optionale PostgreSQL-Lauf. Er ist sicher und
  erklärt explizit: `FINANCE_TEST_PGSQL_URL` plus `pdo_pgsql` aktivieren den
  isolierten Schema-/Zwei-Prozess-Test.
- Draft-/Source-Vertragsregression: `22 passed`, `112 assertions`.
- Finalization/Payment/Persistence ohne den bekannten Baseline-Widerspruch:
  `106 tests`, `100 passed`, `615 assertions`, `6 optional PG skips`.
- Fokussiertes PHPStan mit `memory_limit=1G`: `0 errors`.
- Fokussiertes Pint: `passed`.
- `git diff --check`: sauber.

Der vollständige breite Lauf reproduziert weiterhin genau einen bereits vor
Task 11 vorhandenen Fehler:
`InvoiceFinalizationTest::test_zero_net_hardware_quantity_is_omitted_after_exact_aggregation`
erwartet die Finalisierung eines Dokuments mit `gross_minor = 0`, während die
freigegebene `InvoiceBalance`-Invariante `invoice_gross_zero` ausdrücklich
ablehnt. Task11 ändert weder `InvoiceBalance` noch diesen `view()`-Pfad; der
Test wurde deshalb nicht außerhalb des genehmigten Scopes umgedeutet.

## Scope und Betrieb

Zusätzlich zu den drei im Plan neu genannten Dateien waren die genehmigten
Shared-Erweiterungen an `InvoiceRepository`, `EloquentInvoiceRepository` und
`DocumentCalculator` erforderlich. Vor den Edits bestätigten beide aktiven
Parallelaufgaben keinen Overlap. `FinanceServiceProvider` brauchte keine
Änderung: Repository, Finalisierung, Clock, Nummerierung, Renderer, Storage und
Inventory sind bereits produktiv gebunden; Laravel kann den konkreten Command
auflösen.

Kein Push, Tag, Deployment, Frontend-, Route-, OpenAPI- oder Migrationseingriff.
Der bestehende unvermeidbare Prozess-Crash genau zwischen externem Objekt-Write
und DB-Commit bleibt wie bei der normalen Finalisierung über die vorhandene
Orphan-Reconciliation abgedeckt; alle synchron beobachtbaren Fehler sind
kompensiert und getestet.
