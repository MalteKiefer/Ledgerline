// School-holiday (Ferien) quick-subscribe helper. Builds an OpenHolidays API iCal
// URL for a region; the calendar subscribes to it through the SSRF-guarded proxy
// (which sends Accept: text/calendar). Pure + Vitest. Data is public (school terms),
// so this is the same class of user-initiated outbound fetch as public holidays.
export const SCHOOL_COUNTRIES = ['DE', 'AT'];

// Subdivision codes + display names per country (OpenHolidays `subdivisionCode`).
export const SCHOOL_REGIONS = {
    DE: [
        { code: 'DE-BW', name: 'Baden-Württemberg' },
        { code: 'DE-BY', name: 'Bayern' },
        { code: 'DE-BE', name: 'Berlin' },
        { code: 'DE-BB', name: 'Brandenburg' },
        { code: 'DE-HB', name: 'Bremen' },
        { code: 'DE-HH', name: 'Hamburg' },
        { code: 'DE-HE', name: 'Hessen' },
        { code: 'DE-MV', name: 'Mecklenburg-Vorpommern' },
        { code: 'DE-NI', name: 'Niedersachsen' },
        { code: 'DE-NW', name: 'Nordrhein-Westfalen' },
        { code: 'DE-RP', name: 'Rheinland-Pfalz' },
        { code: 'DE-SL', name: 'Saarland' },
        { code: 'DE-SN', name: 'Sachsen' },
        { code: 'DE-ST', name: 'Sachsen-Anhalt' },
        { code: 'DE-SH', name: 'Schleswig-Holstein' },
        { code: 'DE-TH', name: 'Thüringen' },
    ],
    AT: [
        { code: 'AT-1', name: 'Burgenland' },
        { code: 'AT-2', name: 'Kärnten' },
        { code: 'AT-3', name: 'Niederösterreich' },
        { code: 'AT-4', name: 'Oberösterreich' },
        { code: 'AT-5', name: 'Salzburg' },
        { code: 'AT-6', name: 'Steiermark' },
        { code: 'AT-7', name: 'Tirol' },
        { code: 'AT-8', name: 'Vorarlberg' },
        { code: 'AT-9', name: 'Wien' },
    ],
};

// OpenHolidays iCal URL for a region, covering [fromYear, toYear] inclusive.
export function buildSchoolHolidayUrl(country, subdivisionCode, fromYear, toYear) {
    const lang = 'DE';
    const params = new URLSearchParams({
        countryIsoCode: country,
        languageIsoCode: lang,
        validFrom: `${fromYear}-01-01`,
        validTo: `${toYear}-12-31`,
        subdivisionCode: subdivisionCode,
    });
    return `https://openholidaysapi.org/SchoolHolidays?${params.toString()}`;
}

export function regionName(country, code) {
    return (SCHOOL_REGIONS[country] || []).find((r) => r.code === code)?.name || code;
}
