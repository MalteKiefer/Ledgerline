import { describe, it, expect } from 'vitest';
import { FINANCE_ICONS, FINANCE_ICON_NAMES, FINANCE_COLORS, catIconPath, DEFAULT_CAT_ICON } from '../shared/finance-icons';

describe('finance-icons', () => {
    it('offers at least 150 distinct monochrome icons', () => {
        expect(FINANCE_ICON_NAMES.length).toBeGreaterThanOrEqual(150);
        expect(new Set(FINANCE_ICON_NAMES).size).toBe(FINANCE_ICON_NAMES.length);
    });

    it('every icon is a single valid SVG path (starts with a move command)', () => {
        for (const name of FINANCE_ICON_NAMES) {
            expect(typeof FINANCE_ICONS[name]).toBe('string');
            expect(FINANCE_ICONS[name]).toMatch(/^[Mm]/);
        }
    });

    it('catIconPath falls back to the default for an unknown icon', () => {
        expect(catIconPath('does-not-exist')).toBe(FINANCE_ICONS[DEFAULT_CAT_ICON]);
        expect(catIconPath('hashtag')).toBe(FINANCE_ICONS.hashtag);
    });

    it('exposes a non-empty colour palette of unique hex values', () => {
        expect(FINANCE_COLORS.length).toBeGreaterThan(8);
        expect(new Set(FINANCE_COLORS).size).toBe(FINANCE_COLORS.length);
        for (const hex of FINANCE_COLORS) expect(hex).toMatch(/^#[0-9a-f]{6}$/i);
    });
});
