// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { decimalToMinor, formatMinor, minorToDecimal } from '@spa/modules/finance/models/money';

describe('decimalToMinor / minorToDecimal', () => {
  it('converts an exact currency decimal string to its minor-unit integer', () => {
    expect(decimalToMinor('119.00')).toBe(11900);
    expect(decimalToMinor('0')).toBe(0);
    expect(decimalToMinor('5')).toBe(500);
  });

  it('preserves the sign of a negative (refund) amount, including negative zero cents', () => {
    expect(decimalToMinor('-0.01')).toBe(-1);
    expect(decimalToMinor('-50.00')).toBe(-5000);
  });

  it('rejects a float-shaped string with more than two fraction digits', () => {
    expect(() => decimalToMinor('1.001')).toThrow();
    expect(() => decimalToMinor('abc')).toThrow();
    expect(() => decimalToMinor('')).toThrow();
  });

  it('round-trips minorToDecimal back to the canonical two-decimal string', () => {
    expect(minorToDecimal(11900)).toBe('119.00');
    expect(minorToDecimal(0)).toBe('0.00');
    expect(minorToDecimal(-1)).toBe('-0.01');
    expect(minorToDecimal(-5000)).toBe('-50.00');
  });

  it('rejects a non-integer minor amount', () => {
    expect(() => minorToDecimal(1.5)).toThrow();
  });
});

describe('formatMinor', () => {
  it('formats an arbitrary-precision exact minor-unit string beyond Number.MAX_SAFE_INTEGER', () => {
    document.documentElement.lang = 'de';
    const formatted = formatMinor('10718567113141782', 'EUR').normalize('NFKC').replace(/[\s  ]/gu, ' ');
    expect(formatted).toBe('107.185.671.131.417,82 €');
  });

  it('falls back to a plain currency-suffixed string for a non-canonical value', () => {
    expect(formatMinor('not-a-number', 'EUR')).toBe('not-a-number EUR');
  });
});
