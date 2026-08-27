# Finance 2.0: EÜR-Journal und technische Neustrukturierung

Datum: 2026-08-27
Status: zur fachlichen und technischen Prüfung

## 1. Ziel

Ledgerline erhält ein revisionsnahes EÜR-Finanzsystem, dessen Kern später ohne
Neumodellierung auf doppelte Buchführung erweitert werden kann. Rechnungen,
Belege, Bankumsätze, Angebote, Projekte und Zeiteinträge bleiben als fachliche
Objekte erhalten. Finanzielle Auswertungen lesen jedoch nicht länger direkt aus
diesen heterogenen Quellen, sondern aus einem zentralen Journal.

Die gesamte Umstellung wird lokal fertiggestellt und gemeinsam deployt. Die
Implementierung bleibt trotzdem in kleine, einzeln testbare und commitbare
Schritte zerlegt. Sämtliche vorhandenen Finanzdaten werden migriert. Eine
Migration darf bei nicht erklärbaren Summenabweichungen nicht erfolgreich
abschließen.

## 2. Nicht-Ziele der ersten Ausbaustufe

- keine vollständige Bilanzbuchhaltung
- kein verpflichtender SKR03- oder SKR04-Kontenrahmen
- keine automatische ELSTER-Übermittlung
- keine Bankverbindung über einen externen PSD2-Anbieter im ersten Release
- keine nachträgliche Veränderung finalisierter Journalbuchungen
- kein Deployment von Zwischenständen

Diese Grenzen verhindern nicht die spätere Erweiterung. Insbesondere wird das
Journal bereits als Soll-/Haben-Zeilenmodell angelegt.

## 3. Leitprinzipien

1. Ein finanzieller Geschäftsvorfall besitzt genau eine nachvollziehbare Quelle.
2. Gebuchte Journalzeilen sind unveränderlich. Korrekturen erfolgen durch
   Storno- und Neubuchungen.
3. Geld wird in ganzzahligen Minor Units gespeichert. Fließkommazahlen sind in
   Buchungslogik und Kontrollsummen unzulässig.
4. Jeder Fremdwährungsbetrag speichert Originalbetrag, Originalwährung,
   verwendeten Kurs, Kursdatum und Betrag in Berichtswährung EUR.
5. Jeder Datenzugriff bleibt strikt `user_id`-gebunden.
6. Fachobjekte dürfen nur über Anwendungsservices journalwirksame Zustände
   erreichen. Controller enthalten keine Buchungslogik.
7. Migrationen sind wiederholbar testbar, protokolliert und fail-closed.
8. Auswertungen und Exporte müssen auf denselben gebuchten Daten basieren.

## 4. Zielmodule

### 4.1 Stammdaten

Verantwortet Partner, Zahlungswege, Kategorien, Produkte, Steuercodes und
Kontenzuordnungen. Kategorien bleiben eine nutzerfreundliche Sicht, werden aber
einem Buchungskonto und optional einem Steuercode zugeordnet.

### 4.2 Dokumente

Verantwortet Angebote, Ausgangsrechnungen, Gutschriften und Eingangsbelege sowie
deren Dateien. Eingebettete Belegmetadaten in `bank_transactions.receipts`
werden in das relationale Belegmodell überführt. Danach existiert nur noch ein
kanonischer Belegtyp.

### 4.3 Banking und Abstimmung

Verantwortet Import, Deduplizierung, Zahlungsvorgänge, Zuordnung und
Abstimmungsstatus. CSV und MT940 bleiben zunächst die Importwege. Ein
Adapter-Interface erlaubt später FinTS, PSD2, PayPal oder Stripe.

### 4.4 Journal

Ist die einzige Quelle für EÜR-, Umsatzsteuer-, OPOS-, DATEV- und
Liquiditätsauswertungen. Es akzeptiert ausschließlich validierte
Geschäftsvorfälle und erzeugt atomar ausgeglichene Buchungssätze.

### 4.5 Reporting und Export

Liest nur gebuchte Journalzeilen und dokumentierte Planwerte. Große Exporte
laufen als Hintergrundjobs. Jeder Export besitzt einen unveränderlichen
Batch-Nachweis.

## 5. Datenmodell

### 5.1 `finance_accounts`

- `id`, `user_id`
- `number`, `name`
- `type`: asset, liability, equity, revenue, expense, tax, clearing
- `normal_side`: debit oder credit
- `skr03_number`, `skr04_number` optional
- `active`, `system`, Zeitstempel
- eindeutig pro Nutzer: `number`

