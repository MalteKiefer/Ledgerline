# Quote Task 7 – Implementierungsbericht

Datum: 2026-08-28

## Status

Task 7 ist vollständig umgesetzt. Veröffentlichte Angebotsrevisionen werden ausschließlich aus dem unveränderlichen kanonischen Snapshot deterministisch als PDF gerendert, unter einem serverseitig erzeugten Capability-Pfad gespeichert und über einen eigentümergebundenen Finance-v2-Endpunkt gestreamt.

## Umsetzung

- Dompdf 3.1 wurde als Produktionsabhängigkeit ergänzt; Composer hat `v3.1.6` sowie ausschließlich dessen direkte/transitive Abhängigkeiten aufgelöst. `composer audit` meldet keine Advisories.
- `BladeDocumentRenderer` akzeptiert nur `document_type=quote`, deaktiviert Remote-Ressourcen, beschränkt den Chroot auf die Quote-View und fixiert Zeitmetadaten sowie Dokument-ID. Derselbe Snapshot erzeugt dadurch byte-identische PDFs.
- `QuotePdfViewModel` validiert und formatiert ausschließlich kanonische Snapshot-Felder mit Integer-Arithmetik. Kundendaten werden durch Blade escaped; es werden keine Client-PDF-Bytes, internen Notizen oder Payment-QR-Daten übernommen.
- `FlysystemDocumentStorage` akzeptiert nur echte `%PDF-`-Bytes und kanonische 256-Bit-Hex-Capabilities. Der Pfad lautet `finance/revisions/{prefix}/{capability}.pdf`; vorhandene Pfade werden nie überschrieben, identische Inhalte werden nicht dedupliziert und Cleanup leitet den Zielpfad ausschließlich aus der Capability ab.
- Die Privatheit folgt dem bestehenden `files.disk`-Vertrag: lokaler privater Root beziehungsweise privater S3/R2-Bucket. Es werden absichtlich keine Objekt-ACLs gesendet, da der konfigurierte R2/S3-kompatible Datenträger ACLs ablehnt.
- Der Download-Endpunkt bindet Quote UUID und Revision explizit an den authentifizierten Owner, berücksichtigt den Soft-Delete-Scope, verlangt eine veröffentlichte Revision derselben Serie, akzeptiert nur Capability-Pfade und prüft `%PDF-` plus SHA-256 vor der Ausgabe. Fehlerhafte oder fremde Zustände werden einheitlich als 404 verborgen.
- Antworten liefern `application/pdf`, `nosniff`, Sandbox-CSP, private immutable Caching, SHA-256-ETag sowie sichere Inline-/Attachment-Disposition. Range-Unterstützung wurde nicht ergänzt, da sie in Task 7 ausdrücklich nur „if plan“ gefordert und im Plan nicht vorgesehen ist.
- Route und OpenAPI-Vertrag wurden additiv ergänzt. Die beiden Port-Bindings wurden nach abgeschlossenem Project-Provider-Zeitfenster in den aktuellen Provider eingefügt; bestehende Project-Bindings blieben erhalten.

## TDD-Nachweise

Die Implementierung erfolgte in beobachteten Rot-Grün-Schritten:

1. Fehlende ViewModel-Klasse -> kanonisches HTML/escaping grün.
2. Fehlender Renderer -> PDF-Ausgabe; der Determinismus-Test deckte danach Dompdfs zufällige Dokument-ID auf -> fixe ID/Metadaten grün.
3. Fehlender Storage-Adapter -> Capability-Pfad, Digest, Kollision und capability-sicheres Cleanup grün.
4. Fehlende Route -> Owner-Streaming, Header, Fremdbesitz, Soft Delete, Serien-Mismatch und Traversal-Schutz grün.
5. Manipulierte Nicht-PDF-Bytes mit passendem Digest wurden zunächst gestreamt -> `%PDF-`-Validierung an Storage- und Streaming-Grenze grün.
6. Produktions-Publish-Pfad: exakt ein Objekt bei Retry; injizierter Activity-/DB-Fehler rollt Status zurück und entfernt das eigene Objekt.

## Verifikation

- Quote-Suite plus Foundation/OpenAPI-Guard mit `FILES_DISK=local`: **115 Tests, 114 bestanden, 684 Assertions, 1 opt-in PostgreSQL-Test übersprungen**.
- Task-7-Quote-PDF-Test: **15 Tests bestanden, 72 Assertions** (im obigen Gesamtlauf enthalten).
- Focused PHPStan Level 10 für alle Task-7-PHP-Dateien einschließlich Provider, Route und Test: **0 Fehler**. Dafür wurde nur die projektweite Einstellung `reportUnmatchedIgnoredErrors` in einer nicht eingecheckten temporären Konfiguration deaktiviert; die Task-Dateien selbst benötigen keine Suppressions.
- Focused Pint `--test`: bestanden.
- API Surface Guard: bestanden.
- `composer validate`: bestanden.
- `composer audit`: keine bekannten Sicherheitslücken.
- `git diff --check`: bestanden.

Der vollständige projektweite PHPStan-Lauf ist aktuell nicht grün wegen drei parallelen, nicht zu Task 7 gehörenden Findings in Project-/Invoice-Dateien (`ReorderWorkItems`, `ListProjectWork`, `EloquentInvoiceRepository`). Diese Dateien wurden nicht verändert.

## Selbstreview / verbleibende Bedenken

- Flysystem bietet portabel keinen atomaren „create if absent“-Primitive. Der Adapter prüft vor dem Schreiben auf eine bestehende Capability und überschreibt sie nicht; die Capability selbst ist jedoch ein serverseitig erzeugter zufälliger 256-Bit-Wert, sodass eine kollidierende Erstschreib-Race praktisch ausgeschlossen ist. Speicherung und nachgelagerte DB-Publikation bleiben durch den Foundation-Compensation-Mechanismus abgesichert.
- Der Stream verifiziert den vollständigen SHA-256-Digest vor dem Senden. Das ist sicher und verhindert einen vertrauenswürdigen ETag für korrupte Bytes, benötigt aber Arbeitsspeicher proportional zur PDF-Größe. Für Angebots-PDFs ist dies vertretbar; ein späteres sehr großes Dokumentformat sollte eine verifizierte Streamingstrategie erhalten.
