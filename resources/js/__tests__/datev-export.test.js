import { describe, it, expect } from 'vitest';
import { buildRevenueCsv, buildExpenseCsv, num, cell } from '../shared/datev-export.js';

const inv = (o) => ({ status: 'sent', trashed: false, currency: 'EUR', lines: [{ qty: 1, unitPrice: 100, vatRate: 19 }], ...o });

describe('datev-export', () => {
    it('formats German decimals + escapes cells', () => {
        expect(num(1234.5)).toBe('1234,50');
        expect(cell('a;b')).toBe('"a;b"');
        expect(cell('he said "hi"')).toBe('"he said ""hi"""');
    });
    it('revenue CSV: BOM, header, one row per realized invoice in the year', () => {
        const csv = buildRevenueCsv([
            inv({ issueDate: '2026-03-01', number: '2026-0001' }),
            inv({ issueDate: '2025-01-01', number: '2025-9' }),      // other year
            inv({ issueDate: '2026-05-01', number: 'd', status: 'draft' }), // not realized
        ], 2026);
        expect(csv.charCodeAt(0)).toBe(0xFEFF);
        const rows = csv.replace(/^﻿/, '').split('\r\n');
        expect(rows.length).toBe(2); // header + 1
        expect(rows[1]).toContain('2026-0001');
        expect(rows[1]).toContain('119,00'); // gross
        expect(rows[1]).toContain('100,00'); // net
    });
    it('credit note gross is negative', () => {
        const csv = buildRevenueCsv([
            inv({ issueDate: '2026-02-01', number: 'g1', type: 'credit', lines: [{ qty: 1, unitPrice: -100, vatRate: 19 }] }),
        ], 2026);
        expect(csv).toContain('-119,00');
    });
    it('expense CSV: receipts + project expenses in the year', () => {
        const csv = buildExpenseCsv(
            [{ date: '2026-04-02', counterparty: 'Netcup', receipts: [{ date: '2026-04-02', merchant: 'Netcup', total: 11.9, vat: 19, categories: ['Hosting'] }] }],
            [{ name: 'Haus', expenses: [{ date: '2026-06-01', amount: 250, note: 'Bagger', category: 'Bau' }] }],
            2026,
        );
        const rows = csv.replace(/^﻿/, '').split('\r\n');
        expect(rows.length).toBe(3); // header + 2
        expect(csv).toContain('Netcup');
        expect(csv).toContain('Bagger');
        expect(csv).toContain('10,00'); // net of 11.90 @19%
    });
});