Systemkonten werden pro Nutzer aus einer versionierten Vorlage erzeugt. Eigene
Konten sind erlaubt. Verwendete Konten werden archiviert, nicht gelöscht.

### 5.2 `finance_tax_codes`

- stabiler Code und Bezeichnung
- Steuersatz als Basispunkte
- Richtung: input, output, none, reverse_charge
- zugeordnetes Steuerkonto
- DATEV-Steuerschlüssel optional
- Gültigkeitszeitraum

Steuersätze werden nicht ausschließlich aus dem aktuellen Stammdatensatz
abgeleitet. Jede Journalzeile konserviert den verwendeten Satz und Steuerbetrag.

### 5.3 `finance_events`

- `id`, `uuid`, `user_id`
- polymorphe, owner-validierte Quelle: Typ und ID
- `kind`: outgoing_invoice, credit_note, incoming_receipt, bank_payment,
  manual_adjustment, opening_balance, reversal
- `occurred_on`, `service_on`, `description`
- `status`: draft, posted, reversed
- `currency`, `version`
- Verweis auf das ersetzte oder stornierte Event

Ein Unique-Key auf Nutzer, Quelltyp, Quell-ID und Ereignisart verhindert
doppelte Buchungen durch Retries.

### 5.4 `journal_entries`

- `id`, `uuid`, `user_id`, `finance_event_id`
- fortlaufende Journalnummer pro Nutzer und Geschäftsjahr
- `posted_on`, `fiscal_year`, `description`
- `status`: posted oder reversed
- `reversal_of_id` optional
- `content_hash`, `created_at`

Es gibt kein Update und kein Soft Delete für gebuchte Einträge. Datenbank- und
Modellschutz verhindern Mutation. Eine Korrektur erzeugt einen spiegelbildlichen
Eintrag und anschließend einen neuen Geschäftsvorfall.

### 5.5 `journal_lines`

- `journal_entry_id`, `finance_account_id`
- `side`: debit oder credit
- `amount_minor`, `currency`
- `base_amount_minor`, `base_currency` mit EUR als erster Berichtswährung
- `exchange_rate` als Decimal mit ausreichender Präzision, `rate_date`, `rate_source`
- `tax_code_id`, `tax_rate_bps`, `tax_amount_minor`
- `partner_id`, `project_id`, `category_id` optional
- `document_date`, `service_date`, `due_date`, `reference`

Für jeden Eintrag muss die Summe der Soll- und Haben-Zeilen in Basiswährung
identisch sein. Diese Invariante wird im Domain-Service und in Tests erzwungen.

### 5.6 `open_items` und `open_item_allocations`

Offene Posten repräsentieren Forderungen und Verbindlichkeiten. Allokationen
verbinden Zahlungen mit einem oder mehreren Posten und unterstützen Teilzahlung,
Sammelzahlung, Überzahlung, Skonto und Rückzahlung. Der offene Betrag wird aus
unveränderlichen Allokationen berechnet und nicht als frei änderbarer Wert
geführt.

### 5.7 `posting_rules`

Regeln besitzen priorisierte Bedingungen für Betrag, Text, IBAN, Partner,
Zahlungsweg und Rhythmus sowie Aktionen für Konto, Kategorie, Steuer und Partner.
Eine Regel liefert zunächst einen erklärbaren Vorschlag. Automatische Anwendung
wird nur pro Regel explizit aktiviert und bleibt anhand der Regelversion
nachvollziehbar.

### 5.8 `exchange_rates`

Historische Kurse werden je Basiswährung, Zielwährung, Datum und Quelle
gespeichert. Bereits gebuchte Geschäftsvorfälle behalten ihren Kurs, auch wenn
die Kurstabelle später korrigiert wird.

### 5.9 `export_batches`

- Typ, Zeitraum, Formatversion und Erzeugungsstatus
- SHA-256 der Exportdatei
- enthaltene Journalnummern und Kontenrahmen
- Ersteller und Zeitstempel
- optionaler Storno-/Ersatzbatch

Ein erneuter Export desselben Zeitraums ist möglich, aber als neuer Batch
sichtbar. Ein Perioden-Lock kann danach weitere Buchungen sperren.

## 6. Buchungsabläufe

### 6.1 Ausgangsrechnung

