import { describe, it, expect } from 'vitest';
import {
    parseAmount, parseGermanDate, parseInvoiceFilename, parseInvoiceText, buildImportedInvoice,
} from '../shared/invoice-pdf-import.js';

describe('invoice PDF import — primitives', () => {
    it('parses German amounts', () => {
        expect(parseAmount('227,88 €')).toBe(227.88);
        expect(parseAmount('1.234,56')).toBe(1234.56);
        expect(parseAmount('40,00 €')).toBe(40);
        expect(parseAmount('')).toBeNull();
    });
    it('parses German dates to ISO', () => {
        expect(parseGermanDate('05.02.2022')).toBe('2022-02-05');
        expect(parseGermanDate('x')).toBeNull();
    });
});

describe('invoice PDF import — filename', () => {
    it('reads date + number + customer from the modern filename', () => {
        expect(parseInvoiceFilename('20240429_ Kiefer Networks_ Rechnung R-2024-00001 - IntellyTec GmbH.pdf'))
            .toEqual({ date: '2024-04-29', number: 'R-2024-00001', customer: 'IntellyTec GmbH' });
    });
    it('reads the old numeric filename', () => {
        expect(parseInvoiceFilename('20140410_ Kiefer Networks_ Rechnung 1 - STN Nürnberg.pdf'))
            .toEqual({ date: '2014-04-10', number: '1', customer: 'STN Nürnberg' });
    });
    it('reads the Rechnungsnr. filename without a date and strips copy markers', () => {
        expect(parseInvoiceFilename('Rechnungsnr. R-00124 - Hotel Ammerländer Hof 2.pdf'))
            .toEqual({ date: null, number: 'R-00124', customer: 'Hotel Ammerländer Hof' });
    });
});

describe('invoice PDF import — text (family B, modern with VAT)', () => {
    const text = `Rechnung
R-00124                                    Datum: 05.02.2022
Der Gesamtbetrag ist bis zum 12.02.2022 auf unser
  Pos    Beschreibung                     Einzelpreis   Anzahl   Gesamtpreis
   1     de Domain (15 verschiedene)         15,00 €   12 Monate   180,00 €
Der Gesamtbetrag ist bis zum 12.02.2022 auf unser              Nettobetrag:      227,88 €
Konto zu zahlen.                                              zzgl. 19% MwSt.:    43,30 €
                                                              Gesamtbetrag:      271,18 €`;
    const p = parseInvoiceText(text);
    it('extracts number, dates, totals and VAT', () => {
        expect(p.number).toBe('R-00124');
        expect(p.date).toBe('2022-02-05');
        expect(p.dueDate).toBe('2022-02-12');
        expect(p.net).toBe(227.88);
        expect(p.vat).toBe(43.30);
        expect(p.gross).toBe(271.18);
        expect(p.vatRate).toBe(19);
        expect(p.smallBusiness).toBe(false);
    });
});

describe('invoice PDF import — text (family A, small business / no VAT)', () => {
    const text = `Rechnung
Rechnungsnr.                          1
Rechnungsdatum               10.04.2014
Fälligkeitsdatum             30.04.2014
Zu zahlen EUR                   146,58
Gesamt EUR                      146,58
Gemäß § 19 (1) UStG erheben wir keine Umsatzsteuer (Kleinunternehmerstatus).`;
    const p = parseInvoiceText(text);
    it('detects small business, zero VAT, gross', () => {
        expect(p.date).toBe('2014-04-10');
        expect(p.dueDate).toBe('2014-04-30');
        expect(p.smallBusiness).toBe(true);
        expect(p.vatRate).toBe(0);
        expect(p.gross).toBe(146.58);
        expect(p.net).toBe(146.58); // reconciled: gross - vat(0)
    });
});

describe('invoice PDF import — text (family A, with VAT)', () => {
    const text = `Rechnungsnr.                          20
Rechnungsdatum               21.06.2016
Fälligkeitsdatum             05.07.2016
Zwischensumme ohne USt.               225,00
USt. 19% von 225,00                    42,75
Gesamt EUR                            267,75`;
    const p = parseInvoiceText(text);
    it('extracts subtotal, VAT and gross from the classic sheet', () => {
        expect(p.net).toBe(225);
        expect(p.vat).toBe(42.75);
        expect(p.gross).toBe(267.75);
        expect(p.vatRate).toBe(19);
    });
});

describe('invoice PDF import — build draft', () => {
    it('merges filename + text into a paid, single-line draft', () => {
        const f = parseInvoiceFilename('20240429_ Kiefer Networks_ Rechnung R-2024-00001 - IntellyTec GmbH.pdf');
        const p = parseInvoiceText('R-2024-00001  Datum: 29.04.2024\nNettobetrag: 40,00 €\nzzgl. 19% MwSt.: 7,60 €\nGesamtbetrag: 47,60 €');
        const inv = buildImportedInvoice(f, p, { id: 'x', summaryLabel: 'Rechnungsbetrag' });
        expect(inv.number).toBe('R-2024-00001');
        expect(inv.issueDate).toBe('2024-04-29');
        expect(inv.customer.name).toBe('IntellyTec GmbH');
        expect(inv.status).toBe('paid');
        expect(inv.lines).toHaveLength(1);
        expect(inv.lines[0].unitPrice).toBe(40);
        expect(inv.lines[0].vatRate).toBe(19);
        expect(inv._warnings).toEqual([]);
        expect(inv._parsedGross).toBe(47.6);
    });
    it('flags missing fields as warnings', () => {
        const inv = buildImportedInvoice({ number: null, date: null, customer: null }, {}, { id: 'y' });
        expect(inv._warnings).toContain('number');
        expect(inv._warnings).toContain('date');
        expect(inv._warnings).toContain('amount');
    });
});

describe('invoice PDF import — column-separated layouts (2019-2021)', () => {
    it('reconciles net+VAT=gross when labels and amounts are grouped apart', () => {
        // R-00117-style: labels together, amounts together elsewhere.
        const text = 'R-00117 27.01.2021 Nettobetrag: zzgl. 19% MwSt.: Gesamtbetrag: Gesamtpreis 59,28 € 11,26 € 70,54 €';
        const p = parseInvoiceText(text);
        expect(p.net).toBe(59.28);
        expect(p.vat).toBe(11.26);
        expect(p.gross).toBe(70.54);
    });
    it('falls back to the first date when no Datum label is adjacent', () => {
        const p = parseInvoiceText('Rechnung R-00104 14.05.2020 Leistungszeitraum 30.04.2020');
        expect(p.date).toBe('2020-05-14');
    });
    it('handles the Nettogesamt/Rechnungsbetrag label variant', () => {
        const p = parseInvoiceText('Nettogesamt 260,00 € Umsatzsteuer 19% 49,40 € Rechnungsbetrag 309,40 €');
        expect(p.net).toBe(260); expect(p.vat).toBe(49.4); expect(p.gross).toBe(309.4);
    });
});
