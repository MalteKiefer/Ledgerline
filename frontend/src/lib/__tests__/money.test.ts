// @vitest-environment jsdom

import { describe, expect, it, afterEach } from 'vitest';
import { fmtMoney, moneyInput, moneyLocale, parseMoney } from '../money';

function setLang(lang: string | null) {
  if (lang == null) document.documentElement.removeAttribute('lang');
  else document.documentElement.setAttribute('lang', lang);
}
afterEach(() => setLang('en'));

describe('moneyLocale', () => {
  it('maps the UI language to a full tag and falls back to German, not English', () => {
    setLang('de');
    expect(moneyLocale()).toBe('de-DE');
    setLang('ru');
    expect(moneyLocale()).toBe('ru-RU');
    // A missing/unknown tag must not silently produce English money.
    setLang(null);
    expect(moneyLocale()).toBe('de-DE');
    setLang('xx');
    expect(moneyLocale()).toBe('de-DE');
  });
});

describe('fmtMoney', () => {
  it('formats German with dot thousands, comma decimals and a trailing symbol', () => {
    setLang('de');
    // The reported bug was "€11,708.24" under a German UI.
    const out = fmtMoney(11708.24);
    expect(out).toContain('11.708,24');
    expect(out).not.toContain('11,708.24');
  });

  it('formats English the English way', () => {
    setLang('en');
    expect(fmtMoney(11708.24)).toContain('11,708.24');
  });

  it('formats a decimal-cast string, which is how the API sends money', () => {
    // Laravel's decimal:2 cast serialises as a string; treating that as
    // "not a finite number" showed every transaction as 0,00 once.
    setLang('de');
    expect(fmtMoney('-33.49')).toContain('33,49');
    expect(fmtMoney('11708.24')).toContain('11.708,24');
    expect(fmtMoney('0.00')).toContain('0,00');
  });

  it('survives a nonsense amount or currency instead of throwing', () => {
    setLang('de');
    expect(fmtMoney(Number.NaN)).toContain('0');
    expect(fmtMoney(null)).toContain('0');
    expect(fmtMoney('abc')).toContain('0');
    expect(fmtMoney(5, 'NOTACURRENCY')).toBe('5.00 NOTACURRENCY');
  });
});

describe('parseMoney', () => {
  it('reads both conventions when both separators are present', () => {
    expect(parseMoney('1.234,56')).toBe(1234.56);
    expect(parseMoney('1,234.56')).toBe(1234.56);
    expect(parseMoney('11708,24')).toBe(11708.24);
    expect(parseMoney('11708.24')).toBe(11708.24);
  });

  it('lets the UI locale settle a lone separator', () => {
    setLang('de');
    expect(parseMoney('1.234')).toBe(1234);   // German thousands mark
    expect(parseMoney('1,234')).toBe(1.23);   // German decimal (1,234 -> 1.234, cents)
    expect(parseMoney('1.5')).toBe(1.5);      // not 3 digits -> still a decimal
    setLang('en');
    expect(parseMoney('1,234')).toBe(1234);   // English thousands mark
    expect(parseMoney('1.234')).toBe(1.23);   // English decimal, rounded to cents
  });

  it('keeps a leading minus and ignores currency noise and spaces', () => {
    expect(parseMoney('-19,99')).toBe(-19.99);
    expect(parseMoney('1 234,56 €')).toBe(1234.56);
    expect(parseMoney('€ 45')).toBe(45);
  });

  it('returns null for anything without a number, so a blank field stays blank', () => {
    expect(parseMoney('')).toBeNull();
    expect(parseMoney(null)).toBeNull();
    expect(parseMoney(undefined)).toBeNull();
    expect(parseMoney('abc')).toBeNull();
    expect(parseMoney('-')).toBeNull();
    expect(parseMoney(Number.NaN)).toBeNull();
  });

  it('rounds to cents and passes a number through', () => {
    setLang('de');
    expect(parseMoney('1,006')).toBe(1.01);
    expect(parseMoney(19.999)).toBe(20);
  });
});

describe('moneyInput', () => {
  it('writes the decimal mark the way the owner types it', () => {
    setLang('de');
    expect(moneyInput(11708.24)).toBe('11708,24');
    expect(moneyInput('11708.24')).toBe('11708,24');
    setLang('en');
    expect(moneyInput(11708.24)).toBe('11708.24');
  });

  it('leaves an empty value empty rather than showing a zero', () => {
    setLang('de');
    expect(moneyInput(null)).toBe('');
    expect(moneyInput('')).toBe('');
    expect(moneyInput('abc')).toBe('');
    expect(moneyInput(0)).toBe('0');
  });
});
