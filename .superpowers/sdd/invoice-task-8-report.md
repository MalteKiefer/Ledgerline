# Invoice Plan Task 8 – Implementierungsbericht

Datum: 2026-08-29

## Status

Task 8 ist umgesetzt. Unveränderliche Invoice-Snapshots werden über den bereits gehärteten gemeinsamen Quote/Foundation-Renderer byte-deterministisch als PDF erzeugt, über den bestehenden atomaren Capability-Storage gespeichert und ausschließlich durch einen eigentümer- und seriengebundenen Endpunkt ausgegeben. Die Implementierung führt keinen zweiten Storage-Stack ein.

## Umsetzung

- `BladeDocumentRenderer` wählt serverseitig ausschließlich die allowlisteten Dokumenttypen `quote` und `invoice`. Remote-Zugriffe und PHP-Ausführung bleiben deaktiviert, der Chroot umfasst nur die Finance-Views, und feste PDF-Zeitmetadaten plus eine aus dem HTML abgeleitete Dokument-ID erhalten deterministische Bytes.
- `InvoicePdfViewModel` akzeptiert nur Snapshot-Schema 1 und die Invoice-Arten `invoice`/`credit_note`. Es baut die Positionen mit exakter Decimal-/Minor-Unit-Arithmetik neu auf und verlangt strukturell identische Totals, Tax-Breakdowns, Currency-, Discount- und Quantity-Metadaten. Die kanonische Sortierung des Repositorys wird semantisch verglichen, ohne Typen oder Werte zu lockern.
- Die Invoice-Blade-View rendert nur allowlistete Firmen-, Kunden-, Positions-, Steuer- und Zahlungsfelder. Blade escaped alle Benutzerdaten; interne Notizen und externe Logo-URLs gelangen nicht in das Dokument.
- Die produktive Finalisierung verwendet unverändert die gehärteten gemeinsamen Verträge `DocumentRenderer`, `DocumentStorage`, `DocumentStorageWrite`, `FlysystemDocumentStorage` und die atomaren Local-/S3-Object-Stores. Ein Retry gibt dieselbe Revision und denselben Digest zurück und erzeugt kein weiteres Objekt. Ein Fehler nach dem Storage-Write rollt die Datenbank zurück und entfernt ausschließlich die zu Proof, Digest und Generation gehörenden Bytes.
- `OrphanDocumentReconciler` betrachtet nur Finance-Capability-Pfade, die älter als die Grace Period und in `finance_document_revisions` nicht referenziert sind. Lokale Sidecars und S3-Metadaten müssen Cleanup-Proof, SHA-256 und Generation vollständig bestätigen. Das eigentliche Löschen prüft diese Werte erneut; S3 verwendet zusätzlich ETag, Last-Modified und Size als Bedingungen. Falsche Shards und manipulierte Metadaten werden ignoriert.
- `InvoiceRevisionController` bindet Invoice-UUID, Document-Series, Revision und authentifizierten Owner zusammen. Nur veröffentlichte Revisionen mit sicherem Capability-Pfad, `%PDF-`-Signatur und passendem SHA-256 werden gestreamt. Fremde Owner, andere Serien, fehlende/manipulierte Objekte und unsichere Pfade erscheinen einheitlich als 404.
- Die Antwort enthält `application/pdf`, sichere Inline-/Attachment-Disposition, `nosniff`, Sandbox-CSP, privaten immutable Cache, Content-Length und einen SHA-256-ETag. Der Dateiname wird auf ASCII-Bestandteile reduziert und enthält nur die kanonische Dokumentnummer plus Revision.
- `InvoiceRevisionResource` stellt Snapshot, exakte Minor-Unit-Totals, Digest, Veröffentlichungszeit und den autorisierten Stream-Endpunkt bereit, niemals den privaten Storage-Pfad.
- Route und OpenAPI-Vertrag wurden additiv ergänzt.

Die bereits durch Quote Task 7 installierte und gesperrte Dompdf-Version (`dompdf/dompdf` 3.1, Lockfile 3.1.6) wird wiederverwendet; Composer-Dateien mussten nicht erneut geändert werden.

## TDD-Nachweise

Die wesentlichen Rot-Grün-Schritte wurden beobachtet:

