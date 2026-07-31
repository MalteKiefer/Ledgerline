import { describe, expect, it } from 'vitest';
import { daysUntil, computeAge, upcomingBirthdays, yearsAgoPhotos, sortTodos } from '../shared/dashboard-utils';

describe('daysUntil', () => {
  it('0 for today, positive for later, wraps year end', () => {
    expect(daysUntil('03-15', '2026-03-15')).toBe(0);
    expect(daysUntil('03-18', '2026-03-15')).toBe(3);
    expect(daysUntil('01-01', '2026-12-30')).toBe(2);
  });
  it('Feb-29 maps to Feb-28 in a non-leap year', () => {
    expect(daysUntil('02-29', '2026-02-28')).toBe(0); // 2026 not leap
  });
});

describe('computeAge', () => {
  it('age turned on the next birthday; null without a year', () => {
    expect(computeAge('1990-03-20', '2026-03-15')).toBe(36); // turns 36 on 2026-03-20
    expect(computeAge('1990-03-10', '2026-03-15')).toBe(37); // already passed → next is 2027
    expect(computeAge('--03-20', '2026-03-15')).toBeNull();
    expect(computeAge('', '2026-03-15')).toBeNull();
  });
});

describe('upcomingBirthdays', () => {
  const contacts = [
    { id: 'a', displayName: 'A', bday: '1990-03-18' },
    { id: 'b', displayName: 'B', anniversary: '2015-03-16' },
    { id: 'c', displayName: 'C', bday: '1980-11-01' }, // far away
    { id: 'd', displayName: 'D' }, // no dates
  ];
  it('returns within-window events sorted by days-until', () => {
    const r = upcomingBirthdays(contacts, '2026-03-15', 30);
    expect(r.map((x) => x.id)).toEqual(['b', 'a']); // b in 1 day, a in 3
    expect(r[0].kind).toBe('anniversary');
    expect(r[1].turning).toBe(36);
  });
});

describe('yearsAgoPhotos', () => {
  const photos = [
    { id: 'p1', taken_at: '2022-03-15T10:00:00Z' },
    { id: 'p2', created: '2019-03-15T08:00:00Z' },
    { id: 'p3', taken_at: '2026-03-15T00:00:00Z' }, // this year → excluded
    { id: 'p4', taken_at: '2020-06-01T00:00:00Z' }, // wrong day → excluded
  ];
  it('groups same month/day prior-year photos by years-ago', () => {
    const g = yearsAgoPhotos(photos, '2026-03-15');
    expect(g.map((x) => x.yearsAgo)).toEqual([4, 7]); // 2022 (4y), 2019 (7y)
    expect(g[0].photos[0].id).toBe('p1');
  });
});

describe('sortTodos', () => {
  it('overdue first, then dated, undated last; done removed', () => {
    const t = [
      { id: '1', done: false, due: '2026-03-20' },
      { id: '2', done: false, due: '2026-03-01' }, // overdue
      { id: '3', done: true, due: '2026-03-02' },
      { id: '4', done: false },                    // undated
    ];
    expect(sortTodos(t, '2026-03-15').map((x) => x.id)).toEqual(['2', '1', '4']);
  });
});
