import { describe, it, expect } from 'vitest';
import { METRICS, metric, computeAge, computeBmi, kgToLb, lbToKg, cToF, fToC, mgdlToMmoll, mmollToMgdl, classify, csvRows, csvCell } from '../shared/health-metrics.js';

describe('registry', () => {
  it('has the six metrics, bp is dual', () => {
    expect(METRICS.map((m) => m.key)).toEqual(['weight', 'bp', 'pulse', 'spo2', 'temp', 'glucose']);
    expect(metric('bp').dual).toBe(true);
    expect(metric('weight').dual).toBe(false);
    expect(metric('nope')).toBeUndefined();
  });
});

describe('computeAge', () => {
  it('years from birthdate at a fixed now, birthday not yet reached', () => {
    expect(computeAge('1990-12-31', '2026-07-20T00:00:00Z')).toBe(35);
    expect(computeAge('1990-01-01', '2026-07-20T00:00:00Z')).toBe(36);
    expect(computeAge('', '2026-07-20T00:00:00Z')).toBeNull();
    expect(computeAge('garbage', '2026-07-20T00:00:00Z')).toBeNull();
  });
});

describe('computeBmi', () => {
  it('kg / m^2 rounded to 1dp; null on missing', () => {
    expect(computeBmi(80, 180)).toBe(24.7);
    expect(computeBmi(0, 180)).toBeNull();
    expect(computeBmi(80, null)).toBeNull();
  });
});

describe('unit conversions round-trip', () => {
  it('kg<->lb, c<->f, mgdl<->mmoll', () => {
    expect(lbToKg(kgToLb(80))).toBeCloseTo(80, 1);
    expect(fToC(cToF(37))).toBeCloseTo(37, 1);
    expect(mmollToMgdl(mgdlToMmoll(90))).toBeCloseTo(90, 0);
    expect(mgdlToMmoll(90)).toBeCloseTo(5.0, 1);
  });
});

describe('classify', () => {
  it('spo2 bands', () => { expect(classify('spo2', 97)).toBe('ok'); expect(classify('spo2', 93)).toBe('amber'); expect(classify('spo2', 90)).toBe('red'); });
  it('bp uses worst of sys/dia', () => { expect(classify('bp', 118, 78)).toBe('ok'); expect(classify('bp', 145, 80)).toBe('red'); expect(classify('bp', 130, 85)).toBe('amber'); });
  it('pulse band', () => { expect(classify('pulse', 70)).toBe('ok'); expect(classify('pulse', 110)).toBe('amber'); });
  it('temp band', () => { expect(classify('temp', 37)).toBe('ok'); expect(classify('temp', 38.4)).toBe('amber'); expect(classify('temp', 39.2)).toBe('red'); });
  it('weight/glucose always ok', () => { expect(classify('weight', 200)).toBe('ok'); expect(classify('glucose', 400)).toBe('ok'); });
});

describe('csvCell', () => {
  it('returns plain string unchanged', () => {
    expect(csvCell('hello')).toBe('hello');
    expect(csvCell('2026-01-01')).toBe('2026-01-01');
    expect(csvCell('80')).toBe('80');
  });
  it('wraps field containing comma in double-quotes', () => {
    expect(csvCell('hello, world')).toBe('"hello, world"');
  });
  it('wraps field containing double-quote and escapes internal quotes', () => {
    expect(csvCell('say "hi"')).toBe('"say ""hi"""');
  });
  it('wraps field containing newline', () => {
    expect(csvCell('line1\nline2')).toBe('"line1\nline2"');
    expect(csvCell('line1\r\nline2')).toBe('"line1\r\nline2"');
  });
  it('coerces null/undefined to empty string (no quotes needed)', () => {
    expect(csvCell(null)).toBe('');
    expect(csvCell(undefined)).toBe('');
  });
  it('handles empty string', () => {
    expect(csvCell('')).toBe('');
  });
});

describe('csvRows', () => {
  it('header + converted rows sorted by ts', () => {
    const entries = [
      { id: 'a', ts: '2026-01-02T08:00:00Z', metric: 'weight', v: 80, v2: null, note: 'x' },
      { id: 'b', ts: '2026-01-01T08:00:00Z', metric: 'weight', v: 81, v2: null, note: '' },
      { id: 'c', ts: '2026-01-01T08:00:00Z', metric: 'bp', v: 120, v2: 80, note: '' },
    ];
    const rows = csvRows(entries, 'weight', { weight: 'kg', glucose: 'mgdl', temp: 'c' });
    expect(rows[0]).toEqual(['date', 'time', 'value', 'value2', 'unit', 'note']);
    expect(rows).toHaveLength(3); // header + 2 weight entries (bp excluded)
    expect(rows[1][2]).toBe('81'); // earlier ts first
  });
});
