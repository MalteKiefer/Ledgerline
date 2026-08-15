// Bank-statement CSV import (client-side parsing). Handles the semicolon-delimited
// German export format (Buchungsdatum;...;Betrag;IBAN;Verwendungszweck;...) as well as
// generic comma-delimited exports with English headers. Pure + testable.
//
// The date parser is the critical piece: it MUST try ISO (YYYY-MM-DD) FIRST. An
// earlier version tried a day-first DD.MM.YYYY regex first, unanchored — on an ISO
// date "2025-06-27" that regex still matched (starting mid-string, at the "25-06-27"
// tail), reading it as day=25/month=06/year=27 and silently transposing the real day
// and year into garbage dates. Every date MUST be validated as a real calendar date
// before being accepted, not just pattern-matched.

export interface ParsedBankRow {
  date: string; // ISO yyyy-mm-dd
  amount: number;
  counterparty: string;
  counterparty_iban: string;
  purpose: string;
  sig: string; // stable de-dup signature — re-importing the same statement skips rows already present
}

/** Parse a German/EN amount ("1.234,56", "150,00", "-24,80", "9.99") -> number, or null. */
export function parseAmount(raw: string): number | null {
  let s = String(raw ?? '').replace(/[^\d.,-]/g, '');
  if (!s) return null;
  const lastComma = s.lastIndexOf(',');
  const lastDot = s.lastIndexOf('.');
  if (lastComma > lastDot) s = s.replace(/\./g, '').replace(',', '.'); // "1.234,56" -> "1234.56"
  else if (lastDot > lastComma) s = s.replace(/,/g, ''); // "1,234.56" -> "1234.56"
  else s = s.replace(',', '.'); // only a comma present -> decimal separator
  const n = Number(s);
  return Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
}

const validCalendarDate = (y: number, mo: number, d: number): boolean => {
  if (mo < 1 || mo > 12 || d < 1 || d > 31 || y < 1970 || y > 2100) return false;
  // Reject an impossible day-of-month (e.g. 31 Feb) rather than silently accepting it.
  const dt = new Date(Date.UTC(y, mo - 1, d));
  return dt.getUTCFullYear() === y && dt.getUTCMonth() === mo - 1 && dt.getUTCDate() === d;
};

/**
 * A single date cell -> ISO yyyy-mm-dd, or null. ISO (yyyy-mm-dd, anchored) is tried
 * FIRST — this must never fall through to the day-first heuristic for an ISO date, or
 * the day/year get transposed (see module docblock). Day-first (DD.MM.YYYY /
 * DD/MM/YYYY / DD-MM-YYYY) is the fallback for non-ISO bank exports.
 */
export function parseDate(raw: string): string | null {
  const s = String(raw ?? '').trim();
  if (!s) return null;

  const iso = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ]|$)/);
  if (iso) {
    const y = Number(iso[1]); const mo = Number(iso[2]); const d = Number(iso[3]);
    if (validCalendarDate(y, mo, d)) return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    return null;
  }

  const dayFirst = s.match(/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})$/);
  if (dayFirst) {
    const d = Number(dayFirst[1]); const mo = Number(dayFirst[2]);
    const y = dayFirst[3].length === 2 ? 2000 + Number(dayFirst[3]) : Number(dayFirst[3]);
    if (validCalendarDate(y, mo, d)) return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    return null;
  }

  return null;
}

/** A short, stable de-dup key for a row — re-importing the same statement should skip it. */
export function rowSignature(row: { date: string; amount: number; counterparty: string; purpose: string }): string {
  return `${row.date}|${row.amount.toFixed(2)}|${row.counterparty}|${row.purpose}`.slice(0, 80);
}

function splitCsvLine(line: string, delim: string): string[] {
  // Minimal quoted-CSV split: fields are wrapped in "…", commas/semicolons inside
  // quotes don't split, "" is an escaped quote. Good enough for bank exports (no
  // embedded newlines inside a field).
  const out: string[] = [];
  let cur = ''; let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const c = line[i];
    if (inQuotes) {
      if (c === '"') {
        if (line[i + 1] === '"') { cur += '"'; i++; } else { inQuotes = false; }
      } else cur += c;
    } else if (c === '"') inQuotes = true;
    else if (c === delim) { out.push(cur); cur = ''; }
    else cur += c;
  }
  out.push(cur);
  return out.map((c) => c.trim());
}

function columnIndex(header: string[], names: string[]): number {
  const lower = header.map((h) => h.toLowerCase());
  return lower.findIndex((h) => names.some((n) => h.includes(n)));
}

/**
 * Parse a bank-statement CSV export into rows the bulk-import endpoint accepts.
 * Auto-detects the delimiter (`;` vs `,`) and the date/amount/counterparty/IBAN/
 * purpose columns by header keyword. A row without a valid date or amount is
 * skipped (never guessed) — `skipped` counts how many.
 */
export function parseBankCsv(text: string): { rows: ParsedBankRow[]; skipped: number } {
  const lines = String(text ?? '').split(/\r?\n/).filter((l) => l.trim() !== '');
  if (lines.length < 2) return { rows: [], skipped: 0 };

  const delim = (lines[0].match(/;/g)?.length ?? 0) >= (lines[0].match(/,/g)?.length ?? 0) ? ';' : ',';
  const header = splitCsvLine(lines[0], delim);

  // "Buchungsdatum" (booking date) is preferred over "Wertstellungsdatum" (value
  // date) when both are present — both contain "datum", so prefer an EXACT/prefix
  // match on "buchungsdatum" first before the generic "datum"/"date" substring.
  const di = columnIndex(header, ['buchungsdatum']) >= 0
    ? columnIndex(header, ['buchungsdatum'])
    : columnIndex(header, ['datum', 'date', 'buchung']);
  const ai = columnIndex(header, ['betrag', 'amount', 'value']);
  const ci = columnIndex(header, ['empfänger', 'empfaenger', 'auftraggeber', 'counterparty', 'name', 'beguenstigter', 'begünstigter', 'zahlungspflichtiger']);
  const ii = columnIndex(header, ['iban']);
  const pi = columnIndex(header, ['verwendungszweck', 'verwendung', 'purpose', 'reference', 'zweck']);

  const rows: ParsedBankRow[] = [];
  let skipped = 0;
  for (let i = 1; i < lines.length; i++) {
    const c = splitCsvLine(lines[i], delim);
    const date = di >= 0 ? parseDate(c[di] ?? '') : null;
    const amount = ai >= 0 ? parseAmount(c[ai] ?? '') : null;
    if (date === null || amount === null) { skipped++; continue; }
    const row = {
      date,
      amount,
      counterparty: ci >= 0 ? (c[ci] ?? '') : '',
      counterparty_iban: ii >= 0 ? (c[ii] ?? '') : '',
      purpose: pi >= 0 ? (c[pi] ?? '') : '',
    };
    rows.push({ ...row, sig: rowSignature(row) });
  }
  return { rows, skipped };
}
