# Finance Module Rewrite Design

Datum: 2026-08-28
Status: zur finalen Prüfung
Zielrelease: 1.785.0

## 1. Ziel und Umfang

Das bestehende Finanzsystem wird als eigenständiges, modular aufgebautes
Subsystem neu entwickelt. Laravel und Vue bleiben die Plattformen, die
fachliche Implementierung wird jedoch aus den monolithischen Controllern,
Stores und Views herausgelöst. Das neue Modul übernimmt Angebote, Rechnungen,
Projekte, Zahlungen, wiederkehrende Rechnungen, Dokumente, Notizen und Imports.

Das Release behebt insbesondere:

- clientseitig autoritative Dokumentbeträge,
- veränderbare finalisierte Rechnungen und PDFs,
- unvollständige Angebotsstatusübergänge,
- Versand trotz fehlgeschlagenem Speichern,
- fehlende echte Angebots- und Rechnungsrevisionen,
- fehlende wiederkehrende Ausgangsrechnungen,
- unvollständige Beziehungen zwischen Angebot, Projekt, Rechnung und Zahlung,
- fehlenden strukturierten Aktivitäts- und Notizverlauf.

Nicht Bestandteil von 1.785.0 sind Lieferscheine, Auftragsbestätigungen,
Anzahlungs-, Abschlags- und Schlussrechnungen sowie ein öffentliches
Kundenportal. Diese Funktionen erhalten eigene spätere Spezifikationen.

Die Neuentwicklung wird lokal vollständig abgeschlossen. Es gibt kein
Zwischendeployment. Nach erfolgreicher Migration, Prüfung und CI wird das
Release `v1.785.0` getaggt; ein Deployment ist nicht beauftragt.

## 2. Modularchitektur

### 2.1 Backend

Das Finance-Modul liegt unter `backend/app/Modules/Finance/`:

```text
Domain/
  Quotes/
  Invoices/
  Projects/
  Payments/
  Recurring/
  Shared/
Application/
  Commands/
  Queries/
  DTOs/
  Services/
Infrastructure/
  Persistence/
  Imports/
  Exports/
  Pdf/
  Mail/
  Scheduling/
Http/
  Controllers/
  Requests/
  Resources/
  Routes/
```

Die Schichten dürfen nur nach innen abhängen:

- `Domain` kennt weder Laravel HTTP noch Vue, Mail, Dateisystem oder konkrete DB.
- `Application` orchestriert Use-Cases und Transaktionsgrenzen über Ports.
- `Infrastructure` implementiert Persistence, Imports, PDF, Mail und Scheduler.
- `Http` validiert, autorisiert, ruft genau einen Use-Case auf und formt Responses.

Ein Controller behandelt genau einen fachlichen Ressourcenbereich. Form
Requests besitzen die HTTP-Validierung, Resources die stabile API-Darstellung.
Geschäftsregeln und Berechnungen stehen niemals in Controllern.

### 2.2 Frontend

Das Frontend liegt unter `frontend/src/modules/finance/`:

```text
api/
models/
stores/
composables/
components/
quotes/
invoices/
projects/
payments/
recurring/
reports/
```

Jeder Bereich erhält geroutete Seiten, einen fokussierten Store beziehungsweise
Query-Layer und eigenständige Formulare. Der bisherige Gesamt-Snapshot wird
durch paginierte, filterbare APIs ersetzt. Filterzustände liegen in der URL.
Die monolithische `Finance.vue` und der alte Finance-Store werden nach dem
Cutover entfernt.

## 3. Gemeinsamer Dokumentkern

### 3.1 Geld und Berechnung

Alle autoritativen Beträge werden serverseitig mit ganzzahligen Minor Units
berechnet. Fließkommazahlen sind in Domain- und Application-Code verboten.

`DocumentCalculator` verarbeitet:

- Menge und Einzelpreis,
- Positionsnetto,
- prozentuale oder absolute Rabatte,
- proportionale Rabattverteilung über Steuersätze,
- Umsatzsteuer je Steuersatz,
- Netto, Steuer und Brutto,
- gezahlte und offene Beträge,
- dokumentierte Rundungsdifferenzen.

