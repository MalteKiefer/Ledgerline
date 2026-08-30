export type DecimalString = string;
export type DecimalIntegerString = string;
export type CurrencyCode = string;

export interface MoneyTotals {
  net_minor: DecimalIntegerString;
  vat_minor: DecimalIntegerString;
  gross_minor: DecimalIntegerString;
  currency: CurrencyCode;
}

export interface TaxBreakdown {
  tax_rate_basis_points: DecimalIntegerString;
  net_minor: DecimalIntegerString;
  vat_minor: DecimalIntegerString;
  gross_minor: DecimalIntegerString;
}

export interface CalculatedTotals extends MoneyTotals {
  discount_minor: DecimalIntegerString;
  tax_breakdowns: TaxBreakdown[];
}

/**
 * Exact decimal-string <-> minor-unit-integer boundary helpers. These exist
 * so a form can compute a provisional client-side total for immediate
 * feedback without ever going through a float: `parseFloat('0.1') + 0.2`
 * is not `0.3`, and an invoice total is money, not a float approximation of
 * money. The server always recalculates and returns the authoritative
 * minor-unit totals; a value produced here must never be sent back as if it
 * were authoritative (see `control_*_minor` fields, which exist only to let
 * the server assert the client's arithmetic matched, never to supply it).
 */
export function decimalToMinor(value: DecimalString): number {
  const match = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(value.trim());
  if (!match) throw new Error(`Not an exact decimal string: ${value}`);
  const [, sign, whole, fraction] = match;
  const scaled = `${whole}${(fraction ?? '').padEnd(2, '0')}`;
  const minor = Number(scaled);
  if (!Number.isSafeInteger(minor)) throw new Error(`Decimal exceeds the safe integer range: ${value}`);

  return sign === '-' && minor !== 0 ? -minor : minor;
}

export function minorToDecimal(minor: number): DecimalIntegerString {
  if (!Number.isInteger(minor)) throw new Error(`Not an integer minor-unit amount: ${minor}`);
  const negative = minor < 0;
  const digits = Math.abs(minor).toString().padStart(3, '0');
  const whole = digits.slice(0, -2);
  const fraction = digits.slice(-2);

  return `${negative ? '-' : ''}${whole}.${fraction}`;
}

/**
 * Locale-formatted display of an exact minor-unit integer STRING (as returned
 * by the server — arbitrary precision, safe past Number.MAX_SAFE_INTEGER).
 * Never round-trips through decimalToMinor/minorToDecimal, which are for
 * provisional client-computed amounts within the JS safe-integer range only.
 */
export function formatMinor(value: DecimalIntegerString, currency: CurrencyCode): string {
  if (!/^-?(?:0|[1-9][0-9]*)$/.test(value)) return `${value} ${currency}`;
  const negative = value.startsWith('-');
  const digits = (negative ? value.slice(1) : value).padStart(3, '0');
  const integer = digits.slice(0, -2);
  const fraction = digits.slice(-2);
  const locale = document.documentElement.lang || 'de-DE';

  try {
    const formatter = new Intl.NumberFormat(locale, { style: 'currency', currency, minimumFractionDigits: 2 });
    const group = formatter.formatToParts(1000).find((part) => part.type === 'group')?.value ?? '.';
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, group);
    let usedInteger = false;

    return formatter.formatToParts(negative ? -0.01 : 0.01).map((part) => {
      if (part.type === 'integer') {
        if (usedInteger) return '';
        usedInteger = true;

        return grouped;
      }
      if (part.type === 'fraction') return fraction;
      if (part.type === 'group') return '';

      return part.value;
    }).join('');
  } catch {
    return `${negative ? '-' : ''}${integer}.${fraction} ${currency}`;
  }
}