Ein Entwurf erzeugt noch keine Buchung. Beim Finalisieren entstehen Event,
Journal und offener Posten atomar in einer Datenbanktransaktion. Zahlungseingänge
erzeugen eigene Events und Allokationen. Eine Rechnung gilt erst als bezahlt,
wenn die Allokationen den offenen Betrag ausgleichen; der Status wird daraus
abgeleitet.

### 6.2 Eingangsbeleg

Upload und OCR erzeugen einen prüfbaren Entwurf. Erst die Bestätigung bucht
Aufwand, Vorsteuer und Verbindlichkeit beziehungsweise direkt das Geldkonto.
Der Originalbeleg bleibt unverändert und über Hash und Journal referenziert.

### 6.3 Bankumsatz

Importierte Umsätze landen zunächst in einer Abstimmungswarteschlange. Eine
Zuordnung zu offenen Posten oder Belegen erzeugt die erforderliche Buchung und
Allokation. Nicht zuordenbare Umsätze können über eine Clearing-Buchung erfasst
und später umgebucht werden.

### 6.4 Storno und Korrektur

Finalisierte Dokumente und Journalbuchungen werden niemals überschrieben. Ein
Storno spiegelt sämtliche Zeilen, schließt oder korrigiert zugehörige offene
Posten und referenziert das Original. Danach kann ein korrigierter Vorgang neu
gebucht werden.

## 7. Migration bestehender Daten

Die Migration läuft in einer eigenen, wiederaufnehmbaren Anwendungskomponente
und nicht als unkontrollierte lange Schema-Migration.

1. Neue Tabellen und Stammdaten anlegen.
2. Pro Nutzer Kategorien auf Konten und Steuercodes abbilden.
3. Relationale sowie eingebettete Belege in ein kanonisches Modell überführen.
4. Finalisierte Rechnungen und Gutschriften chronologisch journalisieren.
5. Bankumsätze und bestehende Zuordnungen übernehmen.
6. Offene Posten und Zahlungsallokationen rekonstruieren.
7. Historische manuelle Einnahmen/Ausgaben und Projektbuchungen übernehmen.
8. Kontrollsummen pro Nutzer, Jahr, Währung und Quelltyp vergleichen.
9. Migrationsbericht dauerhaft speichern.

Vor jeder produktiven Migration wird ein Backup vorausgesetzt. Der Lauf ist pro
Nutzer und Phase idempotent. Bei unbekannten Währungen, widersprüchlichen
Verknüpfungen oder Summenabweichungen stoppt die Aktivierung des neuen Systems.
Fehlerhafte Quellen werden mit stabiler ID und Grund ausgewiesen; es gibt keine
stille Näherung.

## 8. API- und Backend-Struktur

Der bisherige große Finance-Controller wird in Ressourcencontroller und
Anwendungsservices aufgeteilt:

- `Finance/InvoicesController`
- `Finance/ReceiptsController`
- `Finance/BankTransactionsController`
- `Finance/ReconciliationController`
- `Finance/JournalController`
- `Finance/OpenItemsController`
- `Finance/PostingRulesController`
- `Finance/ReportsController`
- `Finance/ExportsController`

Schreibende Controller validieren Requests und rufen genau einen Use-Case auf.
Use-Cases besitzen Transaktionsgrenzen und geben DTOs zurück. Modelle enthalten
Beziehungen und lokale Invarianten, aber keine orchestrierende Geschäftslogik.

Listenendpunkte werden cursor- oder seitenbasiert paginiert. Der bisherige
Komplett-Snapshot wird entfernt. Filter, Sortierung und Zeitraumbegrenzung laufen
in der Datenbank. Alle Mutationen nutzen Versionsprüfung oder idempotente Keys.

## 9. Frontend-Struktur

`Finance.vue` wird durch geroutete Seiten ersetzt:

- Dashboard
- Dokumente
- Bank und Abstimmung
- Journal
- Offene Posten
- Projekte und Zeiten
- Partner
- Produkte
- Auswertungen
- Exporte und Einstellungen

Jede Seite besitzt einen kleinen Store beziehungsweise Query-Layer. Formulare,
Druckvorlagen und Importdialoge werden eigenständige Komponenten. Serverseitige
Pagination, Filter in der URL und einheitliche Fehlerzustände verhindern, dass
der gesamte Finanzbestand beim Start geladen werden muss.

## 10. Reporting, DATEV und Liquidität

