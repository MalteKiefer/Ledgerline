import { describe, it, expect } from 'vitest';
import { estimateCalories } from '../shared/explore-calories.js';

describe('estimateCalories', () => {
    it('returns null without weight or distance', () => {
        expect(estimateCalories({ distanceM: 5000, durationS: 3600 })).toBeNull();
        expect(estimateCalories({ weightKg: 80, distanceM: 0 })).toBeNull();
    });

    it('estimates a plausible value for a flat 5 km hour walk', () => {
        // 5 km/h, 80 kg → ~5 MET × 80 × 1 h ≈ 400 kcal, no ascent.
        const kcal = estimateCalories({ distanceM: 5000, durationS: 3600, ascentM: 0, weightKg: 80, sex: 'm' });
        expect(kcal).toBeGreaterThan(300);
        expect(kcal).toBeLessThan(500);
    });

    it('adds energy for climbing', () => {
        const flat = estimateCalories({ distanceM: 8000, durationS: 7200, ascentM: 0, weightKg: 75, sex: 'm' });
        const hilly = estimateCalories({ distanceM: 8000, durationS: 7200, ascentM: 600, weightKg: 75, sex: 'm' });
        expect(hilly).toBeGreaterThan(flat);
    });

    it('estimates a duration for a planned route with no timestamps', () => {
        const kcal = estimateCalories({ distanceM: 9000, ascentM: 300, weightKg: 70, sex: 'f' });
        expect(kcal).toBeGreaterThan(0);
    });

    it('applies a lower value for female than male, all else equal', () => {
        const base = { distanceM: 6000, durationS: 4500, ascentM: 100, weightKg: 70 };
        expect(estimateCalories({ ...base, sex: 'f' })).toBeLessThan(estimateCalories({ ...base, sex: 'm' }));
    });
});
