import { describe, it, expect } from 'vitest';
import {
  extractTotal, extractDate, extractMerchant, extractNumber,
  extractVatRate, extractVatId, extractCurrency, extractOrderRef, extractEigenbelegType, buildReceiptName, analyzeReceiptText,
} from '../receipt-ocr';

describe('extractTotal', () => {
  it('prefers a labelled gross-total line over the max amount on the page', () => {
    expect(extractTotal('Zwischensumme 8,40\nGesamtbetrag 9,99\n')).toBe(9.99);
  });
  it('ignores a labelled zero (amount due=0 on an already-paid invoice)', () => {
    expect(extractTotal('Total paid 60.00\nAmount due 0.00\n')).toBe(60);
  });
  it('reads an integer amount directly next to €/EUR', () => {
    expect(extractTotal('Total: 45 €')).toBe(45);
  });
  it('falls back to the max amount when nothing is labelled', () => {
    expect(extractTotal('12,90\n3,50\n')).toBe(12.9);
  });
  it('reads a 4-digit total without a thousands separator ("1071,00", not "071,00")', () => {
    expect(extractTotal('Gesamtbetrag 1071,00 EUR')).toBe(1071);
  });
  // Regression: a real Telekom invoice's "Insgesamt verbrauchtes Datenvolumen:
  // 791.004 KB" line was picked as the receipt total (791,00 €) over the real
  // "Rechnungsbetrag 39,85 €" — two compounding causes. (1) The plain decimal
  // alternative in amountsIn() truncated the extra trailing digit of a bare
  // three-group thousands figure with no cents at all ("791.004" -> "791.00").
  // (2) The bare "gesamt" label keyword matched inside "insgesamt" (a common
  // German adverb, "in total", not itself a total-amount label).
  it('does not mistake a KB data-volume reading for the receipt total (real Telekom invoice)', () => {
    const text = 'Summe Betrag   33,49 €\n+19 % USt. auf 33,49 €   6,36 €\nRechnungsbetrag   39,85 €\n'
      + 'Vertraglich vereinbartes Datenvolumen: 26.214.400 KB\nInsgesamt verbrauchtes Datenvolumen: 791.004 KB';
    expect(extractTotal(text)).toBe(39.85);
  });
  // A genuine "Insgesamt: <amount>" TOTAL LABEL (Backblaze's own wording for
  // "Total:") must still be recognised — the fix above must not require a
  // leading word boundary on "gesamt" (which would also reject this legitimate
  // case), since the same substring is used both ways across real invoices.
  it('still recognises a bare "Insgesamt:" total label (Backblaze-style)', () => {
    expect(extractTotal('B2 Cloud Storage   ($2.57)\nInsgesamt: ($2.57)')).toBe(2.57);
  });
  // Regression: a real Hetzner invoice's "Summe" row lists three €-suffixed
  // columns (net/tax/gross) separated by wide table-column padding, e.g.
  // "16,18 €               3,07 €             19,25 €". The bare-integer-
  // after-€ alternative in amountsIn() only rejected a following DECIMAL
  // continuation ([.,]\d), not a following bare digit — so it could bridge
  // across the padding from one column's "€" and swallow just the leading
  // digit of the NEXT column's number ("1" of "19,25"), corrupting the real
  // total into two spurious values ("1" and "9,25") and yielding 9.25 instead
  // of 19.25. Both a bare-digit AND a decimal-separator continuation must be
  // rejected, and the €-adjacency whitespace must stay tightly bounded so it
  // can't leap across a distant, unrelated column at all.
  it('reads a full gross total from a wide multi-column table row without splitting it (real Hetzner invoice)', () => {
    const text = 'Service   Zeitraum   Netto   Steuer   Brutto\n'
      + 'Projekt "RMM"   04/2026   8,49 €   1,61 €                10,10 €\n'
      + 'Summe                          16,18 €               3,07 €             19,25 €';
    expect(extractTotal(text)).toBe(19.25);
  });
  // Regression: a real self-issued Eigenbeleg PDF prints its amount as
  // "150 E u r o" — the currency word letter-spaced by the PDF's own text-layer
  // justification (same artifact class as "W a l d k i r c h" seen on an
  // address line elsewhere). Neither the € symbol nor a compact "eur" word
  // boundary can match that at all, so nothing on the line was ever recognised
  // as an amount — and with no other candidate anywhere in the document, a
  // "26.08.2024" date fragment (misread as a decimal 26.08) won by default.
  it('recognises a letter-spaced spelled-out currency word ("150 E u r o")', () => {
    const text = 'Betrag                                                 150 E u r o\n'
      + 'Neudrossenfeld, 26.08.2024';
    expect(extractTotal(text)).toBe(150);
  });
  // Regression: a real Grover subscription invoice prints its "hero" total on
  // its own line, with the caption label ("ZU ZAHLEN") on the line right after
  // instead of alongside it — the total was never recognised as "labelled" and
  // the pre-discount line-item price (49,90 €) won as the fallback max instead
  // of the real post-discount total (24,80 €).
  it('treats a bare total figure as labelled when the caption sits on the very next line', () => {
    const text = 'Garmin Fenix 7X Pro SOLAR                              1               49,90 €\n'
      + 'NACHLASS (GUTSCHEIN)                                                   -30,00 €\n'
      + 'LIEFERKOSTEN                                                             4,90 €\n'
      + '                                                                        24,80 €\n'
      + 'ZU ZAHLEN\n'
      + '(inkl. 19%, 3,96 € MwSt.)';
    expect(extractTotal(text)).toBe(24.8);
  });
  // Regression: that same next-line-label rule must NOT fire on an unrelated
  // date-range line ("... 26.08.2024 - 25.09.2024", itself misread as two
  // decimal values) just because a "BEZAHLT" status badge happens to appear
  // several blank lines further down — the value line carries real text of
  // its own (a company name, a label word), so it isn't a bare "hero" total
  // figure and must be rejected before the label search even runs.
  it('does not promote an unrelated date-range line just because a distant coincidental label follows', () => {
    const text = 'Germany                                                          Leistungszeitraum 26.08.2024 - 25.09.2024\n'
      + '\n\n\n\n'
      + 'Deine Rechnung                        BEZAHLT\n'
      + '\n'
      + 'Garmin Fenix 7X Pro SOLAR                              1               49,90 €';
    expect(extractTotal(text)).toBe(49.9);
  });
  // Regression: a real self-issued Eigenbeleg's "Betrag" is a bare whole-euro
  // figure with NO thousands separator at all ("3741 Euro") — the `\d{1,3}`
  // cap on the grouped-integer alternative rejects a too-short 3-digit prefix
  // capture ("374", still followed by another digit), and the global regex
  // scan then silently retries starting one character later, where the
  // remaining 3 digits ("741") complete a fully valid — but truncated and
  // WRONG — match on their own.
  it('reads a bare 4+ digit amount without truncating it to its last 3 digits', () => {
    expect(extractTotal('Betrag                                     -\n                                                      3741   Euro')).toBe(3741);
  });
});

