import { describe, it, expect } from 'vitest';
import { buildSchoolHolidayUrl, regionName, SCHOOL_REGIONS } from '../shared/school-holidays.js';

describe('school-holidays', () => {
    it('builds an OpenHolidays iCal URL', () => {
        const u = buildSchoolHolidayUrl('DE', 'DE-BY', 2026, 2028);
        expect(u).toContain('https://openholidaysapi.org/SchoolHolidays?');
        expect(u).toContain('countryIsoCode=DE');
        expect(u).toContain('subdivisionCode=DE-BY');
        expect(u).toContain('validFrom=2026-01-01');
        expect(u).toContain('validTo=2028-12-31');
    });

    it('resolves region names', () => {
        expect(regionName('DE', 'DE-BY')).toBe('Bayern');
        expect(regionName('AT', 'AT-9')).toBe('Wien');
        expect(regionName('DE', 'DE-XX')).toBe('DE-XX');
        expect(SCHOOL_REGIONS.DE).toHaveLength(16);
    });
});
