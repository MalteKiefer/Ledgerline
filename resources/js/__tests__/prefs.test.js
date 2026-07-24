import { describe, it, expect, beforeEach } from 'vitest';
import {
    prefs, setPrefs, is12h, convertDistance, distanceUnit, distanceLabel,
    convertElevation, elevationUnit, healthUnits,
} from '../shared/prefs.js';

// No DOM/meta in the test env → defaults, then setPrefs() overrides in-memory.
describe('prefs', () => {
    beforeEach(() => { setPrefs({ distance: 'km', elevation: 'm', weight: 'kg', temp: 'c', glucose: 'mgdl', time_format: '24h' }); });

    it('defaults are metric + 24h', () => {
        expect(prefs().distance).toBe('km');
        expect(is12h()).toBe(false);
    });

    it('converts distance metres → km', () => {
        expect(convertDistance(5000)).toEqual({ value: 5, unit: 'km' });
        expect(distanceUnit()).toBe('km');
        expect(distanceLabel(1234)).toBe('1.23 km');
    });

    it('converts distance metres → miles when set', () => {
        setPrefs({ distance: 'mi' });
        const d = convertDistance(1609.344, 2);
        expect(d.unit).toBe('mi');
        expect(d.value).toBeCloseTo(1, 2);
        expect(distanceUnit()).toBe('mi');
    });

    it('converts elevation metres → feet when set', () => {
        expect(convertElevation(100)).toEqual({ value: 100, unit: 'm' });
        setPrefs({ elevation: 'ft' });
        const e = convertElevation(100, 0);
        expect(e.unit).toBe('ft');
        expect(e.value).toBe(328);
        expect(elevationUnit()).toBe('ft');
    });

    it('exposes health units from the global prefs', () => {
        setPrefs({ weight: 'lb', temp: 'f', glucose: 'mmoll' });
        expect(healthUnits()).toEqual({ weight: 'lb', temp: 'f', glucose: 'mmoll' });
    });

    it('12h flag follows the time_format pref', () => {
        setPrefs({ time_format: '12h' });
        expect(is12h()).toBe(true);
    });
});
