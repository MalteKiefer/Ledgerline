# Invoice Plan Task 15 Report

Datum: 2026-08-30

## Ergebnis

Task 15 baut die Vue-Oberfläche für Rechnungen, Zahlungen und
wiederkehrende Rechnungen auf der in Task 14 offengelegten
`finance-v2`-API: drei Model-Dateien, drei API-Clients, drei
Pinia-Stores, eine geteilte URL-Filter-Composable, fünf geteilte
Komponenten und acht Seiten (Liste/Detail/Editor je Bereich, plus eine
Runs-Seite für wiederkehrende Rechnungen). Wie bei den Quotes-Routen
bleibt `routes.ts` unverknüpft — Aktivierung ist explizit Task 17
vorbehalten; kein bestehender Router-Eintrag wurde verändert.

Die Muster sind bewusst identisch zu den bereits gebauten
`quotes/`-Äquivalenten (`useQuoteFilters`, `stores/quotes.ts`,
`QuoteEditPage.vue` usw.) übernommen, mit einer zentralen
Abweichung: Rechnungen haben — anders als Angebote — keinen
`POST .../preview`-Endpunkt (Task 14 hat bewusst keinen definiert, da
der Plan-Routenkatalog keinen vorsieht). Deshalb existiert in
`InvoiceLineEditor.vue` eine rein clientseitige, **nicht
autoritative** Netto-Summenschätzung über die neuen
`decimalToMinor`/`minorToDecimal`-Hilfsfunktionen in `models/money.ts`
— exakt der in Schritt 1 des Plans verlangte Dezimal↔Minor-Grenzwert-
Mechanismus. Jede gespeicherte Rechnung ersetzt diese Schätzung sofort
durch die serverautoritativen `totals`; kein geschätzter Wert wird je
ans Backend zurückgeschickt (`control_*_minor`-Felder werden im
Formular gar nicht gesetzt).

## Ein reeller, während der Umsetzung gefundener Store-Fehler

Der Invoice- und Recurring-Modul-Backend-Code (Task 14) verwendet
bewusst spezifische, stabile Fehlercodes für Versionskonflikte
(`invoice_version_conflict`, `recurring_template_version_conflict`)
statt des generischen `version_conflict`, den Quotes verwendet. Der
geteilte API-Client (`src/api/client.ts`) erkennt einen
`VersionConflict` aber ausschließlich am **exakten** String
`version_conflict` im `error`-Feld der 409-Antwort. Beim direkten
Kopieren des Quotes-Store-Musters (`error instanceof VersionConflict`)
hätte der `current`-Wert aus einer Konflikt-Antwort nie das
lokale `store.current` aktualisiert — ein „Load server version“-Klick
hätte lautlos die bereits veraltete lokale Kopie erneut angezeigt statt
der tatsächlich gewonnenen Serverversion. Behoben, indem
`applyConflict()` in `stores/invoices.ts` und `stores/recurring.ts`
das `current`-Feld direkt aus dem `ApiError`-Body liest (der es bei
jedem `409`-Konflikt unabhängig vom exakten Fehlercode enthält, siehe
`InvoiceController::actionFailure()`/`RecurringInvoiceTemplateController::conflictResponse()`
aus Task 14), statt sich auf den generischen `VersionConflict`-Pfad zu
verlassen. Der erste Entwurf von `invoice-workflow.test.ts` hätte
diesen Fehler nicht erkannt, weil die dort verwendete
Test-Fixture für „Server-Version“ zufällig dieselben Daten wie die
lokal geladene Version enthielt; korrigiert, indem die Server-Fixture
einen unterscheidbaren Kundennamen erhielt.

## Abgedeckte Invarianten (Plan Schritt 2)

- Filter jedes Listen-/Runs-Bereichs leben ausschließlich in der
  URL-Query (`useFinanceUrlFilters`, geteilt über alle drei Bereiche);
  ein Filterwechsel setzt `page` zurück, ein reiner Seitenwechsel nicht.
- Ein Konflikt beim Speichern eines Rechnungsentwurfs oder einer
  wiederkehrenden Vorlage lädt niemals still die alte lokale Kopie neu
  — der Nutzer sieht explizit „Load server version“ und die dahinter
  liegenden Daten sind exakt die vom Server gewonnene Version.