Der Client übermittelt Eingaben, aber keine verbindlichen Summen. Optional
übermittelte Kontrollsummen müssen exakt mit dem Serverergebnis übereinstimmen,
anderenfalls antwortet die API mit 422 und einem maschinenlesbaren Fehler.

### 3.2 Dokumentserien und Revisionen

Ein Dokument besitzt eine stabile Serie und eine oder mehrere unveränderliche
Revisionen. Eine Revision speichert:

- fortlaufende Revisionsnummer,
- vollständigen Dokument-Snapshot,
- berechnete Summen,
- Status beim Erzeugen,
- Ersteller und Zeitpunkt,
- Änderungsgrund,
- PDF-Pfad und SHA-256,
- Referenz auf die vorherige Revision.

Versendete Angebotsrevisionen und finalisierte Rechnungsrevisionen sind
unveränderlich. Ein PDF wird niemals unter derselben Revision ersetzt. Ein
erneutes Rendern erzeugt vor Versand eine neue Datei oder nach Versand eine
neue Revision. Korrekturen finalisierter Rechnungen erfolgen über Storno und
Neuausstellung, nicht durch Überschreiben.

### 3.3 Aktivitäten und Notizen

`document_activities` bildet append-only Ereignisse ab: Erstellung, Revision,
Statuswechsel, Versand, Konvertierung, Zahlung, Mahnung, Storno und Fehler.
`document_notes` und `project_notes` speichern Autor, Zeitpunkt, Typ und
Sichtbarkeit `internal` oder `customer`. Bestehende Freitextnotizen werden als
initiale interne Notiz migriert; kundensichtbare Dokumenttexte bleiben Teil der
jeweiligen Revision.

## 4. Angebote

### 4.1 Statusmaschine

```text
draft -> sent -> accepted -> converted
               \-> declined
```

Zulässige Übergänge werden ausschließlich durch `QuoteWorkflow` ausgeführt.
Direkte Statusupdates über generisches CRUD sind unzulässig.

- Nur Entwürfe sind bearbeitbar.
- Versand erzeugt Nummer, Revision und PDF vor dem Mailversand.
- Ein Speicher-, Render- oder Validierungsfehler verhindert den Versand.
- Ein Mailfehler wird als fehlgeschlagene Aktivität gespeichert; die versendbare
  Revision bleibt erhalten und kann idempotent erneut versendet werden.
- Nur die aktuelle, versendete und nicht abgelaufene Revision kann angenommen
  oder abgelehnt werden.
- `accepted` kann nicht nachträglich zu `declined` wechseln.
- Abgelehnte, ersetzte oder abgelaufene Revisionen sind nicht konvertierbar.
- Konvertierung ist idempotent.

### 4.2 Neue Angebotsversion

Eine neue Version entsteht innerhalb derselben Serie. Sie kopiert die letzte
Revision in einen neuen Entwurf, erhält eine Revisionsbeziehung und markiert die
vorherige Fassung nach Versand als ersetzt. Frühere Fassungen bleiben sichtbar,
vergleichbar und mit ihrem ursprünglichen PDF abrufbar. Eine neue Fassung erhält
beim Versand eine nachvollziehbare Dokument-/Revisionsnummer entsprechend der
bestehenden Nummernkonfiguration.

### 4.3 Konvertierung

Ein angenommenes Angebot kann unabhängig, aber nachvollziehbar in ein Projekt
und/oder eine Rechnung überführt werden. Die Quellrevision wird als Snapshot
referenziert. Wiederholte Requests öffnen das bereits erzeugte Ziel und erzeugen
keine Duplikate.

## 5. Projekte

Projekte besitzen direkte relationale Beziehungen zu Quellangeboten,
Rechnungen, Aufgaben, Zeiten, Zahlungen, Belegen, Dateien und Fotos.

- Servicepositionen eines Angebots können Aufgaben mit Schätzstunden erzeugen.
- Hardwarepositionen werden nicht als Aufgaben angelegt.
- Zeiteinträge konservieren den zum Buchungszeitpunkt gültigen Stundensatz.
- Abgerechnete Zeiten sind unveränderlich und eindeutig einer Rechnung
  zugeordnet.
