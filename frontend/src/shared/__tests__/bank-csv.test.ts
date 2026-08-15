import { describe, it, expect } from 'vitest';
import { parseAmount, parseDate, rowSignature, parseBankCsv } from '../bank-csv';

describe('parseAmount', () => {
  it('parses German decimal-comma amounts', () => { expect(parseAmount('150,00')).toBe(150); });
  it('parses a negative amount', () => { expect(parseAmount('-24,80')).toBe(-24.8); });
  it('parses a thousands-grouped German amount', () => { expect(parseAmount('6683,00')).toBe(6683); });
  it('parses an English decimal-point amount', () => { expect(parseAmount('1234.56')).toBe(1234.56); });
  it('parses a thousands-grouped English amount', () => { expect(parseAmount('1,234.56')).toBe(1234.56); });
  it('returns null for empty input', () => { expect(parseAmount('')).toBeNull(); });
});

describe('parseDate', () => {
  // Regression: the previous implementation ran an unanchored day-first regex
  // FIRST, which still matched inside an ISO date (skipping the leading 2 digits
  // of the year) and silently transposed day<->year. Every real bank_transactions
  // row imported through that bug landed on a garbage date (verified against
  // production: 351/351 imported rows corrupted, e.g. real 2025-06-27 became
  // 2027-06-25). ISO must always win when the cell IS ISO.
  it('parses ISO yyyy-mm-dd without transposing day/year', () => {
    expect(parseDate('2025-06-27')).toBe('2025-06-27');
  });
  it('parses ISO dates across the whole real export range without corruption', () => {
    expect(parseDate('2024-08-26')).toBe('2024-08-26');
    expect(parseDate('2026-08-07')).toBe('2026-08-07');
    expect(parseDate('2025-03-17')).toBe('2025-03-17');
  });
  it('parses day-first DD.MM.YYYY (non-ISO exports)', () => {
    expect(parseDate('27.06.2025')).toBe('2025-06-27');
  });
  it('parses day-first DD.MM.YY (2-digit year)', () => {
    expect(parseDate('27.06.25')).toBe('2025-06-27');
  });
  it('parses day-first with slash separators', () => {
    expect(parseDate('27/06/2025')).toBe('2025-06-27');
  });
  it('rejects an impossible calendar date (31 Feb)', () => {
    expect(parseDate('2025-02-31')).toBeNull();
  });
  it('rejects garbage', () => { expect(parseDate('not a date')).toBeNull(); });
  it('rejects empty input', () => { expect(parseDate('')).toBeNull(); });
});

describe('rowSignature', () => {
  it('is stable for identical rows', () => {
    const row = { date: '2025-06-27', amount: -9.99, counterparty: 'APPLE.COM/BILL', purpose: '' };
    expect(rowSignature(row)).toBe(rowSignature({ ...row }));
  });
  it('differs when the date differs', () => {
    const a = { date: '2025-06-27', amount: -9.99, counterparty: 'APPLE.COM/BILL', purpose: '' };
    const b = { ...a, date: '2025-06-28' };
    expect(rowSignature(a)).not.toBe(rowSignature(b));
  });
});

describe('parseBankCsv', () => {
  // The real export header + a representative slice of real rows (August 2026
  // production incident fixture) — semicolon-delimited, German headers, ISO dates,
  // decimal-comma amounts, some rows with no IBAN/purpose.
  const HEADER = '"Buchungsdatum";"Wertstellungsdatum";"Transaktionstyp";"Empfänger";"Betrag";"IBAN";"Verwendungszweck";"end_to_end_id";"Buchungsstatus";"Kategorie";"Persönliche Notiz"';
  const ROWS = [
    '"2024-08-26";"2024-08-26";"Echtzeitüberweisung";"Malte Kiefer Margareta Kiefer";"150,00";"DE97771500000101918993";"Privateinlage";;"Gebucht";"Privat";"Privateinlage"',
    '"2024-08-28";"2024-08-28";"Kartenzahlung";"Grover DE GmbH";"-24,80";;;;"Gebucht";"Umsatzsteuer 19%";',
    '"2025-03-15";"2025-03-15";"Kartenzahlung";"APPLE.COM/BILL";"-9,99";;;;"Gebucht";"Umsatzsteuer 19%";',
    '"2026-08-07";"2026-08-07";"Kartenzahlung";"BACKBLAZE INC";"-0,47";;;;"Gebucht";;',
  ].join('\n');

  it('parses every real row with the correct ISO date (no transposition)', () => {
    const { rows, skipped } = parseBankCsv(`${HEADER}\n${ROWS}`);
    expect(skipped).toBe(0);
    expect(rows).toHaveLength(4);
    expect(rows[0]).toMatchObject({ date: '2024-08-26', amount: 150, counterparty: 'Malte Kiefer Margareta Kiefer', counterparty_iban: 'DE97771500000101918993' });
    expect(rows[1]).toMatchObject({ date: '2024-08-28', amount: -24.8, counterparty: 'Grover DE GmbH' });
    expect(rows[2]).toMatchObject({ date: '2025-03-15', amount: -9.99, counterparty: 'APPLE.COM/BILL' });
    expect(rows[3]).toMatchObject({ date: '2026-08-07', amount: -0.47, counterparty: 'BACKBLAZE INC' });
  });
  it('assigns each row a non-empty dedup signature', () => {
    const { rows } = parseBankCsv(`${HEADER}\n${ROWS}`);
    for (const r of rows) expect(r.sig.length).toBeGreaterThan(0);
    // Distinct rows -> distinct signatures.
    expect(new Set(rows.map((r) => r.sig)).size).toBe(rows.length);
  });
  it('prefers Buchungsdatum over Wertstellungsdatum when both are present', () => {
    // Row where the two date columns differ — must read column 0 (Buchungsdatum).
    const csv = `${HEADER}\n"2025-01-01";"2025-01-05";"Kartenzahlung";"Foo";"-1,00";;;;"Gebucht";;`;
    const { rows } = parseBankCsv(csv);
    expect(rows[0].date).toBe('2025-01-01');
  });
  it('skips a row with no parseable date or amount instead of guessing', () => {
    const csv = `${HEADER}\n"not-a-date";"2025-01-05";"Kartenzahlung";"Foo";"-1,00";;;;"Gebucht";;`;
    const { rows, skipped } = parseBankCsv(csv);
    expect(rows).toHaveLength(0);
    expect(skipped).toBe(1);
  });
  it('returns empty for a header-only or empty file', () => {
    expect(parseBankCsv('').rows).toEqual([]);
    expect(parseBankCsv(HEADER).rows).toEqual([]);
  });
});