describe('extractDate', () => {
  it('parses DD.MM.YYYY', () => { expect(extractDate('Datum: 27.07.2026')).toBe('2026-07-27'); });
  it('parses a German month name', () => { expect(extractDate('27. Juli 2026')).toBe('2026-07-27'); });
  it('parses an English month name', () => { expect(extractDate('July 27, 2026')).toBe('2026-07-27'); });
  it('parses a dash month abbreviation', () => { expect(extractDate('27-MAR-2025')).toBe('2025-03-27'); });
  it('rejects an invalid day/month', () => { expect(extractDate('99.99.2026')).toBe(''); });
  // Regression: a real Telekom invoice's OWN "Datum 11.06.2026" line lost to an
  // earlier, unlabelled SEPA-debit sentence ("Den Betrag von 39,85 € buchen wir
  // am 23.06.2026 ab.") purely because of where each happened to land in the
  // text-extraction order — the old code just took the first date pattern found
  // anywhere. A labelled date must now win regardless of position.
  it('prefers a labelled "Datum" line over an earlier unlabelled date', () => {
    const text = 'Den Betrag von 39,85 € buchen wir am 23.06.2026 ab.\nDatum   11.06.2026\nRechnungsnummer   25 4492 2701 2681';
    expect(extractDate(text)).toBe('2026-06-11');
  });
  // A real Ubiquiti receipt prints "Invoice Date: 2026/06/23" — year-first, with
  // slashes rather than the usual hyphenated ISO form.
  it('parses a labelled year-first date with slash separators', () => {
    expect(extractDate('Invoice No.: EU5675973\nInvoice Date:     2026/06/23')).toBe('2026-06-23');
  });
  // A real Tresorit invoice prints "Invoice Date:  24/Sep/2024" — day, an
  // abbreviated month NAME, and year, all slash-separated (not the usual
  // dot/space/dash separators the day-month-name pattern already handled).
  it('parses a day/month-name/year date with slash separators', () => {
    expect(extractDate('Invoice Date:             24/Sep/2024')).toBe('2024-09-24');
  });
  // Regression: the English full-month-name list explicitly spelled out
  // january/february/march/june/july/october/december but never "may" — the
  // other months are either spelled identically in German (april/august/
  // september/november, already covered there) or have an explicit English
  // entry, but German "mai" and English "may" differ by one letter, so this
  // was a genuine, silent gap. Real Apple/OpenAI/Ageras/xAI invoices all
  // print "30. May 2025" / "Date of issue  May 9, 2025" and lost their date
  // entirely (the match succeeded, but MONTHS['may'] was undefined, so the
  // whole day-month-name branch silently declined to return).
  it('parses the English month "May" (day-first)', () => {
    expect(extractDate('30. May 2025')).toBe('2025-05-30');
  });
  it('parses the English month "May" (month-first, American style)', () => {
    expect(extractDate('Date of issue  May 9, 2025')).toBe('2025-05-09');
  });
});