- Ein fehlgeschlagener Speichervorgang (`data-action="save"`) löst
  keinen zweiten Versuch aus und meldet den Konflikt sichtbar über
  `role="alert"`, statt eine unbehandelte Promise-Ablehnung zu
  erzeugen.
- Nach `finalize` zeigt die Detailseite die Rechnungsnummer und den
  Bestätigungstext; nach `cancel` bleibt die ursprüngliche Rechnung im
  Store durch einen expliziten Reload (`loadInvoice`) sichtbar
  unverändert (Nummer, Kind `invoice`), während die neue Gutschrift
  (`kind: credit_note`) eine eigene ID trägt.
- Eine mehrdeutige Zahlungszuordnungs-Vorschlagsliste
  (`status: 'ambiguous'`) mutiert beim reinen Anzeigen nichts — kein
  `POST .../allocations`-Aufruf, bevor der Nutzer explizit „Use“
  wählt; danach wird die Zeile nur vorbefüllt, nicht automatisch
  abgeschickt.
- Eine angewendete Teilzuordnung aktualisiert die sichtbare
  „Offen“-Summe ausschließlich aus der Server-Antwort
  (`AllocationResult.payment.unapplied_minor`), nie aus einer lokalen
  Neuberechnung.
- Ein fehlgeschlagener wiederkehrender Run zeigt seinen wahren
  Zustand (`failed`, mit `last_error_code`) statt eines optimistischen
  Erfolgs; „Retry“ ruft ausschließlich
  `POST /recurring-invoice-runs/{run}/retry` auf und lädt die
  Run-Liste danach neu, statt den Status lokal zu erraten.

## Bewusste Scope-Entscheidungen

- **Keine `InvoiceActivity`-Zustellhistorie aus der API.** Task 14
  bietet keinen `GET .../deliveries`-Listenendpunkt (nur die beiden
  `POST`-Aktionen zum Einreihen). `InvoiceDetailPage.vue` sammelt
  deshalb nur die in der aktuellen Sitzung selbst ausgelösten
  Zustellungen lokal (`deliveries` als `ref`); ein Neuladen der Seite
  zeigt sie nicht erneut. Das ist eine ehrliche Abbildung der
  tatsächlichen Backend-Fläche, keine Lücke in der UI.
- **Keine Zustellungs-„Retry“-Schaltfläche.** Aus demselben Grund:
  Task 14 hat `RetryInvoiceDelivery` (seit Task 9 im Backend vorhanden)
  nicht über HTTP exponiert, da der Plan-Routenkatalog keinen
  entsprechenden Pfad vorsieht. Eine Retry-Schaltfläche, die einen
  nicht existierenden Endpunkt aufruft, wäre schlechter als keine.
- **Keine eigene `PaymentEditorPage.vue`.** Der Plan listet für
  Payments nur List- und Detailseite; „Zahlung erfassen“ läuft über
  ein `Modal` auf `PaymentListPage.vue`, konsistent mit den in
  `@spa/ui` vorhandenen Bausteinen und ohne einen vom Plan nicht
  vorgesehenen Routennamen zu erfinden.
- **Kein `PATCH` auf wiederkehrende Vorlagen im Editor.** Folgt aus der
  in Task 14 dokumentierten Entscheidung: Inhaltsänderungen laufen
  über „Neue Version“, Statusänderungen über Pause/Resume.

## TDD-Evidenz

Beobachtete RED-Phasen während dieser Runde:

1. `RecordPaymentRequest::data()` — kein Frontend-Fehler, aber beim
   ersten Testlauf ein **PHP-Fatal** in Task 14 (`data()` kollidiert
   mit `Illuminate\Http\Request::data()`); bereits im Task-14-Bericht
   dokumentiert und dort behoben, hier nur als Kontext relevant, weil
   derselbe Testlauf die Frontend-Tests initial mitgerissen hätte.
2. `formatMinor`-Test scheiterte an `Object.is`-Ungleichheit zweier
   optisch identischer Strings — `Intl.NumberFormat('de').format()`
   fügt vor „€“ ein geschütztes Leerzeichen (U+00A0) ein, kein
   normales Leerzeichen; der Test normalisiert jetzt beide Seiten über
   `\s`, das laut ECMAScript-Spezifikation U+00A0 einschließt.