- Mehrere Rechnungen pro Projekt sind zulässig.
- Projektübersichten zeigen Angebotsserie, Rechnungen, offene Zahlungen,
  Dokumente, strukturierte Notizen und Aktivitäten.
- Dateien, Fotos, Belege und Bankvorgänge erscheinen über eine gemeinsame
  Projekt-Dokument-Query, ihre bestehenden owner-scoped Quellen bleiben
  erhalten.

## 6. Rechnungen und Zahlungen

### 6.1 Statusmaschine

```text
draft -> finalized -> sent -> partially_paid -> paid
                   \-> cancelled
```

Finalisierung erzeugt in einer Transaktion:

- gapless Rechnungsnummer nach bestehender Konfiguration,
- serverseitige Summen,
- unveränderlichen Snapshot,
- unveränderliches PDF,
- Dokumentaktivität,
- erforderliche Lagerbewegungen.

Schlägt ein zwingender Schritt fehl, wird nicht finalisiert. Lagerbuchungen sind
idempotent. Eine erneute Finalisierung liefert dasselbe Ergebnis.

### 6.2 Zahlungen

`payments` repräsentiert importierte oder manuelle Zahlungen.
`payment_allocations` ordnet einen Zahlungsbetrag einer oder mehreren Rechnungen
zu. Damit werden Teilzahlung, Sammelzahlung und Überzahlung abgebildet. Der
Rechnungsstatus wird aus Allokationen abgeleitet und nicht frei gesetzt.

Automatische Vorschläge laufen serverseitig und berücksichtigen Nummer,
Referenz, Betrag, Währung und Eindeutigkeit. Die Bestätigung bleibt zunächst
manuell. Eine Allokation ist idempotent und owner-scoped.

### 6.3 Storno

Ein Storno erzeugt ein neues, nummeriertes Gegen-Dokument mit eigener Revision,
PDF und Aktivität. Das Original bleibt vollständig unverändert. Zugehörige
Zahlungsallokationen werden nicht gelöscht; notwendige Rückzahlung oder
Gutschrift wird als eigener Zahlungsvorgang dargestellt.

## 7. Wiederkehrende Rechnungen

`recurring_invoice_templates` enthält Kunde, Positionen, Zahlungsbedingungen,
Intervall, Start, optionales Ende, Zeitzone, nächsten Lauf und Modus.
Unterstützte Intervalle sind monatlich, quartalsweise, halbjährlich und jährlich.

Modi:

- `draft`: erzeugt ausschließlich einen Rechnungsentwurf.
- `auto_send`: erzeugt Entwurf, finalisiert, rendert PDF und versendet per Mail.

Vorlagen sind versioniert. Preis- oder Textänderungen gelten ab einem Stichtag
und verändern keine früheren Läufe. Pausieren verändert keine erzeugten
Rechnungen.

`recurring_invoice_runs` speichert Solltermin, Vorlagenversion, Status,
Rechnungs-ID, Versuche und Fehler. Ein Unique-Key aus Vorlage und Solltermin
verhindert doppelte Rechnungen. Fehlgeschlagene Läufe sind idempotent
wiederholbar. Bei `auto_send` wird ein PDF-/Mailfehler sichtbar protokolliert;
ein Retry setzt am sicheren letzten Zustand fort.

## 8. Imports

Imports implementieren einen gemeinsamen Port:

```text
ImportSource -> Parser -> NormalizedImportRow -> Validator
             -> DuplicateDetector -> Preview -> Application Command
             -> ImportLog
```

Erste Adapter:

- CSV-Bankimport,
- MT940-Bankimport,
- Legacy-Finance-Import.

Adapter schreiben niemals direkt in fachliche Tabellen. Manuelle und
importierte Datensätze durchlaufen dieselben Commands und Invarianten. Jeder
Import besitzt Datei-/Quellhash, Zeilennummern, Vorschau, Bestätigung,
Fehlerprotokoll und Deduplizierung. Größen-, Zeilen- und Laufzeitlimits werden
serverseitig erzwungen. Künftige FinTS-/PSD2-Adapter können denselben Port
implementieren, gehören aber nicht zu diesem Release.

## 9. Datenmigration und Cutover

