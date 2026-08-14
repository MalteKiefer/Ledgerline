import { describe, it, expect } from 'vitest';
import {
  extractTotal, extractDate, extractMerchant, extractNumber,
  extractVatRate, extractVatId, extractCurrency, analyzeReceiptText,
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
});

describe('extractNumber', () => {
  it('reads a labelled invoice number', () => { expect(extractNumber('Rechnungsnr.: R-00123')).toBe('R-00123'); });
  it('rejects a numeric date mistaken for a number', () => { expect(extractNumber('Rechnungsnr. 27.07.2026')).toBe(''); });
  it('joins a space-grouped reference number onto one line (Telekom-style)', () => {
    expect(extractNumber('Rechnungsnummer                          25 5828 2901 2681')).toBe('25582829012681');
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
  it('avoids the "kündbar" false positive for Geschäftsessen (bar)', () => {
    expect(analyzeReceiptText('Vertrag monatlich kündbar\n19,99 €').category).toBe('');
  });
});
