import { describe, it, expect } from 'vitest';
import { parseUnsubscribe } from '../unsubscribe';

describe('parseUnsubscribe', () => {
  it('reads both target kinds and shows what they are', () => {
    const out = parseUnsubscribe('From: a@b.c\nList-Unsubscribe: <mailto:leave@list.example>, <https://news.example/u/abc>\n');
    expect(out).toEqual([
      { kind: 'mailto', value: 'mailto:leave@list.example', label: 'leave@list.example' },
      { kind: 'http', value: 'https://news.example/u/abc', label: 'news.example' },
    ]);
  });

  it('handles a header folded across lines', () => {
    // Real newsletters wrap long headers; a value continuing on an indented
    // line is one value, not two.
    const out = parseUnsubscribe('List-Unsubscribe:\n <https://news.example/very/long/token>\nSubject: x\n');
    expect(out).toHaveLength(1);
    expect(out[0].label).toBe('news.example');
  });

  it('is case-insensitive about the header name', () => {
    expect(parseUnsubscribe('list-unsubscribe: <mailto:x@y.z>')).toHaveLength(1);
  });

  it('keeps the mailto query so the subject the sender wants survives', () => {
    const out = parseUnsubscribe('List-Unsubscribe: <mailto:leave@l.example?subject=unsubscribe%20abc>');
    expect(out[0].value).toBe('mailto:leave@l.example?subject=unsubscribe%20abc');
    expect(out[0].label).toBe('leave@l.example');
  });

  it('ignores anything that is not a mail address or a web link', () => {
    // javascript: in particular must never become something clickable.
    const out = parseUnsubscribe('List-Unsubscribe: <javascript:alert(1)>, <ftp://x.example>, <mailto:>, <not a url>');
    expect(out).toEqual([]);
  });

  it('returns nothing when there is no such header', () => {
    expect(parseUnsubscribe('Subject: hello\nFrom: a@b.c')).toEqual([]);
    expect(parseUnsubscribe(null)).toEqual([]);
    expect(parseUnsubscribe('')).toEqual([]);
  });
});
