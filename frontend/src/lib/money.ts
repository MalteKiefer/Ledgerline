/**
 * Money in and out of the UI.
 *
 * Two separate problems, both of which used to be handled ad hoc:
 *
 * 1. **Display.** Every call site built its own `Intl.NumberFormat` from
 *    `document.documentElement.lang`, which carries a bare language subtag
 *    ('de'). That is enough for grouping, but a bare tag is a weak contract —
 *    and if the tag is ever wrong or missing, the fallback silently became
 *    English, i.e. "€11,708.24" where German wants "11.708,24 €". The locale is
 *    resolved here, once, and mapped to a full BCP-47 tag.
 *
 * 2. **Input.** A German user types "11.708,24". `<input type="number">` is
 *    specified to hold a dot-decimal, so a comma either never reaches the model
 *    or arrives as a string `Number()` turns into NaN — the amount was refused
 *    or silently dropped. `parseMoney` accepts both conventions (and thousands
 *    separators) so what the owner types is what gets stored.
 */

const LOCALES: Record<string, string> = { de: 'de-DE', en: 'en-GB', ru: 'ru-RU' };

/** The active UI locale as a full BCP-47 tag; German is the fallback, not English. */
export function moneyLocale(): string {
  const lang = (document.documentElement.getAttribute('lang') || '').slice(0, 2).toLowerCase();
  return LOCALES[lang] ?? LOCALES.de;
}

/** Format for reading: "11.708,24 €" in German, "€11,708.24" in English. */
export function fmtMoney(n: number, currency = 'EUR'): string {
  const value = Number.isFinite(n) ? n : 0;
  try {
    return new Intl.NumberFormat(moneyLocale(), { style: 'currency', currency: currency || 'EUR' }).format(value);
  } catch {
    // An unknown currency code would throw — still show the number.
    return `${value.toFixed(2)} ${currency || 'EUR'}`;
  }
}

/**
 * Parse what a human typed into a number, or null when there is no number in
 * it. Accepts both conventions and either separator as the thousands mark:
 *
 *   "1.234,56" -> 1234.56    "1,234.56" -> 1234.56
 *   "11708,24" -> 11708.24   "-19,99"   -> -19.99
 *   "1 234,56 €" -> 1234.56  ""         -> null
 *
 * With BOTH separators present the last one is the decimal mark — unambiguous.
 * With only one, the active UI locale decides: its own decimal mark reads as a
 * decimal, the other one as a thousands separator, but only when exactly three
 * digits follow. So a German "1.234" is 1234 while "1.5" is still 1.5, because
 * reading that as a thousands mark would turn it into 15.
 *
 * (`shared/bank-csv.ts` keeps its own, locale-independent rule: a CSV is written
 * by a bank, not by the person looking at this UI.)
 */
export function parseMoney(raw: string | number | null | undefined): number | null {
  if (typeof raw === 'number') return Number.isFinite(raw) ? Math.round(raw * 100) / 100 : null;
  let s = String(raw ?? '').replace(/[^\d.,-]/g, '');
  if (!s || !/\d/.test(s)) return null;
  // A minus only counts leading; "1-2" is not a number.
  const negative = s.startsWith('-');
  s = s.replace(/-/g, '');
  const lastComma = s.lastIndexOf(',');
  const lastDot = s.lastIndexOf('.');
  if (lastComma >= 0 && lastDot >= 0) {
    // Both present: the later one is the decimal mark.
    if (lastComma > lastDot) s = s.replace(/\./g, '').replace(',', '.');
    else s = s.replace(/,/g, '');
  } else if (lastComma >= 0 || lastDot >= 0) {
    const sep = lastComma >= 0 ? ',' : '.';
    const decimalMark = moneyLocale().startsWith('en') ? '.' : ',';
    const tail = s.slice(s.lastIndexOf(sep) + 1);
    if (sep !== decimalMark && tail.length === 3) s = s.split(sep).join(''); // thousands
    else s = s.replace(sep, '.');
  }
  const n = Number(s);
  if (!Number.isFinite(n)) return null;
  return Math.round((negative ? -n : n) * 100) / 100;
}

/**
 * A stored number as text for a money input: German gets "11708,24" so the
 * field reads the way the owner writes it. No thousands separators — they turn
 * an editable field into a fight with the caret.
 */
export function moneyInput(n: number | string | null | undefined): string {
  if (n == null || n === '') return '';
  const value = typeof n === 'number' ? n : parseMoney(n);
  if (value == null) return '';
  const text = String(value);
  return moneyLocale().startsWith('en') ? text : text.replace('.', ',');
}