describe('outgoing invoice PDF regressions', () => {
  it('recognises the structured header and gross total of invoice 2025-3', () => {
    const text = `Kiefer Networks
Rechnungsnummer:                    2025-3
Rechnungsdatum:                     07.03.2025
Hausmeister Service Töws
Vitali Töws
Hochfeldring 85
76549 Hügelsheim
Nettobetrag                 77,71 €
USt. 19,00 %                14,78 €
Gesamtsumme                 92,49 €`;
    const result = analyzeReceiptText(text, ['Kiefer Networks', 'Malte Kiefer']);
    expect(result).toMatchObject({ merchant: 'Hausmeister Service Töws', number: '2025-3', date: '2025-03-07', total: 92.49, vat: '19', currency: 'EUR' });
  });

  it('recognises the structured header and gross total of invoice 2025-6', () => {
    const text = `Kiefer Networks
Rechnungsnummer:                   2025-6
Rechnungsdatum:                    01.08.2025
IntellyTec GmbH
Ingo Radermacher
Grünenborn 1
53797 Lohmar
USt-IdNr.:                         DE 347 51 73 86
Nettobetrag               145,60 €
USt. 19,00 %               27,66 €
Gesamtsumme              173,26 €`;
    const result = analyzeReceiptText(text, ['Kiefer Networks', 'Malte Kiefer']);
    expect(result).toMatchObject({ merchant: 'IntellyTec GmbH', number: '2025-6', date: '2025-08-01', total: 173.26, vat: '19', vatId: 'DE347517386', currency: 'EUR' });
  });
});