Die alte Finanzimplementierung bleibt während der lokalen Entwicklung lesbar
und funktionsfähig. Ein versionierter, wiederaufnehmbarer Migrationsprozess
übernimmt pro Nutzer:

1. Partner- und relevante Stammdatenreferenzen,
2. Angebote, Status, Konvertierungen und PDFs,
3. Rechnungen, Gutschriften, alte Versionsdaten und PDFs,
4. Projekte, Aufgaben, Zeiten und manuelle Projektbuchungen,
5. Dateien, Fotos, Belege und Banktransaktionen,
6. Zahlungszuordnungen,
7. vorhandene Notizfelder als strukturierte interne Notizen.

Jede Phase besitzt Fortschrittsmarker und stabile Quell-IDs. Wiederholung
erzeugt keine Duplikate. Die Migration stoppt bei widersprüchlichen Beziehungen,
fehlenden Dateien, unbekannten Währungen oder Summendifferenzen.

Kontrollsummen werden pro Nutzer, Geschäftsjahr, Währung und Quelltyp verglichen:

- Anzahl der Datensätze,
- Netto, Steuer und Brutto,
- offene und gezahlte Beträge,
- Dokumentnummern und Revisionen,
- Datei- und PDF-Hashes,
- Projekt-, Angebots-, Rechnungs- und Zahlungsbeziehungen.

Der neue Router wird erst aktiviert, wenn alle Kontrollen exakt bestehen. Nach
dem Cutover werden alte Controller, Routes, Stores, Views und nicht mehr
benötigte Services entfernt. Historische Schema-Migrationen bleiben erhalten,
damit Updates von alten Installationen weiterhin funktionieren. Obsolete
Tabellen und Spalten werden ausschließlich über neue, reversible beziehungsweise
explizit dokumentierte Migrationsschritte entfernt.

## 10. Sicherheit und Fehlerbehandlung

- Jede Ressource ist über `user_id` oder eine owner-validierte Aggregate-Relation
  isoliert; Fremdzugriff liefert 404.
- IDs in Konvertierungen, Imports und Zuordnungen werden serverseitig gegen den
  Besitzer geprüft.
- Commands akzeptieren Idempotency-Keys für wiederholbare externe Aktionen.
- Nummernvergabe nutzt Transaktionen, Locks und Unique Constraints.
- PDFs werden mit sicherem Pfad, MIME-Prüfung, SHA-256, `nosniff`, Sandbox-CSP
  und autorisiertem Streaming gespeichert.
- Mail-, PDF- und Importfehler erzeugen strukturierte Aktivitäten ohne Secrets
  oder sensible Rohdaten in Logs.
- Statusmaschinen verweigern ungültige Übergänge mit stabilen Fehlercodes.
- Hintergrundjobs besitzen begrenzte Versuche, Backoff und eindeutige
  Fortschrittszustände.

## 11. API und Oberfläche

Die API wird unter `/api/v1/finance/` beibehalten, aber ressourcenorientiert neu
implementiert. Listen sind paginiert und filterbar. Mutationen liefern die
aktuelle Resource einschließlich Versionswert zurück. Generische Updates dürfen
keine Workflowzustände verändern.

Die Oberfläche erhält getrennte Bereiche für:

- Dashboard,
- Angebote und Revisionen,
- Rechnungen und Revisionen,
- Projekte, Zeiten, Dokumente und Notizen,
- Zahlungen und Zuordnung,
- wiederkehrende Rechnungen,
- Imports,
- Berichte.

Jeder asynchrone Ablauf zeigt seinen tatsächlichen Zustand. Ein fehlgeschlagenes
Speichern kann niemals in Versand oder Konvertierung übergehen. Konflikte laden
den aktuellen Stand und verlangen eine bewusste Wiederholung.

## 12. Teststrategie

### Unit

- Minor-Unit-Geldrechnung und Grenzwerte,
- Rabattverteilung und gemischte Steuersätze,
- Angebots- und Rechnungsstatusmaschinen,
- Revisionserzeugung und Snapshot-Hash,
- Intervallberechnung einschließlich Monatsende, Schaltjahr und Zeitzone,
- Zahlungsallokation und Restbetrag,
- Parser-Vertrag für jeden Importadapter.

### Application und API

