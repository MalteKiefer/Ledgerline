import { describe, it, expect } from 'vitest';
import {
    parseAmount, parseDate, detectFormat, parseMt940, parseMt940Field86,
    parseCsv, detectCsvMapping, applyCsvMapping, txSignature, dedupeTransactions, TX_FIELDS,
    enrichExisting, classifyTxType,
} from '../shared/bank-statement.js';

describe('bank statement parsing', () => {
    it('parses German and English amounts', () => {
        expect(parseAmount('1.992,43')).toBe(1992.43);
        expect(parseAmount('-175,28')).toBe(-175.28);
        expect(parseAmount('213,51')).toBe(213.51);
        expect(parseAmount('1,992.43')).toBe(1992.43);
        expect(parseAmount('150.00')).toBe(150);
        expect(parseAmount('2200,00')).toBe(2200);
        expect(parseAmount('')).toBe(null);
    });
    it('normalises dates from all statement styles', () => {
        expect(parseDate('29.07.26')).toBe('2026-07-29');
        expect(parseDate('2024-08-26')).toBe('2024-08-26');
        expect(parseDate('260430')).toBe('2026-04-30'); // MT940 YYMMDD
        expect(parseDate('')).toBe(null);
    });
    it('detects the format', () => {
        expect(detectFormat(':20:X\n:25:Y\n:61:2604300430DR1,00N010NONREF')).toBe('mt940');
        expect(detectFormat('"a";"b";"c"\n"1";"2";"3"')).toBe('csv');
        expect(detectFormat('just some prose')).toBe('unknown');
    });

    const MT940 = [
        ':20:STARTUMSE',
        ':25:77150000/0101918910',
        ':28C:00000/001',
        ':60F:C260429EUR1992,43',
        ':61:2604300430DR213,51N010NONREF',
        ':86:601?00EINZUG RATE?106000?20Rechnung?21 Darlehen?30BYLADEM1KUB?31DE69771500006202469687?32Sparkas',
        'se Kulmbach',
        ':61:2607280728CR2200,00N060NONREF',
        ':86:152?00GUTSCHRIFT?20SVWZ+Miete Juli?32Max Muster',
        ':62F:C260728EUR3978,92',
        '-',
    ].join('\n');

    it('parses MT940 incl. folded :86:, D/C sign and balances', () => {
        const r = parseMt940(MT940);
        expect(r.openingBalance).toBe(1992.43);
        expect(r.closingBalance).toBe(3978.92);
        expect(r.currency).toBe('EUR');
        expect(r.transactions).toHaveLength(2);
        const [a, b] = r.transactions;
        expect(a.amount).toBe(-213.51);           // DR → negative
        expect(a.counterparty).toBe('Sparkasse Kulmbach'); // folded ?32 continuation joined
        expect(a.iban).toBe('DE69771500006202469687');
        expect(a.bic).toBe('BYLADEM1KUB');
        expect(b.amount).toBe(2200);              // CR → positive
        expect(b.purpose).toBe('Miete Juli');     // SVWZ+ unwrapped
        // Balance reconciles: opening + sum(txns) == closing
        const sum = r.transactions.reduce((s, t) => s + t.amount, 0);
        expect(Math.round((r.openingBalance + sum) * 100) / 100).toBe(r.closingBalance);
    });

    it('splits :86: sub-fields', () => {
        const f = parseMt940Field86('152?00GUTSCHRIFT?20SVWZ+Hello?32Jane?33 Doe?31DE12?30ABCDEF');
        expect(f.bookingText).toBe('GUTSCHRIFT');
        expect(f.counterparty).toBe('Jane Doe');
        expect(f.iban).toBe('DE12');
        expect(f.bic).toBe('ABCDEF');
    });

    const SPK = [
        '"Auftragskonto";"Buchungstag";"Valutadatum";"Buchungstext";"Verwendungszweck";"Beguenstigter/Zahlungspflichtiger";"Kontonummer";"BLZ";"Betrag";"Waehrung";"Info"',
        '"DE10";"28.07.26";"28.07.26";"GUTSCHR";"SVWZ+Gehalt";"ACME GmbH";"DE99";"BYLADEM1KUB";"2200,00";"EUR";"Umsatz gebucht"',
        '"DE10";"";"29.07.26";"LASTSCHRIFT";"SVWZ+vorgemerkt";"Shop";"DE88";"COBADEFF";"-10,00";"EUR";"vorgemerkt"',
    ].join('\n');

    it('auto-maps a Sparkasse CSV and skips undated rows', () => {
        const c = parseCsv(SPK);
        expect(c.delimiter).toBe(';');
        const m = detectCsvMapping(c.header);
        expect(m.name).toBe('sparkasse');
        const { transactions, skipped } = applyCsvMapping(c.header, c.rows, m.map);
        expect(skipped).toBe(1); // the row with an empty Buchungstag
        expect(transactions).toHaveLength(1);
        expect(transactions[0]).toMatchObject({ date: '2026-07-28', amount: 2200, counterparty: 'ACME GmbH', purpose: 'Gehalt', currency: 'EUR' });
    });

    const GEN = [
        '"Buchungsdatum";"Wertstellungsdatum";"Transaktionstyp";"Empfänger";"Betrag";"IBAN";"Verwendungszweck";"end_to_end_id";"Buchungsstatus";"Kategorie";"Persönliche Notiz"',
        '"2024-09-02";"2024-09-02";"Überweisung";"IntellyTec GmbH";"866,34";"DE91";"Rechnung 1";"E-abc";"Gebucht";"USt 19%";""',
    ].join('\n');

    it('auto-maps a generic ISO CSV incl. eref', () => {
        const c = parseCsv(GEN);
        const m = detectCsvMapping(c.header);
        expect(m.name).toBe('generic-iso');
        const { transactions } = applyCsvMapping(c.header, c.rows, m.map);
        expect(transactions[0]).toMatchObject({ date: '2024-09-02', amount: 866.34, counterparty: 'IntellyTec GmbH', iban: 'DE91', eref: 'E-abc' });
    });

    it('returns null mapping for an unknown CSV (→ manual mapping)', () => {
        const c = parseCsv('"When";"How much";"Who"\n"2024-01-01";"5,00";"X"');
        expect(detectCsvMapping(c.header)).toBe(null);
        // A manual mapping still yields transactions.
        const { transactions } = applyCsvMapping(c.header, c.rows, { date: 'When', amount: 'How much', counterparty: 'Who' });
        expect(transactions[0]).toMatchObject({ date: '2024-01-01', amount: 5, counterparty: 'X' });
        expect(TX_FIELDS).toContain('amount');
    });

    it('dedupes on re-import', () => {
        const a = { date: '2024-01-01', amount: -5, counterparty: 'X', purpose: 'p' };
        const b = { date: '2024-01-02', amount: -6, counterparty: 'Y', purpose: 'q', eref: 'E1' };
        expect(txSignature(b)).toContain('E1');
        expect(dedupeTransactions([a], [a, b])).toEqual([b]); // a already present
        expect(dedupeTransactions([], [a, a])).toHaveLength(1); // within-batch dupes collapse
    });

    it('enriches an existing transaction with new info on re-import', () => {
        const existing = [{ date: '2024-01-02', amount: -6, counterparty: 'Y', purpose: 'q', eref: 'E1', iban: '', bic: '' }];
        const incoming = [
            { date: '2024-01-02', amount: -6, counterparty: 'Y', purpose: 'q', eref: 'E1', iban: 'DE99', bic: 'ABCDEF' }, // same tx, now has IBAN/BIC
            { date: '2024-01-03', amount: -7, counterparty: 'Z', purpose: 'r' }, // genuinely new
        ];
        const { fresh, updates } = enrichExisting(existing, incoming);
        expect(fresh).toHaveLength(1);
        expect(fresh[0].amount).toBe(-7);
        expect(updates).toHaveLength(1);
        expect(updates[0].patch).toEqual({ iban: 'DE99', bic: 'ABCDEF' }); // only the previously-empty fields
    });

    it('does not overwrite fields that already have a value', () => {
        const existing = [{ date: '2024-01-02', amount: -6, eref: 'E1', iban: 'DE-OLD' }];
        const incoming = [{ date: '2024-01-02', amount: -6, eref: 'E1', iban: 'DE-NEW', bic: 'X' }];
        const { updates } = enrichExisting(existing, incoming);
        expect(updates[0].patch).toEqual({ bic: 'X' }); // iban kept, only the missing bic added
    });

    it('classifies the payment type from booking text', () => {
        expect(classifyTxType({ bookingText: 'SEPA-ELV-LASTSCHRIFT', amount: -30 })).toBe('card');
        expect(classifyTxType({ bookingText: 'Kartenzahlung', amount: -10 })).toBe('card');
        expect(classifyTxType({ bookingText: 'FOLGELASTSCHRIFT', amount: -149 })).toBe('debit');
        expect(classifyTxType({ bookingText: 'EINZUG RATE/ANNUITAET', amount: -213 })).toBe('debit');
        expect(classifyTxType({ bookingText: 'GUTSCHR. UEBERW. DAUERAUFTR', amount: 2200 })).toBe('credit');
        expect(classifyTxType({ bookingText: 'DAUERAUFTRAG', amount: -410 })).toBe('standingorder');
        expect(classifyTxType({ bookingText: 'Echtzeitüberweisung', amount: 150 })).toBe('transfer');
        expect(classifyTxType({ bookingText: '', amount: 50 })).toBe('credit');
        expect(classifyTxType({ bookingText: '', amount: -50 })).toBe('other');
    });
});