describe('extractMerchant', () => {
  it('prefers a company-legal-form letterhead line', () => {
    expect(extractMerchant('Ihre Bestellung\nIntellyTec GmbH\nGrünenborn 1\n53797 Lohmar')).toBe('IntellyTec GmbH');
  });
  it('collapses letter-spaced headings', () => {
    expect(extractMerchant('I n t e l l y T e c GmbH')).toBe('IntellyTec GmbH');
  });
  it('falls back to a known brand', () => { expect(extractMerchant('Thanks for your Amazon order')).toBe('Amazon'); });
  it('excludes the viewer\'s own name/company (merged multi-column letterhead)', () => {
    const text = 'Herrn Hochburgerstr. 4\nMalte Kiefer Telefon: 07666-9379021\nClaudia Faber GmbH\nRechnung';
    expect(extractMerchant(text, ['Malte Kiefer', 'Kiefer Networks'])).toBe('Claudia Faber GmbH');
  });
  // Regression: "ab" (Swedish "Aktiebolag" company suffix) is also an extremely
  // common German word ("...buchen wir am 22.07. ab.") — a real Telekom invoice
  // had that exact sentence hijack the merchant match away from the actual
  // "Telekom Deutschland GmbH" letterhead line a few lines later. Lower/mixed
  // case "ab" mid-sentence must NOT count as a company suffix; only ALL-CAPS
  // "AB" (as real Swedish invoices print it, e.g. "Spotify AB") should.
  it('does not mistake the German word "ab" inside a sentence for a company suffix', () => {
    const text = 'Rechnungsbetrag 42,40 €\nDen Betrag von 42,40 € buchen wir am 22.07.2026 ab.\nTelekom Deutschland GmbH, PF 300464, 53184 Bonn';
    expect(extractMerchant(text)).toBe('Telekom Deutschland GmbH');
  });
  it('still recognises a real Swedish AB company suffix (all-caps)', () => {
    expect(extractMerchant('Danke für Ihre Bestellung\nSpotify AB\nBox 1234')).toBe('Spotify AB');
  });
  // Regression: a real invoice from a sole trader (Andy Hempel/datonga.com, no
  // legal-form suffix on his own letterhead) was misdetected as "PayPal" purely
  // because the document's payment-confirmation sentence mentions the processor,
  // not the issuer — "Der Rechnungsbetrag wurde per PayPal bezahlt." PayPal is a
  // real BRANDS entry (it also issues its own transaction receipts), so a bare
  // full-text scan hijacked onto the payment-method mention instead of the real
  // seller's own letterhead line further up the document.
  it('does not mistake a payment-method mention ("...per PayPal bezahlt") for the merchant', () => {
    const text = 'Rechnung\nAndy Hempel | Anemonenweg 24 | 71672 Marbach am Neckar\nSehr geehrter Kunde,\n'
      + 'Summe Artikel   63,98 €\nDer Rechnungsbetrag wurde   per PayPal   bezahlt. Vielen Dank!\n'
      + 'Kiefer Networks\nMalte Kiefer\nAdalbert-Stifter-Str. 6\n95512 Neudrossenfeld';
    expect(extractMerchant(text, ['Malte Kiefer', 'Kiefer Networks'])).toBe('Andy Hempel');
  });
  // A genuine PayPal-issued receipt (no other seller letterhead anywhere) must
  // still fall back to the brand keyword — the exclusion only removes lines that
  // pair "PayPal" with a settlement verb, not every mention of the word.
  it('still recognises PayPal itself when the document has no unrelated seller letterhead', () => {
    expect(extractMerchant('Ihre PayPal-Transaktion\nBetrag: 12,00 €\nTransaktions-ID: 8AB123')).toBe('PayPal');
  });
  // Regression: extractMerchant's step-3 "first meaningful line" fallback checked
  // the RAW line's length before splitting off the " | address" tail that
  // cleanMerchant() strips — a short company name glued to a long pipe-separated
  // address ("Andy Hempel | Anemonenweg 24 | 71672 Marbach am Neckar", 56 chars)
  // was rejected outright by the pre-clean length cap, leaving merchant empty even
  // once the PayPal brand hijack above is fixed.
  it('cleans a letterhead line before applying the length cap, not after', () => {
    const text = 'Rechnung\nAndy Hempel | Anemonenweg 24 | 71672 Marbach am Neckar\nSehr geehrter Kunde,';
    expect(extractMerchant(text)).toBe('Andy Hempel');
  });
  // Regression: a real FastSpring/Royal Apps receipt's own bare "RECEIPT" heading
  // (no company-suffix line, no known-brand keyword) was picked as the merchant
  // by the "first meaningful line" fallback — "receipt" was missing from the
  // generic-document-word skip list that already excludes "beleg"/"quittung".
  it('does not mistake a bare "RECEIPT" heading for the merchant', () => {
    const text = 'RECEIPT\nOrder ID: ROYALAPPS260608-2052-69131\nOrder Created: 8 June 2026';
    expect(extractMerchant(text)).not.toBe('RECEIPT');
  });
  // Regression: on the same document, once "RECEIPT" is excluded, the next
  // candidate line was "PO Number:" — a form label with an empty value (no PO
  // number on this order), not a company name. A line ending in a bare colon is
  // never a real letterhead.
  it('does not mistake an empty "PO Number:" field for the merchant', () => {
    const text = 'Some Heading\nPO Number:\nMore text here';
    expect(extractMerchant(text)).not.toBe('PO Number:');
  });
  // Regression: a real ente.io invoice left "Ausstellungsdatum" ("date of
  // issue" — a German compound not covered by the generic "date"/"rechnung"
  // skip prefixes) as an unrejected candidate; cleanMerchant's address-tail
  // stripper (meant to cut a trailing housenumber) then ate everything from
  // the first digit onward, leaving the bare label word itself as the
  // returned "merchant". The real name sits on a later two-column line merged
  // with the header word "Rechnungsempfänger" ("invoice recipient").
  it('skips a bare "Ausstellungsdatum" date-label line and finds the real name past it', () => {
    const text = 'Rechnung ente.io\nRechnungsnummer 2CD81DDD-0003\nAusstellungsdatum 1. September 2024\n'
      + 'Fällig am 1. September 2024\nente.io Rechnungsempfänger\nbilling@ente.io Malte Kiefer';
    expect(extractMerchant(text, ['Malte Kiefer'])).toBe('ente.io');
  });
  // Regression: a real Tresorit invoice's customer address line ("Adalbert-
  // Stifter-Str. 6") is merged on one pdftotext-flattened row with an
  // unrelated "Account Number:" field — cleanMerchant's digit-triggered
  // address-tail stripper leaves just the bare street name once the
  // housenumber/account-number tail is cut, and a bare street fragment must
  // never be accepted as a company name on its own.
  it('rejects a bare street-name fragment left over after address-tail stripping', () => {
    const text = 'INVOICE\nMALTE KIEFER\nAdalbert-Stifter-Str. 6                    Account Number:      A01694959\n'
      + 'Neudrossenfeld 95512                       Invoice Date:        24/Sep/2024';
    expect(extractMerchant(text, ['Malte Kiefer'])).not.toMatch(/str\.?$/i);
  });
  // Grover Deutschland GmbH and Tresorit AG both only name themselves in a
  // footer/imprint block, well past both the header letterhead window and the
  // first-meaningful-line fallback scan — real Grover/Tresorit invoices open
  // instead with a generic status header ("Deine Rechnung  BEZAHLT") or the
  // customer's own address, so a dedicated brand keyword is the safe fix
  // (a broader "scan the rest of the document" attempt regressed several
  // already-correct Microsoft/Apple/Amazon brand matches, whose OWN EU
  // legal-entity footer disclaimers sit in that same positional zone).
  it('recognises Grover via its brand keyword when no early letterhead exists', () => {
    const filler = Array.from({ length: 15 }, (_, i) => `Zeile ${i}`).join('\n');
    const text = `Deine Rechnung                        BEZAHLT\nHallo Malte,\n${filler}\nGrover Deutschland GmbH DE355009161`;
    expect(extractMerchant(text, ['Malte Kiefer'])).toBe('Grover');
  });
  it('recognises Tresorit via its brand keyword when no early letterhead exists', () => {
    const filler = Array.from({ length: 15 }, (_, i) => `Zeile ${i}`).join('\n');
    const text = `INVOICE\nMALTE KIEFER\n${filler}\nTresorit AG   Franklinstrasse 27, 8050 Zürich / Switzerland`;
    expect(extractMerchant(text, ['Malte Kiefer'])).toBe('Tresorit');
  });
  // Regression: a real IntellyTec letterhead uses " . " (a bare period, with
  // whitespace on BOTH sides) as a field separator, same role as the already-
  // handled | / • / · characters: "IntellyTec GmbH . Grünenborn 1 . 53797
  // Lohmar". The split must require whitespace on both sides — a genuine
  // abbreviation period ("Str.", "Msg.") only ever has a TRAILING space, never
  // a leading one, so it must not be treated as a separator.
  it('splits a letterhead on a bare "." separator with whitespace on both sides', () => {
    expect(extractMerchant('IntellyTec GmbH . Grünenborn 1 . 53797 Lohmar')).toBe('IntellyTec GmbH');
  });
  // Regression: a real Medium.com receipt merges the seller and the viewer's
  // own name onto ONE pdftotext-flattened two-column row ("A Medium
  // Corporation    Kiefer Networks") — the whole line was previously thrown
  // away entirely because it CONTAINED the own name anywhere, even though the
  // real seller name sits intact before it. Only the own-name tail (and
  // whatever follows it) should be cut, the same way a trailing "customer"/
  // address label is already cut — not the whole line. Also covers the
  // COMPANY_SUFFIX gap that let "Corp" (abbreviated) match but not the full
  // word "Corporation".
  it('keeps the seller name when the own name is merged onto the same two-column line', () => {
    const text = 'From                                            To\n'
      + 'A Medium Corporation                            Kiefer Networks\n'
      + '548 Market St                                   Malte Kiefer';
    expect(extractMerchant(text, ['Kiefer Networks', 'Malte Kiefer'])).toBe('A Medium Corporation');
  });
});

