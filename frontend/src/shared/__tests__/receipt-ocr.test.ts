import { describe, it, expect } from 'vitest';
import {
  extractTotal, extractDate, extractMerchant, extractNumber,
  extractVatRate, extractVatId, extractCurrency, extractOrderRef, buildReceiptName, analyzeReceiptText,
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
});

describe('extractDate', () => {
  it('parses DD.MM.YYYY', () => { expect(extractDate('Datum: 27.07.2026')).toBe('2026-07-27'); });
  it('parses a German month name', () => { expect(extractDate('27. Juli 2026')).toBe('2026-07-27'); });
  it('parses an English month name', () => { expect(extractDate('July 27, 2026')).toBe('2026-07-27'); });
  it('parses a dash month abbreviation', () => { expect(extractDate('27-MAR-2025')).toBe('2025-03-27'); });
  it('rejects an invalid day/month', () => { expect(extractDate('99.99.2026')).toBe(''); });
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
});