1. Der Renderer lehnte Invoice-Snapshots zunächst als Quote-fremd ab; das Invoice-ViewModel fehlte. Danach waren deterministische Invoice-Bytes und escaped, exakte HTML-Werte grün.
2. Stream-Route und `InvoiceRevisionResource` fehlten zunächst. Nach der Implementierung waren Owner-Streaming, Serienbindung, sichere Header, Digest-/MIME-Prüfung und 404-Verbergung grün.
3. Die OpenAPI-Surface-Prüfung meldete den neuen Endpunkt zunächst als undokumentiert; der additive Vertrag behob den Fehler.
4. Der lokale Orphan-Test scheiterte an der fehlenden Reconciliation. Danach entfernte er ausschließlich ein altes, unreferenziertes und generationsgebundenes Objekt.
5. Der S3-Test reproduzierte zuerst die fehlende `ownedBefore`-Implementierung. Nach List/Head/conditional-Delete-Unterstützung war der AWS-SDK-Command-Vertrag grün.
6. Ein weiterer roter Test zeigte, dass ein Capability-Token unter einem falschen S3-Shard gelesen worden wäre. Die strikte Shard/Token-Kopplung verhindert nun bereits den Head-Aufruf.
7. Ein roter Schema-Test bewies, dass Snapshot-Schema 2 zuvor gerendert wurde. Der Renderer lehnt nicht unterstützte Schema-Versionen jetzt geschlossen ab.
8. Der echte Finalisierungsweg deckte auf, dass der Renderer korrekte kanonisch sortierte Repository-Totals reihenfolgeabhängig verglich. Der strukturell kanonische, weiterhin exakt typisierte Vergleich machte Produktionserzeugung, Retry und Digest-Verifikation grün.
9. Ein injizierter Activity-Insert-Fehler beweist mit dem echten lokalen Store, dass Datenbankstatus und Nummer zurückrollen und keine PDF-Datei verbleibt.

## Verifikation

- `InvoicePdfTest`: 12 Tests entdeckt, 11 bestanden, 66 Assertions, 1 optionaler `pdftotext`-Test übersprungen.
- `QuotePdfTest`: 19 Tests bestanden, 84 Assertions; damit bleiben die gemeinsamen Renderer-/Storage-Verträge rückwärtskompatibel.
- `InvoiceFinalizationTest`, `DocumentRevisionApplicationTest` und `ApiSurfaceGuardTest`: 43 Tests entdeckt, 42 bestanden, 286 Assertions, 1 opt-in PostgreSQL-Test übersprungen.
- Gemeinsamer Invoice-/Quote-PDF-Lauf über PHPUnit mit 512 MB: 31 Tests entdeckt, 30 bestanden, 150 Assertions, 1 optionaler `pdftotext`-Test übersprungen. `artisan test` setzt in diesem Projekt den Prozess auf 128 MB zurück; deshalb werden die beiden Dompdf-Suiten entweder getrennt oder direkt über PHPUnit mit dem höheren Testlimit ausgeführt.
- Task-8-Produktionspfade mit PHPStan: 0 Fehler.
- Projektweiter Finance-PHPStan-Lauf: ausschließlich zwei parallele, nicht zu Task 8 gehörende Findings in `ReorderWorkItems` und `ListProjectWork` (`missingType.iterableValue`). Diese Dateien wurden nicht verändert.
- Pint `--test` auf den im Plan genannten Finance-PDF/Persistence/HTTP-Pfaden und dem Invoice-Test: bestanden.
- `composer validate`: bestanden. `composer audit`: keine bekannten Advisories.
- Der API-Surface-Guard und die konkrete Route einschließlich Security-/Throttle-Middleware sind verifiziert.
- `git diff --check`: bestanden.

## Provider-Integration

`FinanceServiceProvider` wurde gemäß Aufgabenabgrenzung nicht verändert oder gestaged.

Bereits vorhanden und für Task 8 ausreichend:

- `DocumentRenderer::class => BladeDocumentRenderer::class`
- `DocumentStorage` als `FlysystemDocumentStorage` mit atomarem Local- oder S3-Store

Für eine geplante Scheduler-/Command-Auflösung des neuen Reconciler-Dienstes muss der Integrationsschritt den aktuell gewählten `AtomicDocumentObjectStore` als eigenen Container-Vertrag verfügbar machen und daraus `OrphanDocumentReconciler` mit der gewünschten Grace Period bauen. Der Reconciler wird in Task 8 bewusst nicht automatisch geplant, weil weder Zeitplan noch Laufumgebung Teil des Tasks sind.

Die schon aus Invoice Task 7 gemeldeten Bindings für `InvoiceRepository`, `InvoiceNumberAllocator` und `InventoryMovementPort` bleiben ebenfalls Voraussetzung für eine containeraufgelöste `FinalizeInvoice`-Ausführung; die Task-8-Tests verwenden explizite Produktionsadapter.

## Verbleibende Bedenken

- `pdftotext` ist auf dem lokalen Windows-Host nicht installiert. Der Testpfad ist vorhanden und läuft automatisch, sobald Poppler verfügbar ist; lokale Sichtbarkeit/escaping wird zusätzlich direkt an der gerenderten allowlisteten HTML-View geprüft.
- Der Stream prüft den vollständigen SHA-256-Digest vor Ausgabe und hält dafür die PDF-Bytes im Speicher. Das ist für Rechnungs-PDFs sicher und vertretbar; sehr große zukünftige Dokumentklassen bräuchten eine verifizierte Streamingstrategie.
- Die S3-Tests laufen gegen das echte AWS-SDK-Command-Modell mit Mock-Transport. Der eingesetzte S3/R2-kompatible Dienst muss die bereits vom gemeinsamen Storage-Vertrag geforderten bedingten Delete-Parameter unterstützen; bei fehlender Unterstützung schlägt Cleanup geschlossen fehl.
- Kein Provider-Change, Push, Tag oder Deployment ist enthalten.