describe('extractNumber', () => {
  it('reads a labelled invoice number', () => { expect(extractNumber('Rechnungsnr.: R-00123')).toBe('R-00123'); });
  it('rejects a numeric date mistaken for a number', () => { expect(extractNumber('Rechnungsnr. 27.07.2026')).toBe(''); });
  it('joins a space-grouped reference number onto one line (Telekom-style)', () => {
    expect(extractNumber('Rechnungsnummer                          25 5828 2901 2681')).toBe('25582829012681');
  });
  it('ignores a payment-instruction sentence merely mentioning the label, and finds the real one below it (netcup)', () => {
    const text = 'Wichtig: Bitte nutzen Sie Ihre Rechnungs-Nr.\nDE-95512 Neudrossenfeld\n'
      + 'als Verwendungszweck für Ihre Überweisung!\nDatum                     22.07.2026\n'
      + 'Kunden-Nr.                     95788\nRechnungs-Nr.             nc-5384423\nSeite                              1';
    expect(extractNumber(text)).toBe('nc-5384423');
  });
  it('reads a label sharing a line with unrelated text before it (two-column merge, fonial-style)', () => {
    expect(extractNumber('Kiefer Networks   Rechnungsnummer:   2026061702224')).toBe('2026061702224');
  });
  it('never crosses a newline from the label into the next, unrelated line', () => {
    expect(extractNumber('Rechnungsnr.:\nUSt-IdNr. DE123456789')).toBe('');
  });
  it('reads a bare "Rechnung <code>" in a dunning-letter sentence, no -nr/-nummer suffix (real netcup reminder text)', () => {
    const text = 'Karlsruhe, den 10.07.2026\r\n1. Mahnung zu Ihrer Rechnung nc-5287300\r\nGuten Tag Malte Kiefer,\r\n'
      + 'vielen Dank für Ihr Vertrauen in unsere Dienste. Leider konnten wir bislang keinen Zahlungseingang zu\r\n'
      + 'unserer Rechnung nc-5287300 vom 22.06.2026 über den Betrag von 35,37 EUR feststellen.\r\n'
      + 'R.-Nr.   R.-Datum   R.-Betrag\r\nnc-5287300   22.06.2026   35,37 EUR\r\nZwischensumme   35,37 EUR';
    expect(extractNumber(text)).toBe('nc-5287300');
  });
  it('rejects a bare year mistaken for an ID after a bare "Rechnung" ("Rechnung 2026" is not a number)', () => {
    expect(extractNumber('Bitte prüfen Sie Ihre Rechnung 2026 sorgfältig.')).toBe('');
  });
  it('reconstructs a value from a two-column label-block/value-block layout (real netcup invoice text, label and value on separate lines)', () => {
    const text = 'Wichtig: Bitte nutzen Sie Ihre Rechnungs-Nr.\r\nals Verwendungszweck für Ihre Überweisung!\r\n'
      + 'Datum\r\nKunden-Nr.\r\nRechnungs-Nr.\r\nSeite\r\n22.06.2026\r\n95788\r\nnc-5287300\r\n1\r\nIhre Rechnung';
    expect(extractNumber(text)).toBe('nc-5287300');
  });
  it('reconstructs a value with an underscore from a two-column label-block/value-block layout (real Backblaze invoice text)', () => {
    const text = 'Zahlung Datum\r\nE-Mail-Adresse\r\nZahlung\r\nUnternehmen\r\nRechnung\r\nMwSt\r\nAnderes\r\n'
      + '2026-02-05 UTC\r\nmalte.kiefer@kiefer-networks.de\r\nKreditkarte mit den Endziffern 1548\r\nKiefer Networks\r\n'
      + '021abe1f7af3_158\r\nDE 30 43 23 922\r\nAdalbert-Stifter-Str.   6\r\n95512   Neudrossenfeld';
    expect(extractNumber(text)).toBe('021abe1f7af3_158');
  });
  it('reconstructs a value from a two-column block layout with a generic "Invoice" heading label (real INWX invoice text)', () => {
    const text = 'Germany\r\nInvoice\r\nCustomer number:\r\nDocument number:\r\nDate:\r\nPage:\r\n'
      + '254945\r\n2026078217\r\n2026-07-31\r\n1 / 1\r\nYour Invoice';
    expect(extractNumber(text)).toBe('2026078217');
  });
  it('does not mistake a table header + first item line for a number when a "RECHNUNG" heading is not part of a real label block (fonial-style)', () => {
    const text = 'Lieferdatum entspricht Rechnungsdatum.\r\nRECHNUNG\r\nProdukt   Menge   Datum / Zeitraum   Nettogesamtpreis\r\n'
      + 'fonial PLUS   01.06.2026 - 30.06.2026';
    expect(extractNumber(text)).toBe('');
  });
});

