import { describe, it, expect } from 'vitest';
import {
    FAST_TEMPLATES, activeFast, fastElapsedSeconds, fastTargetSeconds,
    fastProgress, formatDuration, formatDurationHMS, templateLabel, isValidFast, normalizeFasts,
} from '../shared/health-fasting.js';

const T0 = Date.parse('2026-07-24T08:00:00Z');

describe('health-fasting', () => {
    it('ships the common protocols', () => {
        expect(FAST_TEMPLATES.map((t) => t.key)).toContain('16:8');
        expect(FAST_TEMPLATES.find((t) => t.key === '16:8').targetHours).toBe(16);
    });

    it('finds the single active fast (end === null)', () => {
        const fasts = [
            { id: 'a', start: '2026-07-20T08:00:00Z', end: '2026-07-20T22:00:00Z', targetHours: 14 },
            { id: 'b', start: '2026-07-24T08:00:00Z', end: null, targetHours: 16 },
        ];
        expect(activeFast(fasts).id).toBe('b');
        expect(activeFast([])).toBeNull();
        expect(activeFast(null)).toBeNull();
    });

    it('computes elapsed to now for a running fast, to end for a finished one', () => {
        const running = { start: '2026-07-24T08:00:00Z', end: null };
        expect(fastElapsedSeconds(running, T0 + 3600_000)).toBe(3600);
        const done = { start: '2026-07-24T08:00:00Z', end: '2026-07-24T10:00:00Z' };
        expect(fastElapsedSeconds(done, T0 + 99_000_000)).toBe(7200);
        expect(fastElapsedSeconds(null)).toBe(0);
    });

    it('reports progress + goal reached', () => {
        const f = { start: '2026-07-24T08:00:00Z', end: null, targetHours: 16 };
        const mid = fastProgress(f, T0 + 8 * 3600_000);
        expect(mid.target).toBe(16 * 3600);
        expect(mid.fraction).toBeCloseTo(0.5, 5);
        expect(mid.reached).toBe(false);
        const hit = fastProgress(f, T0 + 16 * 3600_000);
        expect(hit.reached).toBe(true);
        const over = fastProgress(f, T0 + 20 * 3600_000);
        expect(over.reached).toBe(true);
        expect(over.fraction).toBeGreaterThan(1);
    });

    it('formats durations as Xh MMm', () => {
        expect(formatDuration(0)).toBe('0h 00m');
        expect(formatDuration(5040)).toBe('1h 24m');
        expect(formatDuration(16 * 3600)).toBe('16h 00m');
    });

    it('formats the live timer as HH:MM:SS', () => {
        expect(formatDurationHMS(0)).toBe('00:00:00');
        expect(formatDurationHMS(5045)).toBe('01:24:05');
        expect(formatDurationHMS(16 * 3600 + 3)).toBe('16:00:03');
    });

    it('labels a window from its fasting hours', () => {
        expect(templateLabel(16)).toBe('16:8');
        expect(templateLabel(20)).toBe('20:4');
        expect(templateLabel(13)).toBe('13:11'); // non-preset still labelled
        expect(templateLabel(0)).toBe('');
    });

    it('validates fasts', () => {
        expect(isValidFast({ start: '2026-07-24T08:00:00Z', end: null, targetHours: 16 })).toBe(true);
        expect(isValidFast({ start: '2026-07-24T08:00:00Z', end: '2026-07-24T10:00:00Z', targetHours: 16 })).toBe(true);
        expect(isValidFast({ start: '2026-07-24T10:00:00Z', end: '2026-07-24T08:00:00Z', targetHours: 16 })).toBe(false); // end before start
        expect(isValidFast({ start: '', targetHours: 16 })).toBe(false);
        expect(isValidFast({ start: '2026-07-24T08:00:00Z', targetHours: 0 })).toBe(false);
        expect(isValidFast({ start: '2026-07-24T08:00:00Z', targetHours: 99 })).toBe(false);
    });

    it('fastTargetSeconds handles unset target', () => {
        expect(fastTargetSeconds({ targetHours: 16 })).toBe(57600);
        expect(fastTargetSeconds({})).toBe(0);
    });

    describe('normalizeFasts — single-active invariant after a merge race', () => {
        it('leaves a single active fast untouched', () => {
            const fasts = [{ id: 'a', start: '2026-07-24T08:00:00Z', end: null }];
            expect(normalizeFasts(fasts)).toBe(false);
            expect(activeFast(fasts).id).toBe('a');
        });

        it('voids the later of two concurrently-started active fasts, keeping the earliest', () => {
            const fasts = [
                { id: 'b', start: '2026-07-24T09:00:00Z', end: null }, // later start
                { id: 'a', start: '2026-07-24T08:00:00Z', end: null }, // earlier start — kept
            ];
            expect(normalizeFasts(fasts)).toBe(true);
            expect(fasts.filter((f) => ! f.end)).toHaveLength(1);
            expect(activeFast(fasts).id).toBe('a');
            // The duplicate is voided (zero-length), not deleted — no data loss.
            expect(fasts.find((f) => f.id === 'b').end).toBe('2026-07-24T09:00:00Z');
        });

        it('is deterministic across clients (same input → same choice) via id tie-break', () => {
            const mk = () => [
                { id: 'y', start: '2026-07-24T08:00:00Z', end: null },
                { id: 'x', start: '2026-07-24T08:00:00Z', end: null }, // same start, smaller id kept
            ];
            const one = mk(); normalizeFasts(one);
            const two = mk().reverse(); normalizeFasts(two);
            expect(activeFast(one).id).toBe('x');
            expect(activeFast(two).id).toBe('x');
        });

        it('does not lose the completed history', () => {
            const fasts = [
                { id: 'done', start: '2026-07-23T08:00:00Z', end: '2026-07-23T20:00:00Z' },
                { id: 'a', start: '2026-07-24T08:00:00Z', end: null },
                { id: 'b', start: '2026-07-24T09:00:00Z', end: null },
            ];
            normalizeFasts(fasts);
            expect(fasts).toHaveLength(3); // nothing deleted
            expect(fasts.filter((f) => ! f.end)).toHaveLength(1);
        });
    });
});
