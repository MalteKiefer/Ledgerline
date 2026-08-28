# Quote Task 8 – Implementierungsbericht

Datum: 2026-08-28

## Status

Task 8 ist vollständig umgesetzt. Angebotszustellungen referenzieren ausschließlich die aktuelle unveränderliche veröffentlichte Revision und deren serverseitig gespeichertes PDF. Der Application-Command veröffentlicht bei Bedarf zuerst, reserviert die Zustellung idempotent und stellt erst nach dem Datenbank-Commit einen Queue-Job ein.

## Umsetzung

- `SendQuoteData` und der frameworkfreie `QuoteMailer`-Port bilden die Application-Grenze. Der Request-Hash bindet Owner/Quote, erwartete Version und normalisierten Empfänger an den Idempotency-Key.
- `SendQuote` validiert Empfänger und Owner-SMTP vor der Veröffentlichung. Bei einem Draft verwendet es den bestehenden atomaren Publish-Flow; bei einem bereits veröffentlichten Angebot bleibt Revision/PDF unverändert.
- Publication, Revision und Delivery werden als Checkpoints der dauerhaften Send-Operation gespeichert. Ein Fehler nach der Veröffentlichung oder nach dem Delivery-Insert kann mit demselben Key exakt dieselbe Revision, PDF-Datei und Zustellung fortsetzen.
- `EloquentQuoteRepository::queueDelivery()` sperrt in der bestehenden Reihenfolge Serie, Quote, Revision und Operation. Es prüft Owner, aktuelle Revision und Published-Status, legt Delivery plus `quote.mail.queued` atomar an und gibt bei Replay dieselbe Delivery zurück.
- Jede Delivery erhält vor der numerischen Datenbank-ID eine dauerhafte UUID. Die RFC-konforme Message-ID ist unmittelbar `<delivery-uuid@quotes.ledgerline>`; UUID und Message-ID bleiben über Dispatch- und Worker-Retries gleich. Eine additive Migration übernimmt bestehende UUID-förmige Message-IDs, erzwingt `NOT NULL` sowie Owner-Unique und bietet `down()`/`up()`.
- `DeliverQuoteRevision` serialisiert nur Owner-ID und Delivery-ID, läuft `afterCommit`, ist pro Delivery eindeutig und verwendet drei Versuche mit Backoff `[60, 300, 900]`. Der Worker lädt Revision, Empfänger und PDF stets serverseitig neu, prüft Current-Revision, `%PDF-` und SHA-256 und sendet exakt diese Bytes.
- Der Worker protokolliert nur Delivery-ID, Empfänger-Domain und stabile Fehlercodes. Empfängeradresse, SMTP-Zugangsdaten und Transportdetails gelangen weder in Activities noch in weitergereichte Exceptions.
- Der Transport-Port liefert eine explizite Annahmebestätigung und unterscheidet typisierte sichere Vorannahmefehler von ungewissen Ergebnissen. Nur nach sicherem Vorannahmefehler wird bis zum dritten Versuch erneut eingeplant; jede unbekannte oder möglicherweise nach Annahme aufgetretene Ausnahme wird sofort `delivery_outcome_uncertain` und niemals automatisch erneut gesendet. Ein neuer Benutzer-Versuch erzeugt eine neue Delivery, aber weder eine neue Revision noch ein neues PDF.
- Ein Prozessabbruch nach Transportannahme hinterlässt `sending`. Ein späterer Job versendet nicht erneut, sondern setzt den expliziten terminalen Zustand `delivery_outcome_uncertain` und schreibt `quote.mail.uncertain`. Damit wird ein möglicher Doppelversand nicht als sicherer Retry ausgegeben.
- `CompanySmtpMailer` liest ausschließlich die Einstellungen des Owners, behält den Egress-Guard und das 15-Sekunden-Limit bei, verwendet pro Send einen zufälligen Laufzeit-Mailer und entfernt Transportkonfiguration sowie Geheimnisse in einem verschachtelten `finally`-Block. Das Cleanup läuft dadurch auch dann vollständig, wenn der Laravel-MailManager beim Purge selbst scheitert.
- Der bisherige Invoice-/Quote-/Reminder-Mailpfad im `FinanceController` delegiert an denselben SMTP-Service; Statuscodes und Erfolgszustände des Legacy-Endpunkts bleiben unverändert.
- `QuoteRevisionMail` setzt die stabile Message-ID und hängt die bereits verifizierten PDF-Bytes im Speicher an. Clientgelieferte Bytes oder Pfade werden nicht akzeptiert.
- Der Finance-Provider bindet ausschließlich den Quote-Mail-Port und den notwendigen austauschbaren Company-Mail-Transport. Die bereits vorhandenen Project-/PDF-/Quote-Bindings wurden erhalten.

## TDD-Nachweise

Die Umsetzung erfolgte in beobachteten Rot-Grün-Schritten:

