// GoBD accounting export — revenue journal (Rechnungsausgangsbuch) + expense journal
// (Belege/Rechnungseingang) as semicolon CSV with a UTF-8 BOM and German decimal comma.
// Universally importable (Steuerberater / DATEV generic CSV mapping / Excel). Pure +
// client-side (zero-knowledge — nothing leaves the browser). A full DATEV EXTF
// Buchungsstapel needs a chart-of-accounts mapping and is a deliberate follow-up.

import { invoiceTotals, realizedInvoices } from './finance-stats.js';

const yearOf = (s) => String(s || '').slice(0, 4);
/** German decimal (comma), fixed 2 places. */
export function num(n) { return (Number(n) || 0).toFixed(2).replace('.', ','); }
/** RFC-4180-ish cell, semicolon/quote/newline safe. */
export function cell(v) { const s = String(v ?? ''); return /[";\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; }
const line = (arr) => arr.map(cell).join(';');
/** Prepend a UTF-8 BOM so Excel/DATEV detect encoding. */
export function withBom(text) { return '﻿' + text; }

/**
 * Revenue journal for a year: one row per realized (sent/paid) invoice, incl. credit
 * notes (their negated totals subtract). Columns cover everything a bookkeeper needs.
 */
export function buildRevenueCsv(invoices, year, headers) {
    const h = headers || {};
    const rows = [line([
        h.date || 'Belegdatum', h.number || 'Rechnungsnummer', h.customer || 'Kunde',
        h.net || 'Netto', h.vatRate || 'USt-Satz %', h.vat || 'USt-Betrag', h.gross || 'Brutto',
        h.currency || 'Währung', h.status || 'Status', h.paid || 'Zahldatum', h.type || 'Art',
    ])];
    const list = realizedInvoices(invoices)
        .filter((i) => yearOf(i.issueDate) === String(year))
        .sort((a, b) => String(a.issueDate).localeCompare(String(b.issueDate)));
    for (const inv of list) {
        const t = invoiceTotals(inv);
        const rates = Object.keys(t.vatByRate).map(Number).sort((a, b) => a - b);
        rows.push(line([
            inv.issueDate || '', inv.number || '', (inv.customer && inv.customer.name) || '',
            num(t.net), rates.join('/'), num(t.vat), num(t.gross),
            inv.currency || 'EUR', inv.status || '', inv.paidAt || '',
            inv.type === 'credit' ? (h.credit || 'Gutschrift') : (h.invoice || 'Rechnung'),
        ]));
    }
    return withBom(rows.join('\r\n'));
}

/**
 * Expense journal for a year: one row per receipt (attached to a bank transaction) plus
 * manual project expenses. Net/VAT derived from each receipt's detected rate.
 */
export function buildExpenseCsv(transactions, projects, year, headers) {
    const h = headers || {};
    const rows = [line([
        h.date || 'Belegdatum', h.merchant || 'Empfänger/Beleg', h.category || 'Kategorie',
        h.gross || 'Brutto', h.vatRate || 'USt-Satz %', h.vat || 'Vorsteuer', h.net || 'Netto', h.account || 'Konto',
    ])];
    const out = [];
    for (const tx of transactions || []) {
        for (const r of tx.receipts || []) {
            if (r.trashed) continue;
            const dt = String(r.date || tx.date || '');
            if (yearOf(dt) !== String(year)) continue;
            const gross = Number(r.total) || 0; if (! gross) continue;
            const rate = Number(r.vat) || 0;
            const vat = rate ? gross * rate / (100 + rate) : 0;
            const cats = Array.isArray(r.categories) ? r.categories : (r.category ? [r.category] : []);
            out.push({ dt, name: r.merchant || r.name || '', cat: cats.join(', '), gross, rate, vat, net: gross - vat, account: tx.counterparty || '' });
        }
    }
    for (const p of projects || []) {
        for (const ex of p.expenses || []) {
            const dt = String(ex.date || '');
            if (yearOf(dt) !== String(year)) continue;
            const gross = Number(ex.amount) || 0; if (! gross) continue;
            out.push({ dt, name: ex.note || p.name || '', cat: ex.category || '', gross, rate: 0, vat: 0, net: gross, account: (h.manual || 'Projekt') });
        }
    }
    out.sort((a, b) => a.dt.localeCompare(b.dt));
    for (const e of out) rows.push(line([e.dt, e.name, e.cat, num(e.gross), e.rate || '', num(e.vat), num(e.net), e.account]));
    return withBom(rows.join('\r\n'));
}
