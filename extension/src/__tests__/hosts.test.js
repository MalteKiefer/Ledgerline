import { describe, it, expect } from 'vitest';
import { hostOf, hostsMatch, matchScore } from '../hosts.js';

describe('hostsMatch', () => {
    it('exact match is true', () => {
        expect(hostsMatch('example.com', 'example.com')).toBe(true);
    });
    it('parent→child (stored=parent, page=child) is true', () => {
        expect(hostsMatch('accounts.example.com', 'example.com')).toBe(true);
    });
    it('child→parent (stored=child, page=parent) is false', () => {
        expect(hostsMatch('example.com', 'accounts.example.com')).toBe(false);
    });
    it('bare TLD stored is false', () => {
        expect(hostsMatch('example.com', 'com')).toBe(false);
        expect(hostsMatch('com', 'com')).toBe(false);
    });
    it('www. is stripped from both sides', () => {
        expect(hostsMatch('www.example.com', 'example.com')).toBe(true);
        expect(hostsMatch('example.com', 'www.example.com')).toBe(true);
    });
    it('unrelated domains are false', () => {
        expect(hostsMatch('example.com', 'evil.com')).toBe(false);
    });
    it('empty/null inputs are false', () => {
        expect(hostsMatch('', 'example.com')).toBe(false);
        expect(hostsMatch('example.com', '')).toBe(false);
        expect(hostsMatch(null, 'example.com')).toBe(false);
    });
    it('dot-boundary: suffix without dot boundary is false', () => {
        // notexample.com should NOT match example.com
        expect(hostsMatch('notexample.com', 'example.com')).toBe(false);
    });
    it('bare TLD localhost is false', () => {
        expect(hostsMatch('localhost', 'localhost')).toBe(false);
    });
});

describe('hostOf', () => {
    it('extracts hostname from full URL', () => {
        expect(hostOf('https://example.com/path')).toBe('example.com');
    });
    it('handles URL without scheme', () => {
        expect(hostOf('example.com')).toBe('example.com');
    });
    it('strips www.', () => {
        expect(hostOf('https://www.example.com')).toBe('example.com');
    });
    it('returns empty string for invalid input', () => {
        expect(hostOf('')).toBe('');
    });
});

describe('matchScore', () => {
    it('returns 1 for matching login', () => {
        const lg = { type: 'login', urls: ['https://example.com/login'] };
        expect(matchScore(lg, 'example.com')).toBe(1);
    });
    it('returns 0 for non-login type', () => {
        const lg = { type: 'card', urls: ['https://example.com'] };
        expect(matchScore(lg, 'example.com')).toBe(0);
    });
    it('returns 0 for no URL match', () => {
        const lg = { type: 'login', urls: ['https://other.com'] };
        expect(matchScore(lg, 'example.com')).toBe(0);
    });
});
