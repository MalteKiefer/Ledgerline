import { describe, it, expect } from 'vitest';
import { estimateStrength } from '../shared/strength.js';

describe('estimateStrength', () => {
    it('rates a trivial password weak (<=2)', async () => {
        const r = await estimateStrength('password1');
        expect(r.score).toBeLessThanOrEqual(2);
        expect(typeof r.crackTimeDisplay).toBe('string');
    });
    it('rates a long random passphrase strong (>=3)', async () => {
        const r = await estimateStrength('correct-horse-battery-staple-9f3Qx!');
        expect(r.score).toBeGreaterThanOrEqual(3);
    });
});