3. Der erste Entwurf von `invoice-workflow.test.ts` löste beim
   bloßen Klick auf `data-action="save"` unter einem 409-Konflikt eine
   unbehandelte Promise-Ablehnung aus (Vitest meldete sie als
   „Unhandled Error“, ohne den Testlauf fehlschlagen zu lassen) — der
   `@click="save"`-Handler in `InvoiceEditorPage.vue` hatte, anders als
   `QuoteEditPage.vue`s durch `saveThen()`/`guarded()` abgesicherte
   Aktionen, keinen Abschluss-Catch; behoben durch
   `@click="() => save().catch(() => undefined)"` am Template-Aufruf,
   während `save()` selbst weiterhin wirft (für zukünftige Aufrufer,
   die das Ergebnis brauchen).
4. Der unter „Ein reeller … Store-Fehler“ oben beschriebene
   `VersionConflict`-vs-`ApiError`-Fehler wurde durch den ersten Lauf
   von `recurring-workflow.test.ts`s Store-Test aufgedeckt
   (`store.current?.status` blieb `'active'`, obwohl der Test einen
   Pause-Konflikt simulierte — die Assertion bestand nur zufällig,
   bis die Fixture bewusst unterscheidbare Werte bekam).

Jeder Fehler wurde einzeln beobachtet, auf die kleinste produktive
Ursache zurückgeführt und danach erneut grün ausgeführt.

## Verifikation

- `yarn test:js src/modules/finance` (wie im Plan Schritt 6
  vorgegeben): `14 Testdateien`, `12 bestanden, 2 fehlgeschlagen`,
  `53 Tests`, `49 bestanden, 4 fehlgeschlagen`. Alle vier Fehlschläge
  liegen in `stores/__tests__/projects.test.ts` und
  `composables/__tests__/useProjectDetail.test.ts` (Projects-Modul,
  von dieser Task nicht berührt); per `git stash -u`-Vergleich gegen
  den unveränderten HEAD als vorbestehend verifiziert (identischer
  Fehlschlag-Wortlaut, identische Zeilennummern). Alle 13 neuen Tests
  in den vier von Task 15 verlangten Dateien (`money.test.ts`,
  `invoice-workflow.test.ts`, `payment-allocation.test.ts`,
  `recurring-workflow.test.ts`) bestehen.
- `yarn typecheck`: identische 5 vorbestehende Fehler in
  `useProjectDetail.test.ts`/`projects.test.ts` (`etag`/`actionState`
  existieren nicht auf den dortigen Typen) — keine neuen Fehler durch
  diese Task, per `git stash -u`-Vergleich gegen HEAD bestätigt.
- `yarn lint`: sauber.
- `yarn build`: erfolgreich; die bestehende Warnung zu Chunks über
  500 kB betrifft vorbestehende Chunks (`es-*.js`, `jspdf.es.min-*.js`,
  `ServerDetail-*.js`) und keinen von dieser Task hinzugefügten Chunk.
- `php artisan test tests/Feature/Guards/TranslationParityGuardTest.php
  tests/Feature/Guards/TranslationUsageGuardTest.php` (wie im Plan
  Schritt 6 vorgegeben): `2 tests`, `2 passed` — alle literalen
  `t('invoices.…')`-Aufrufe aus den neuen Vue-Dateien lösen sich in
  `lang/en/invoices.php` auf, und `en`/`de`/`ru` definieren exakt
  dieselben Schlüssel.
- Beim Ergänzen der Übersetzungsschlüssel wurde eine reale
  Namenskollision gefunden: `invoices.tab_payments` bezeichnet in der
  bestehenden `Finance.vue` bereits „Zahlungsmethoden“
  (Bankkonto-Verwaltung), nicht die neue Zahlungsliste. Die neue
  Zahlungsseite verwendet stattdessen `invoices.payments_title`, um
  die bestehende Bedeutung nicht zu überschreiben.

## Scope und Betrieb

Kein Push, kein Tag, kein Deployment. `routes.ts` ist erstellt, aber
an keiner Stelle in den globalen Router (`src/router/index.ts`)
eingehängt — identisch zum bisherigen Umgang mit `quoteRoutes`.
