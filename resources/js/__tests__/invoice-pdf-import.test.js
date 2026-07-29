import { describe, it, expect } from 'vitest';
import {
    parseAmount, parseGermanDate, parseInvoiceFilename, parseInvoiceText, buildImportedInvoice, parseInvoiceNumber, parseCustomer, importedSeq, parseFirstLineItem,
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

describe('invoice PDF import — text is authoritative over the filename', () => {
    it('prefers the text number when the filename disagrees (45 vs R-00045)', () => {
        const f = parseInvoiceFilename('Rechnungsnr. 45 - Lorenz IT Dienstleistungen 2.pdf');
        const p = parseInvoiceText('Rechnung\nKiefer Networks | Talmatten 10 | 79639 Grenzach-Wyhlen\nLORENZ IT-Dienstleistungen Ltd. & Co. KG\nHerr Rüdiger Lorenz\nFriedrich-Jung-Str. 5\n79618 Rheinfelden\nRechnungsnummer\nR-00045\nKundennummer\nK-00003\nNettobetrag: 100,00 €\nzzgl. 19% MwSt.: 19,00 €\nGesamtbetrag: 119,00 €');
        const inv = buildImportedInvoice(f, p, { id: 'z' });
        expect(inv.number).toBe('R-00045'); // NOT the filename's "45", and NOT the K- number
        expect(inv.customer.name).toBe('LORENZ IT-Dienstleistungen Ltd. & Co. KG');
    });
    it('recognises every number-format generation from the text', () => {
        expect(parseInvoiceNumber('Rechnung R-2024-00001 Datum: …')).toBe('R-2024-00001');
        expect(parseInvoiceNumber('… R-00124 Datum …')).toBe('R-00124');
        expect(parseInvoiceNumber('Rechnung\nRechnungsnr.\n1\nRechnungsdatum 10.04.2014')).toBe('1');
        expect(parseInvoiceNumber('Rechnungsnummer\nR-00045\nKundennummer\nK-00003')).toBe('R-00045');
        expect(parseInvoiceNumber('no number here')).toBeNull();
    });
    it('extracts the recipient block from the text (family A)', () => {
        const c = parseCustomer('Kiefer Networks Beethovenstraße 10 - 79183 Waldkirch\nSTN Nürnberg\nMauermattenstraße 20\nD-79183 Waldkirch\nUSt.-IdNr. DE265814432\nRechnung');
        expect(c.name).toBe('STN Nürnberg');
        expect(c.address).toContain('Mauermattenstraße 20');
    });
    it('extracts the recipient block from the text (family B)', () => {
        const c = parseCustomer('Kiefer Networks - Adalbert-Stifter-Str. 6 - 95512 Neudrossenfeld\nIntellyTec GmbH\nIngo Radermacher\nGrünenborn 1\n53797 Lohmar\nRechnung\nR-2024-00001');
        expect(c.name).toBe('IntellyTec GmbH');
        expect(c.address).toContain('53797 Lohmar');
    });
});

describe('invoice PDF import — the current-year 2026 format', () => {
    it('parses both decimal notations', () => {
        expect(parseAmount('€157.50')).toBe(157.5);   // English dot
        expect(parseAmount('149,85 €')).toBe(149.85);  // German comma
        expect(parseAmount('1.071,00 €')).toBe(1071);  // German thousands
        expect(parseAmount('$1,234.56')).toBe(1234.56); // English thousands
    });
    it('recognises the YYYY-NNN number (labeled and bare)', () => {
        expect(parseInvoiceNumber('Rechnung #: 2026-001 Rechnungsdatum: 02.02.2026')).toBe('2026-001');
        expect(parseInvoiceNumber('Rechnung Nr.:\nRechnungsdatum:\n2026-003\n30.04.2026')).toBe('2026-003');
        expect(parseInvoiceNumber('… 2026-005 RECHNUNG …')).toBe('2026-005');
    });
    it('parses the English-decimal sheet (2026-001) to zero warnings', () => {
        const text = 'Rechnung #: 2026-001\nRechnungsdatum: 02.02.2026\nFällig am: 16.02.2026\nRECHNUNG AN\nIntellyTec GmbH\nIngo Radermacher\nGrünenborn 1, 53797 Lohmar\nZwischensumme: €157.50\nSteuer (19%): €29.93\nGesamt: €187.43';
        const inv = buildImportedInvoice({ number: '2026-001' }, parseInvoiceText(text), { id: 'a', currentYear: 2026 });
        expect(inv.number).toBe('2026-001');
        expect(inv.issueDate).toBe('2026-02-02');
        expect(inv.dueDate).toBe('2026-02-16');
        expect(inv.customer.name).toBe('IntellyTec GmbH');
        expect(inv.lines[0].unitPrice).toBe(157.5);
        expect(inv.lines[0].vatRate).toBe(19);
        expect(inv.seq).toBe(1);
        expect(inv._warnings).toEqual([]);
    });
    it('strips the seller from a two-column recipient (2026-005)', () => {
        const c = parseCustomer('2026-005 RECHNUNG\nVON RECHNUNG AN\nKiefer Networks IntellyTec GmbH\nAdalbert-Stifter-Str. 6 Ingo Radermacher\n95512 Neudrossenfeld Grünenborn 1, 53797 Lohmar');
        expect(c.name).toBe('IntellyTec GmbH');
    });
    it('sets a seq only for the current-year series', () => {
        expect(importedSeq('2026-005', 2026)).toBe(5);
        expect(importedSeq('2026-001', 2026)).toBe(1);
        expect(importedSeq('2025-010', 2026)).toBeNull(); // different year
        expect(importedSeq('R-00124', 2026)).toBeNull();  // different format
        expect(importedSeq('20', 2026)).toBeNull();
    });
});

describe('invoice PDF import — column-separated dates (pdf.js groups labels then values)', () => {
    // The older Kiefer sheet renders labels in one text group and the values in another,
    // so the label-adjacent regex can't bind them; issue = earliest date, due = the first
    // date after it.
    const text = `Rechnung
Rechnungsnr.
Rechnungsdatum
Fälligkeitsdatum
Zu zahlen EUR
4
17.06.2014
17.07.2014
36,00
Beschreibung Menge Einheit Preis Betrag
IT Wartung & Pflege 3 Stunde(n) 12,00 36,00
Gesamt EUR 36,00
Gemäß § 19 (1) UStG erheben wir keine Umsatzsteuer.`;
    const p = parseInvoiceText(text);
    it('recovers issue and due dates positionally', () => {
        expect(p.date).toBe('2014-06-17');
        expect(p.dueDate).toBe('2014-07-17'); // not the issue date
    });
    it('parses the first line item with its unit', () => {
        expect(p.firstItem).toEqual(expect.objectContaining({ desc: 'IT Wartung & Pflege', qty: 3, unit: 'Stunde(n)', unitPrice: 12 }));
    });
    it('builds a single-item line that keeps qty + unit + unit price', () => {
        const inv = buildImportedInvoice(parseInvoiceFilename('20140617_ Rechnung 4 - STN Nürnberg.pdf'), p, { id: 'x', currentYear: 2014 });
        expect(inv.lines).toHaveLength(1);
        expect(inv.lines[0]).toEqual(expect.objectContaining({ desc: 'IT Wartung & Pflege', qty: 3, unit: 'Stunde(n)', unitPrice: 12, vatRate: 0 }));
        expect(inv.dueDate).toBe('2014-07-17');
    });
});

describe('invoice PDF import — parseFirstLineItem', () => {
    it('parses a unit-bearing row (qty × price = amount)', () => {
        const it = parseFirstLineItem('Beschreibung Menge Einheit Preis Betrag\nBeratung 2 Std. 90,00 180,00');
        expect(it).toEqual(expect.objectContaining({ qty: 2, unit: 'Std.', unitPrice: 90, amount: 180 }));
    });
    it('parses a row without a unit column', () => {
        const it = parseFirstLineItem('Beschreibung Menge Preis Betrag\nLizenz 3 10,00 30,00');
        expect(it).toEqual(expect.objectContaining({ qty: 3, unit: '', unitPrice: 10, amount: 30 }));
    });
    it('rejects a row where qty × price != amount (mis-parse guard)', () => {
        expect(parseFirstLineItem('Beschreibung Menge Einheit Preis Betrag\nZeug 2 Stk 5,00 99,00')).toBeNull();
    });
    it('multi-item invoice keeps the safe qty-1 net summary line', () => {
        // Two items → first item amount (36) != net (60), so the summary line is used.
        const p = parseInvoiceText('Beschreibung Menge Einheit Preis Betrag\nA 3 Stunde(n) 12,00 36,00\nB 2 Stk 12,00 24,00\nGesamt EUR 60,00\nGemäß § 19 UStG keine Umsatzsteuer.');
        const inv = buildImportedInvoice({ date: null, number: null, customer: null }, p, { id: 'y', summaryLabel: 'Rechnungsbetrag' });
        expect(inv.lines).toHaveLength(1);
        expect(inv.lines[0].qty).toBe(1);
        expect(inv.lines[0].unitPrice).toBe(60);
    });
});
