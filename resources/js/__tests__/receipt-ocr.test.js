import { describe, it, expect } from 'vitest';
import { analyzeReceiptText, extractTotal, extractDate, extractMerchant } from '../shared/receipt-ocr.js';

const RESTAURANT = `Ristorante Da Mario
Hauptstraße 5, 95326 Kulmbach
Tel.: 09221 12345

2x Pizza        18,00
1x Wein          6,50
Trinkgeld        2,00

Summe           26,50
Datum 17.07.2026`;

describe('receipt OCR analysis', () => {
    it('extracts the total from a summe line, else the max amount', () => {
        expect(extractTotal(RESTAURANT)).toBe(26.5);
        expect(extractTotal('Betrag 9,99\nMwSt 1,59\nGesamt 11,58')).toBe(11.58);
        expect(extractTotal('Artikel 3,00\nArtikel 12,40')).toBe(12.4); // no label → max
        expect(extractTotal('no amounts here')).toBe(null);
    });
    it('extracts a date as ISO', () => {
        expect(extractDate(RESTAURANT)).toBe('2026-07-17');
        expect(extractDate('Kauf am 3.9.24')).toBe('2024-09-03');
        expect(extractDate('2026-02-01 stuff')).toBe('2026-02-01');
    });
    it('extracts the merchant from the first meaningful line', () => {
        expect(extractMerchant(RESTAURANT)).toBe('Ristorante Da Mario');
        expect(extractMerchant('12,90\nShell Station\nDiesel')).toBe('Shell Station');
    });
    it('classifies the category from keywords', () => {
        expect(analyzeReceiptText(RESTAURANT).category).toBe('Geschäftsessen');
        expect(analyzeReceiptText('ARAL Tankstelle\nDiesel 60,00').category).toBe('Kfz');
        expect(analyzeReceiptText('Hotel Adler\nÜbernachtung 89,00').category).toBe('Reisekosten');
        expect(analyzeReceiptText('Adobe Creative Cloud\nLizenz 59,49').category).toBe('Software');
        expect(analyzeReceiptText('random note').category).toBe('');
    });
    it('does not misfire on substrings (kündbar ≠ bar) and prefers the specific category', () => {
        // A telecom invoice containing "unkündbar" must NOT become Geschäftsessen.
        const tel = 'Telekom Deutschland GmbH\nMobilfunk-Rechnung\nVertrag monatlich kündbar\nRechnungsbetrag 39,85';
        expect(analyzeReceiptText(tel).category).toBe('Telekommunikation');
        // "total"/"super" are too generic and must not classify as Kfz.
        expect(analyzeReceiptText('Supermarkt\nTotal 12,00').category).toBe('');
    });
    it('suggests tags (merchant + category), de-duplicated', () => {
        const a = analyzeReceiptText(RESTAURANT);
        expect(a.tags).toEqual(['Ristorante Da Mario', 'Geschäftsessen']);
        expect(a.total).toBe(26.5);
        expect(a.date).toBe('2026-07-17');
    });
});