- jeder Command und jede Query,
- Owner-Isolation und Fremd-ID-Angriffe,
- konkurrierende Nummernvergabe,
- idempotente Konvertierung, Finalisierung, Allokation und Scheduler-Läufe,
- PDF-/Mailfehler und sichere Wiederholung,
- paginierte Filter und stabile Resources.

### Migration

- Fixtures aller historischen Datenformen,
- mehrfache und unterbrochene Ausführung,
- exakte Kontrollsummen,
- Dateihashes und Verknüpfungen,
- absichtlich inkonsistente Quellen stoppen den Cutover.

### Frontend und End-to-End

- Angebot -> Revision -> Annahme -> Projekt,
- Angebot -> Rechnung -> Teilzahlung -> Zahlung,
- Projektzeit -> Rechnung,
- wiederkehrende Vorlage -> Entwurf,
- wiederkehrende Vorlage -> Finalisierung -> PDF -> Mail,
- Versandfehler -> Retry,
- Storno ohne Veränderung des Originals.

Der bestehende Finance-Testbestand bleibt während der Entwicklung grün oder wird
erst in dem Commit ersetzt, der den entsprechenden alten Pfad vollständig und
gleichwertig ablöst.

## 13. Implementierungsreihenfolge

1. Modulbootstrap, Ports und gemeinsamer Geld-/Dokumentkern.
2. Angebote, Statusmaschine und Revisionen.
3. Projekte, Aufgaben, Zeiten, Dokumente und Notizen.
4. Rechnungen, Revisionen, Storno, Zahlungen und Allokationen.
5. Wiederkehrende Vorlagen, Läufe und Scheduler.
6. CSV-, MT940- und Legacy-Importadapter.
7. Bestandsmigration und Kontrollsummen.
8. neue Vue-Finanzoberfläche und paginierte Stores.
9. Cutover, Entfernung des alten Laufzeitcodes und vollständige Regression.
10. Releaseprüfung, Changelog und Version 1.785.0.

Jeder Schritt wird testgetrieben, separat geprüft und nachvollziehbar committed.
Es erfolgt kein Push eines nicht vollständig geprüften Endzustands als Release.

## 14. CI- und Releaseablauf

Vor dem Release müssen bestehen:

- vollständige Backend-Tests,
- vollständige Frontend-Tests,
- TypeScript, ESLint und Formatprüfung,
- PHP-Formatierung und statische Analyse,
- Produktionsbuild,
- Migrationstest mit realistischen Altdaten,
- Sicherheits- und Abhängigkeitsprüfungen,
- finaler Code-Review,
- sauberer Git-Status.

Anschließend:

1. Changelog und Anwendungsversion auf `1.785.0` setzen.
2. finalen Release-Commit erstellen.
3. `develop` zu `origin` pushen.
4. GitHub Tests, Security Scan und Image Build erfolgreich abwarten.
5. Tag `v1.785.0` erstellen und pushen.
6. Tag-Build erfolgreich abwarten.

Bei roter CI wird kein Tag erstellt. Ein veröffentlichter Tag wird niemals
verschoben oder überschrieben; notwendige Korrekturen erhalten eine neue
Patch-Version. Ein Deployment ist nicht Bestandteil dieses Auftrags.

## 15. Abnahmekriterien

Das Release ist fertig, wenn:

- alle autoritativen Beträge serverseitig centgenau berechnet werden,
- keine versendete/finalisierte Revision oder ihr PDF mutiert werden kann,
- alle Statusübergänge ausschließlich über getestete Workflows laufen,
- Angebots- und Rechnungsrevisionen vollständig sichtbar und abrufbar sind,
- Angebot, Projekt, Rechnung, Zahlung, Dokumente und Notizen direkt verbunden
  und owner-sicher abrufbar sind,
- wiederkehrende Rechnungen in beiden Modi idempotent funktionieren,
- CSV und MT940 nur über die neue Importpipeline schreiben,
- alle Bestandsdaten samt Dateien und Beziehungen kontrolliert migriert sind,
- alter Finance-Laufzeitcode entfernt ist,
- alle lokalen Gates und beide CI-Phasen grün sind,
- `v1.785.0` unveränderlich auf dem geprüften Release-Commit liegt.