describe('extractVatRate', () => {
  it('reads the highest VAT rate on a VAT-mentioning line', () => {
    expect(extractVatRate('7% MwSt 1,05\n19% MwSt 3,80\n')).toBe('19');
  });
  it('maps Kleinunternehmer §19 to 0', () => { expect(extractVatRate('Gemäß §19 UStG wird keine Umsatzsteuer berechnet.')).toBe('0'); });
  it('returns empty when no VAT is mentioned', () => { expect(extractVatRate('Total 45.00')).toBe(''); });
});

describe('extractVatId', () => {
  it('reads a labelled German VAT-ID', () => { expect(extractVatId('USt-IdNr.: DE265814432')).toBe('DE265814432'); });
  it('reads a bare German VAT-ID without a label', () => { expect(extractVatId('Steuernummer 123/456/789 · DE265814432')).toBe('DE265814432'); });
  it('normalises spaces/dots/dashes', () => { expect(extractVatId('VAT ID: DE 265 814 432')).toBe('DE265814432'); });
  it('reads an Austrian VAT-ID', () => { expect(extractVatId('UID: ATU12345678')).toBe('ATU12345678'); });
  it('rejects a malformed DE id (wrong digit count)', () => { expect(extractVatId('USt-IdNr.: DE12345')).toBe(''); });
  it('returns empty when absent', () => { expect(extractVatId('Total 45.00')).toBe(''); });
  it('does not swallow the following lines into the captured id', () => {
    const text = 'USt-IdNr.: DE313567169\n\nLeistung ... 900,00\n19% MwSt 171,00\nGesamtbetrag 1071,00 EUR';
    expect(extractVatId(text)).toBe('DE313567169');
  });
});

describe('extractCurrency', () => {
  it('prefers EUR when both $ and € appear', () => { expect(extractCurrency('$ logo ... Gesamt 45,00 €')).toBe('EUR'); });
  it('detects USD', () => { expect(extractCurrency('Total: $45.00 USD')).toBe('USD'); });
});