1. Der erste Send-Test scheiterte an der fehlenden `SendQuote`-Klasse; anschließend wurden Publish-before-queue, Fallback-Empfänger, Owner-Scope, Idempotency und Validation implementiert.
2. Der Worker-Test scheiterte am fehlenden Job-Handler; danach wurden exaktes Attachment, Digest, Message-ID, Success-Activity und minimale Job-Serialisierung umgesetzt.
3. Transporttests scheiterten am fehlenden austauschbaren Transport-Port; die Produktion verwendet nun den Laravel-Adapter, Tests einen deterministischen Fake.
4. Der Drei-Versuche-Test gab zunächst rohe SMTP-Details weiter; danach wurden generische Retry-Fehler, terminale stabile Codes und geheimnisfreie Activities erzwungen.
5. Ein injizierter Fehler nach angenommener Mail reproduzierte den Crash-Zustand. Der Folgelauf markiert die Delivery nun als `uncertain` und sendet nicht erneut.
6. Eine zwischen Queue und Worker ersetzte Revision wurde zunächst als Ausnahme weitergereicht; jetzt wird sie ohne Transportaufruf terminal als stale markiert.
7. Ein Retry nach erfolgreich abgeschlossener Veröffentlichung, aber vor dem Send-Checkpoint scheiterte zunächst mit `version_conflict`. Der Publish-Idempotency-Key wird nun wiederaufgenommen; es entstehen genau eine Revision und eine Delivery.
8. SMTP-Isolationstests verwenden zwei Owner und prüfen getrennte Hosts/Passwörter, einzigartige Laufzeit-Mailer sowie vollständiges Cleanup auch nach Fehlern.
9. Review-Runde 1: Ein angenommener Transport, der anschließend eine ungewisse Ausnahme meldet, wurde zunächst als sicher retrybar behandelt. Der neue Test beobachtete `quote_delivery_failed`; nach der Klassifikation bleibt exakt ein Transportaufruf und die Delivery wird terminal `delivery_outcome_uncertain`.
10. Review-Runde 1: Der UUID-Test las vor der Migration auf SQLite den Bezeichner `uuid` als Stringliteral. Die neue persistierte Delivery-UUID ist vor der numerischen ID bestimmt, Owner-unique und alleinige Quelle der Message-ID.
11. Review-Runde 1: Ein injizierter `MailManager::purge()`-Fehler ließ die dynamische SMTP-Konfiguration samt Secret zunächst im Prozess zurück. Das verschachtelte Cleanup entfernt jetzt beide Config-Zweige, bevor die konservativ ungewisse Ausnahme weitergereicht wird.
12. Review-Runde 1: Ein opt-in PostgreSQL-Test startet zwei echte PHP-Prozesse hinter einer Datenbank-Barriere. Beide senden denselben Owner/Quote/Key; Owner-/Operation-Locks plus der gemeinsame Database-Cache des Unique-Jobs ergeben eine Delivery, einen Queue-Job, eine stabile UUID/Message-ID und bei Ausführung genau einen Transportaufruf.

## Verifikation

- Task-8-Testdatei lokal: **18 Tests, 17 bestanden, 121 Assertions, 1 opt-in PostgreSQL-Test übersprungen**.
- Planmäßige Quote- und Legacy-Mail-Suite (`QuoteDeliveryTest`, `FinanceQuoteTest`, `InvoiceEmailTest`, `InvoiceReminderTest`): **48 Tests, 47 bestanden, 267 Assertions, 1 opt-in PostgreSQL-Test übersprungen**.
- Fokussiertes PHPStan für sämtliche Task-8-Application-, Port-, Mail-, Repository- und Testdateien: **0 Fehler**. Die temporäre Konfiguration zur Deaktivierung ausschließlich unpassender projektweiter Ignore-Erwartungen wurde anschließend gelöscht; es wurden keine Suppressions ergänzt.
- Fokussiertes Pint `--test` für alle Task-8-Dateien einschließlich Provider und Legacy-Controller: bestanden.
- `git diff --check`: bestanden.

## Selbstreview / verbleibende Bedenken

- SMTP bietet keine transaktionale Exactly-once-Bestätigung zusammen mit der lokalen Datenbank. Der bewusst konservative `uncertain`-Zustand verhindert automatische Doppelzustellung, verlangt aber eine menschliche Entscheidung für einen weiteren Send-Versuch.
- `ShouldBeUnique` reduziert doppelte Queue-Ausführung; die Datenbankprüfungen und Zustandsübergänge bleiben trotzdem die maßgebliche Sicherheitsgrenze. Eine parallel beobachtete `sending`-Delivery wird deshalb niemals blind erneut gesendet.
- Empfängeradressen müssen für die tatsächliche Zustellung dauerhaft in der Delivery stehen. Sie werden jedoch weder in Queue-Payloads noch Activities oder Fehlertexte kopiert; Activities enthalten ausschließlich die Domain.
- Der echte Konkurrenztest ist auf PostgreSQL und zwei Prozesse ausgelegt. In der lokalen Umgebung fehlten `pdo_pgsql` und `FINANCE_TEST_PGSQL_URL`; der Pfad ist daher sichtbar als opt-in übersprungen und wird nicht als lokal ausgeführt dargestellt.