EÜR, Umsatzsteuer, Fälligkeiten und Kundenumsätze werden auf das Journal
umgestellt. Jeder Report liefert Herkunft, Zeitraum, Berichtswährung und eine
Kontrollsumme. Fremdwährungswerte ohne gespeicherten Buchungskurs gelten als
Migrationsfehler, nicht als null oder EUR.

Der DATEV-Export enthält Buchungsdatum, Belegfelder, Buchungstext, Betrag,
Soll-/Habenkonto, Steuerschlüssel, Partnerreferenz und Beleglink/-referenz. Eine
Profilkonfiguration bestimmt SKR03, SKR04 oder eigene Zuordnungen. Vor dem Export
prüft ein Validator fehlende Konten, Steuerschlüssel und Belegreferenzen.

Die Liquiditätsplanung kombiniert offene Posten, Fälligkeiten, bestätigte
wiederkehrende Vorgänge und manuelle Planwerte. Prognosen werden strikt von
gebuchten Ist-Zahlen getrennt.

## 11. Sicherheit und Fehlerbehandlung

- Owner-Scope und Fremd-ID-Tests für jede neue Ressource
- Betrags-, Konten- und Partner-IDs werden serverseitig validiert
- Datei- und Exportdownloads bleiben sandboxed und autorisiert
- Imports besitzen Größen-, Zeilen- und Laufzeitgrenzen
- Jobs sind idempotent und speichern eindeutige Fortschrittsmarken
- keine Secrets oder vollständigen Bankdateien in Logs
- Audit-Ereignisse für Buchung, Storno, Periodensperre und Export
- Race-Conditions bei Nummern, Buchung und Allokation werden durch Locks und
  Unique Constraints abgefangen

## 12. Teststrategie und Abnahmekriterien

### Unit-Tests

- centgenaue Geld- und Steuerberechnung
- Soll-/Haben-Ausgleich
- Storno-Inversion
- Regelpriorität und Regelerklärung
- Wechselkurskonservierung
- OPOS-Allokationen einschließlich Teil- und Überzahlung

### Feature- und Integrationstests

- vollständige Lebenszyklen für Rechnung, Beleg, Bankzahlung und Gutschrift
- Autorisierung und Owner-Isolation
- konkurrierende Finalisierung und Zahlung
- DATEV-Validierung und reproduzierbare Exportdateien
- Berichte stimmen mit Journal-Kontrollsummen überein
- paginierte APIs und stabile Filter

### Migrationstests

- anonymisierte Fixtures aller vorhandenen Quellvarianten
- mehrfache Ausführung erzeugt keine Duplikate
- Vorher-/Nachher-Summen pro Nutzer, Jahr, Währung und Quelle
- eingebettete und relationale Belege bleiben erreichbar
- absichtlich inkonsistente Daten stoppen die Aktivierung

### End-to-End-Tests

- Angebot → Rechnung → Zahlung → DATEV
- Eingangsbeleg → OCR-Prüfung → Bankabgleich → EÜR/USt
- Teilzahlung → Mahnung → Restzahlung
- Fremdwährungsbeleg mit historischem Kurs
- Storno und korrigierte Neubuchung

Die Umstellung ist fertig, wenn alle vorhandenen Finanztests und die neuen
Tests bestehen, die Migrationskontrollsummen exakt sind, keine alte
Berichtslogik mehr direkt aus heterogenen Quellen liest und das Frontend keinen
vollständigen Finance-Snapshot mehr benötigt.

## 13. Implementierungsreihenfolge

1. Charakterisierungstests und technische Modulgrenzen
2. Konten, Steuercodes, Events und Journal
3. Migrationspipeline und Kontrollsummen
4. kanonisches Belegmodell, Banking und Abstimmung
5. Regeln und Kategorienzuordnung
6. offene Posten, Teilzahlungen, Skonto und Mahnwesen
7. DATEV-Export und Periodensperren
8. EÜR, Umsatzsteuer und historische Wechselkurse
9. Liquiditätsplanung und Plan-Ist-Berichte
10. Eingangsrechnungs- und SEPA-Workflow
11. Bankadapter-Schnittstelle und erste automatische Anbindung
12. Kundenportal
13. Frontend-Fertigstellung, Lasttests, Migrationstest und Deployment-Runbook

Jede Stufe muss grün sein, bevor die nächste darauf aufbaut. Das Deployment
erfolgt dennoch erst nach Abschluss und gemeinsamer Abnahme aller Stufen.