describe('extractOrderRef', () => {
  // Real Amazon invoice snippets (August 2026) — Amazon prints the SAME
  // "Zahlungsreferenznummer" on every invoice of one order/charge, which is the
  // signal the receipt<->transaction matcher groups on for a split order.
  it('reads the reference from a real Amazon invoice header', () => {
    expect(extractOrderRef('Zahlungsreferenznummer 3lOvS0TSid0aZgi3lA6S\nVerkauft von memoryking GmbH & Co. KG')).toBe('3lOvS0TSid0aZgi3lA6S');
  });
  it('is case-insensitive on the label', () => {
    expect(extractOrderRef('zahlungsreferenznummer X2WmQiYAdVrdhYhmIF1X')).toBe('X2WmQiYAdVrdhYhmIF1X');
  });
  it('returns empty when the document has no such reference', () => {
    expect(extractOrderRef('Rechnungsnummer DE60GTQMP053RU\nZahlbetrag 11,99 €')).toBe('');
  });
});

describe('buildReceiptName', () => {
  it('joins date (compact) + issuer + number', () => {
    expect(buildReceiptName('2026-07-02', 'fonial GmbH', '2026061702224')).toBe('20260702; fonial GmbH; 2026061702224');
  });
  it('omits a part that was not recognised instead of inserting a placeholder', () => {
    expect(buildReceiptName('2026-07-02', 'fonial GmbH', '')).toBe('20260702; fonial GmbH');
    expect(buildReceiptName('', 'fonial GmbH', '123')).toBe('fonial GmbH; 123');
  });
  it('strips characters that would break the "; "-separated format or a filename', () => {
    expect(buildReceiptName('2026-07-02', 'Acme; Corp / Ltd', 'AB:12')).toBe('20260702; Acme Corp Ltd; AB12');
  });
  it('is empty when nothing was recognised', () => { expect(buildReceiptName('', '', '')).toBe(''); });
});

describe('analyzeReceiptText', () => {
  it('classifies a known category and collects tags', () => {
    const r = analyzeReceiptText('netcup GmbH\nHosting-Vertrag\n19% MwSt\nGesamt 23,80 €\n27.07.2026');
    expect(r.category).toBe('Software');
    expect(r.merchant).toBe('netcup GmbH');
    expect(r.total).toBe(23.8);
    expect(r.vat).toBe('19');
    expect(r.date).toBe('2026-07-27');
    expect(r.tags).toContain('Software');
  });
  it('extracts the order/payment reference alongside the other fields', () => {
    const r = analyzeReceiptText('Zahlungsreferenznummer 3eTEaMIJmYRBXpEc2Rm4\nVerkauft von Spigen Korea Co.,Ltd.\nZahlbetrag 19,99 €');
    expect(r.orderRef).toBe('3eTEaMIJmYRBXpEc2Rm4');
  });
  it('avoids the "kündbar" false positive for Geschäftsessen (bar)', () => {
    expect(analyzeReceiptText('Vertrag monatlich kündbar\n19,99 €').category).toBe('');
  });
  it('classifies a tax-advisor invoice as Steuerberatung', () => {
    const r = analyzeReceiptText('Buchen und kontieren der laufenden Geschäftsvorfälle\nNettobetrag 400,00\n+ 19,00 % USt 76,00\nBruttobetrag 476,00');
    expect(r.category).toBe('Steuerberatung');
  });
  // Regression: a real self-issued Eigenbeleg's own boilerplate "Beleggrund"
  // reason checklist always prints EVERY option — including "Trinkgeld" — no
  // matter which one was actually ticked (the checkbox glyph itself collapses
  // to an identical bullet for checked/unchecked once run through OCR/text
  // extraction), so the generic keyword scan always misclassified a private
  // withdrawal as a business meal ("Geschäftsessen") via that fixed word.
  it('leaves the category unset on a self-issued Eigenbeleg instead of guessing from its fixed checklist', () => {
    const text = 'Eigenbeleg\nBeleg grund\n•Privatentnahme                                                 & Privateinlage\n'
      + '• Trinkgeld\n• Betriebsausgabe (verlorener Beleg)\n• Sachgeschenke (Blumen, Wein)\n• Sonstiges\n'
      + 'Belegdaten\nBetrag                                                 150 E u r o\n'
      + 'Buchungstext / Bemerkung:                  Privatentnahme\nNeudrossenfeld, 26.08.2024';
    const r = analyzeReceiptText(text);
    expect(r.category).toBe('');
    expect(r.total).toBe(150);
    expect(r.eigenbelegType).toBe('Privatentnahme');
  });
  // Regression: real Eigenbeleg PDFs render "Beleggrund" with an
  // unpredictable, different letter-spacing split EVERY time — "Bele ggru
  // nd" on one real document (not even at the natural word boundary, unlike
  // "Beleg grund"), which the soft `beleg\s*grund` pattern still missed,
  // leaving the category detector to run its generic keyword scan after all
  // and mis-tag the receipt as Geschäftsessen via the fixed "Trinkgeld"
  // checklist option. Stripping ALL whitespace before matching is immune to
  // wherever the PDF happens to split it.
  it('still detects an Eigenbeleg whose "Beleggrund" heading is split at an arbitrary point', () => {
    const text = 'Eigenbeleg\nBele ggru nd\n•Privatentnahme  • Privateinlage\nD Trinkgeld\n'
      + '• Betriebsausgabe (verlorener Beleg)\n• Sachgeschenke (Blumen, Wein)\n• Sonstiges\n'
      + 'Belegdaten\nBetrag  -  100 E u r o\nBuchungstext / Bemerkung:  Privateinlage\nNeudrossenfeld, 20.02.2025';
    const r = analyzeReceiptText(text);
    expect(r.category).toBe('');
    expect(r.merchant).toBe('Eigenbeleg');
    expect(r.eigenbelegType).toBe('Privateinlage');
  });
  // Regression: a real Eigenbeleg's OWN title is subject to the same
  // unpredictable split as "Beleggrund" — one document rendered it as
  // "Eigen beleg" (a plain two-word split), and cleanMerchant's generic
  // trailing-document-word stripper (designed to cut a real company name's
  // own "... Rechnung"/"... Invoice" suffix) then mistook the second half
  // "beleg" for that generic suffix, returning just "Eigen". A confirmed
  // Eigenbeleg gets its canonical merchant name set directly instead of
  // trusting the generic extractor for this document type.
  it('normalises a split Eigenbeleg title ("Eigen beleg") to the canonical "Eigenbeleg" merchant', () => {
    const text = 'Eigen beleg\nBeleggrund\n&Privatentnahme  • Privateinlage\n• Trinkgeld\n'
      + '• Betriebsausgabe (verlorener Beleg)\n• Sachgeschenke (Blumen, Wein)\n• Sonstiges\n'
      + 'Belegdaten\nBetrag  152,51 Euro\nBuchungstext / Bemerkung:  Privatentnahme\nNeudrossenfeld, 07.04.2025';
    expect(analyzeReceiptText(text).merchant).toBe('Eigenbeleg');
  });
  // The fixed Beleggrund checklist can't tell Privatentnahme from Privateinlage
  // (both checkbox glyphs collapse to an identical bullet after OCR — see the
  // comment on `isEigenbeleg` above), but the free-text "Buchungstext /
  // Bemerkung:" line states the chosen reason as a plain word.
  it('extracts the Eigenbeleg type from the free-text Buchungstext/Bemerkung line', () => {
    expect(extractEigenbelegType('Buchungstext / Bemerkung:                 Privatentnahme')).toBe('Privatentnahme');
    expect(extractEigenbelegType('Buchungstext / Bemerkung:  Privateinlage')).toBe('Privateinlage');
    expect(extractEigenbelegType('kein Eigenbeleg hier')).toBe('');
  });
  // Regression: a real Eigenbeleg's Buchungstext line carries trailing free
  // text after the reason word (e.g. a manual note about a related rebooking)
  // — the type must still be recognised, not just an exact-match line.
  it('still recognises the type when the Buchungstext line has trailing free text', () => {
    expect(extractEigenbelegType('Buchungstext / Bemerkung:                 Privateinlage >- Umbuchung N26')).toBe('Privateinlage');
  });
  // Same-day self-receipts share the same date and the bare "Eigenbeleg"
  // merchant — without a real invoice number, they'd collide in any
  // filename/list built from (date, merchant, number) alone. The type is the
  // one thing that actually distinguishes them, so buildReceiptName folds it
  // into the merchant slot — but `merchant` itself, as returned by
  // analyzeReceiptText, stays the bare 'Eigenbeleg' (partner-matching must
  // not fragment by transaction type).
  it('folds the Eigenbeleg type into buildReceiptName without touching the bare merchant field', () => {
    const text = 'Eigenbeleg\nBeleggrund\n&Privatentnahme  • Privateinlage\n• Trinkgeld\n'
      + '• Betriebsausgabe (verlorener Beleg)\n• Sachgeschenke (Blumen, Wein)\n• Sonstiges\n'
      + 'Belegdaten\nBetrag  700 Euro\nBuchungstext / Bemerkung:  Privatentnahme\nNeudrossenfeld, 16.09.2025';
    const r = analyzeReceiptText(text);
    expect(r.merchant).toBe('Eigenbeleg');
    expect(r.eigenbelegType).toBe('Privatentnahme');
    expect(buildReceiptName(r.date, r.merchant, r.number, r.eigenbelegType)).toBe('20250916; Eigenbeleg (Privatentnahme)');
  });
  it('leaves buildReceiptName unchanged when no eigenbelegType is given', () => {
    expect(buildReceiptName('2025-09-16', 'Eigenbeleg', '')).toBe('20250916; Eigenbeleg');
    expect(buildReceiptName('2025-09-16', 'netcup GmbH', 'nc-1234', '')).toBe('20250916; netcup GmbH; nc-1234');
  });
});
