// Public-holiday computation (client-side, no dependency). Easter via the
// Meeus/Jones/Butcher algorithm; fixed + Easter-relative + nth-weekday dates per
// country. Pure + Vitest. Returns [{ date:'yyyy-mm-dd', name }] for a year.
export const HOLIDAY_COUNTRIES = ['DE', 'AT', 'CH', 'GB', 'IE', 'FR', 'NL', 'BE', 'LU', 'IT', 'ES', 'PT', 'PL', 'CZ', 'SE', 'DK', 'NO', 'FI', 'US'];

function pad(n) { return String(n).padStart(2, '0'); }
function iso(y, m, d) { return `${y}-${pad(m)}-${pad(d)}`; }

// Gregorian Easter Sunday for a year → { m, d }.
export function easterSunday(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;
    return { m: month, d: day };
}

// A date offset `days` from Easter Sunday, as yyyy-mm-dd.
function easterOffset(year, days) {
    const e = easterSunday(year);
    const base = new Date(year, e.m - 1, e.d);
    base.setDate(base.getDate() + days);
    return iso(base.getFullYear(), base.getMonth() + 1, base.getDate());
}

// The nth (1-based; -1 = last) `weekday` (0=Sun..6=Sat) of month `m` in `year`.
function nthWeekday(year, m, weekday, nth) {
    if (nth === -1) {
        const last = new Date(year, m, 0); // last day of month m
        const back = (last.getDay() - weekday + 7) % 7;
        last.setDate(last.getDate() - back);
        return iso(last.getFullYear(), last.getMonth() + 1, last.getDate());
    }
    const first = new Date(year, m - 1, 1);
    const fwd = (weekday - first.getDay() + 7) % 7;
    const day = 1 + fwd + (nth - 1) * 7;
    return iso(year, m, day);
}

const NEW_YEAR = { m: 1, d: 1 };

function build(year, defs) {
    return defs.map((def) => {
        let date;
        if (def.fixed) date = iso(year, def.fixed[0], def.fixed[1]);
        else if (typeof def.easter === 'number') date = easterOffset(year, def.easter);
        else if (def.nth) date = nthWeekday(year, def.nth[0], def.nth[1], def.nth[2]);
        return { date, name: def.name };
    }).filter((h) => h.date);
}

const CATALOG = {
    DE: [
        { fixed: [NEW_YEAR.m, NEW_YEAR.d], name: 'Neujahr' },
        { easter: -2, name: 'Karfreitag' },
        { easter: 1, name: 'Ostermontag' },
        { fixed: [5, 1], name: 'Tag der Arbeit' },
        { easter: 39, name: 'Christi Himmelfahrt' },
        { easter: 50, name: 'Pfingstmontag' },
        { fixed: [10, 3], name: 'Tag der Deutschen Einheit' },
        { fixed: [12, 25], name: '1. Weihnachtstag' },
        { fixed: [12, 26], name: '2. Weihnachtstag' },
    ],
    AT: [
        { fixed: [1, 1], name: 'Neujahr' },
        { fixed: [1, 6], name: 'Heilige Drei Könige' },
        { easter: 1, name: 'Ostermontag' },
        { fixed: [5, 1], name: 'Staatsfeiertag' },
        { easter: 39, name: 'Christi Himmelfahrt' },
        { easter: 50, name: 'Pfingstmontag' },
        { easter: 60, name: 'Fronleichnam' },
        { fixed: [8, 15], name: 'Mariä Himmelfahrt' },
        { fixed: [10, 26], name: 'Nationalfeiertag' },
        { fixed: [11, 1], name: 'Allerheiligen' },
        { fixed: [12, 8], name: 'Mariä Empfängnis' },
        { fixed: [12, 25], name: 'Christtag' },
        { fixed: [12, 26], name: 'Stefanitag' },
    ],
    CH: [
        { fixed: [1, 1], name: 'Neujahr' },
        { easter: -2, name: 'Karfreitag' },
        { easter: 1, name: 'Ostermontag' },
        { easter: 39, name: 'Auffahrt' },
        { easter: 50, name: 'Pfingstmontag' },
        { fixed: [8, 1], name: 'Bundesfeier' },
        { fixed: [12, 25], name: 'Weihnachten' },
    ],
    GB: [
        { fixed: [1, 1], name: "New Year's Day" },
        { easter: -2, name: 'Good Friday' },
        { easter: 1, name: 'Easter Monday' },
        { fixed: [12, 25], name: 'Christmas Day' },
        { fixed: [12, 26], name: 'Boxing Day' },
    ],
    US: [
        { fixed: [1, 1], name: "New Year's Day" },
        { nth: [1, 1, 3], name: 'Martin Luther King Jr. Day' },
        { nth: [5, 1, -1], name: 'Memorial Day' },
        { fixed: [7, 4], name: 'Independence Day' },
        { nth: [9, 1, 1], name: 'Labor Day' },
        { nth: [11, 4, 4], name: 'Thanksgiving' },
        { fixed: [12, 25], name: 'Christmas Day' },
    ],
    IE: [
        { fixed: [1, 1], name: "New Year's Day" },
        { fixed: [3, 17], name: "St Patrick's Day" },
        { easter: 1, name: 'Easter Monday' },
        { nth: [5, 1, 1], name: 'May Day' },
        { nth: [6, 1, 1], name: 'June Bank Holiday' },
        { nth: [8, 1, 1], name: 'August Bank Holiday' },
        { nth: [10, 1, -1], name: 'October Bank Holiday' },
        { fixed: [12, 25], name: 'Christmas Day' },
        { fixed: [12, 26], name: "St Stephen's Day" },
    ],
    FR: [
        { fixed: [1, 1], name: 'Jour de l’an' },
        { easter: 1, name: 'Lundi de Pâques' },
        { fixed: [5, 1], name: 'Fête du Travail' },
        { fixed: [5, 8], name: 'Victoire 1945' },
        { easter: 39, name: 'Ascension' },
        { easter: 50, name: 'Lundi de Pentecôte' },
        { fixed: [7, 14], name: 'Fête nationale' },
        { fixed: [8, 15], name: 'Assomption' },
        { fixed: [11, 1], name: 'Toussaint' },
        { fixed: [11, 11], name: 'Armistice 1918' },
        { fixed: [12, 25], name: 'Noël' },
    ],
    NL: [
        { fixed: [1, 1], name: 'Nieuwjaarsdag' },
        { easter: -2, name: 'Goede Vrijdag' },
        { easter: 1, name: 'Tweede paasdag' },
        { fixed: [4, 27], name: 'Koningsdag' },
        { easter: 39, name: 'Hemelvaartsdag' },
        { easter: 50, name: 'Tweede pinksterdag' },
        { fixed: [12, 25], name: 'Eerste kerstdag' },
        { fixed: [12, 26], name: 'Tweede kerstdag' },
    ],
    BE: [
        { fixed: [1, 1], name: 'Nieuwjaar' },
        { easter: 1, name: 'Paasmaandag' },
        { fixed: [5, 1], name: 'Dag van de Arbeid' },
        { easter: 39, name: 'O.-H.-Hemelvaart' },
        { easter: 50, name: 'Pinkstermaandag' },
        { fixed: [7, 21], name: 'Nationale feestdag' },
        { fixed: [8, 15], name: 'O.-L.-Vrouw-Hemelvaart' },
        { fixed: [11, 1], name: 'Allerheiligen' },
        { fixed: [11, 11], name: 'Wapenstilstand' },
        { fixed: [12, 25], name: 'Kerstmis' },
    ],
    LU: [
        { fixed: [1, 1], name: 'Neijoerschdag' },
        { easter: 1, name: 'Ouschterméindeg' },
        { fixed: [5, 1], name: 'Dag vun der Aarbecht' },
        { fixed: [5, 9], name: 'Europadag' },
        { easter: 39, name: 'Christi Himmelfaart' },
        { easter: 50, name: 'Péngschtméindeg' },
        { fixed: [6, 23], name: 'Nationalfeierdag' },
        { fixed: [8, 15], name: 'Mariä Himmelfaart' },
        { fixed: [11, 1], name: 'Allerhellgen' },
        { fixed: [12, 25], name: 'Chrëschtdag' },
        { fixed: [12, 26], name: 'Stiefesdag' },
    ],
    IT: [
        { fixed: [1, 1], name: 'Capodanno' },
        { fixed: [1, 6], name: 'Epifania' },
        { easter: 1, name: 'Lunedì dell’Angelo' },
        { fixed: [4, 25], name: 'Festa della Liberazione' },
        { fixed: [5, 1], name: 'Festa del Lavoro' },
        { fixed: [6, 2], name: 'Festa della Repubblica' },
        { fixed: [8, 15], name: 'Ferragosto' },
        { fixed: [11, 1], name: 'Ognissanti' },
        { fixed: [12, 8], name: 'Immacolata Concezione' },
        { fixed: [12, 25], name: 'Natale' },
        { fixed: [12, 26], name: 'Santo Stefano' },
    ],
    ES: [
        { fixed: [1, 1], name: 'Año Nuevo' },
        { fixed: [1, 6], name: 'Reyes' },
        { easter: -2, name: 'Viernes Santo' },
        { fixed: [5, 1], name: 'Día del Trabajador' },
        { fixed: [8, 15], name: 'Asunción' },
        { fixed: [10, 12], name: 'Fiesta Nacional' },
        { fixed: [11, 1], name: 'Todos los Santos' },
        { fixed: [12, 6], name: 'Día de la Constitución' },
        { fixed: [12, 8], name: 'Inmaculada Concepción' },
        { fixed: [12, 25], name: 'Navidad' },
    ],
    PT: [
        { fixed: [1, 1], name: 'Ano Novo' },
        { easter: -2, name: 'Sexta-feira Santa' },
        { easter: 0, name: 'Páscoa' },
        { fixed: [4, 25], name: 'Dia da Liberdade' },
        { fixed: [5, 1], name: 'Dia do Trabalhador' },
        { fixed: [6, 10], name: 'Dia de Portugal' },
        { fixed: [8, 15], name: 'Assunção' },
        { fixed: [10, 5], name: 'Implantação da República' },
        { fixed: [11, 1], name: 'Todos os Santos' },
        { fixed: [12, 1], name: 'Restauração' },
        { fixed: [12, 8], name: 'Imaculada Conceição' },
        { fixed: [12, 25], name: 'Natal' },
    ],
    PL: [
        { fixed: [1, 1], name: 'Nowy Rok' },
        { fixed: [1, 6], name: 'Trzech Króli' },
        { easter: 1, name: 'Poniedziałek Wielkanocny' },
        { fixed: [5, 1], name: 'Święto Pracy' },
        { fixed: [5, 3], name: 'Święto Konstytucji' },
        { easter: 60, name: 'Boże Ciało' },
        { fixed: [8, 15], name: 'Wniebowzięcie NMP' },
        { fixed: [11, 1], name: 'Wszystkich Świętych' },
        { fixed: [11, 11], name: 'Święto Niepodległości' },
        { fixed: [12, 25], name: 'Boże Narodzenie' },
        { fixed: [12, 26], name: 'Drugi dzień Świąt' },
    ],
    CZ: [
        { fixed: [1, 1], name: 'Nový rok' },
        { easter: -2, name: 'Velký pátek' },
        { easter: 1, name: 'Velikonoční pondělí' },
        { fixed: [5, 1], name: 'Svátek práce' },
        { fixed: [5, 8], name: 'Den vítězství' },
        { fixed: [7, 5], name: 'Cyril a Metoděj' },
        { fixed: [7, 6], name: 'Jan Hus' },
        { fixed: [9, 28], name: 'Den české státnosti' },
        { fixed: [10, 28], name: 'Vznik ČSR' },
        { fixed: [11, 17], name: 'Den boje za svobodu' },
        { fixed: [12, 24], name: 'Štědrý den' },
        { fixed: [12, 25], name: '1. svátek vánoční' },
        { fixed: [12, 26], name: '2. svátek vánoční' },
    ],
    SE: [
        { fixed: [1, 1], name: 'Nyårsdagen' },
        { fixed: [1, 6], name: 'Trettondedag jul' },
        { easter: -2, name: 'Långfredagen' },
        { easter: 1, name: 'Annandag påsk' },
        { fixed: [5, 1], name: 'Första maj' },
        { easter: 39, name: 'Kristi himmelsfärd' },
        { fixed: [6, 6], name: 'Nationaldagen' },
        { fixed: [12, 25], name: 'Juldagen' },
        { fixed: [12, 26], name: 'Annandag jul' },
    ],
    DK: [
        { fixed: [1, 1], name: 'Nytårsdag' },
        { easter: -3, name: 'Skærtorsdag' },
        { easter: -2, name: 'Langfredag' },
        { easter: 1, name: '2. påskedag' },
        { easter: 39, name: 'Kristi himmelfartsdag' },
        { easter: 50, name: '2. pinsedag' },
        { fixed: [12, 25], name: 'Juledag' },
        { fixed: [12, 26], name: '2. juledag' },
    ],
    NO: [
        { fixed: [1, 1], name: 'Første nyttårsdag' },
        { easter: -3, name: 'Skjærtorsdag' },
        { easter: -2, name: 'Langfredag' },
        { easter: 1, name: 'Andre påskedag' },
        { fixed: [5, 1], name: 'Arbeidernes dag' },
        { fixed: [5, 17], name: 'Grunnlovsdag' },
        { easter: 39, name: 'Kristi himmelfartsdag' },
        { easter: 50, name: 'Andre pinsedag' },
        { fixed: [12, 25], name: 'Første juledag' },
        { fixed: [12, 26], name: 'Andre juledag' },
    ],
    FI: [
        { fixed: [1, 1], name: 'Uudenvuodenpäivä' },
        { fixed: [1, 6], name: 'Loppiainen' },
        { easter: -2, name: 'Pitkäperjantai' },
        { easter: 1, name: '2. pääsiäispäivä' },
        { fixed: [5, 1], name: 'Vappu' },
        { easter: 39, name: 'Helatorstai' },
        { fixed: [12, 6], name: 'Itsenäisyyspäivä' },
        { fixed: [12, 25], name: 'Joulupäivä' },
        { fixed: [12, 26], name: 'Tapaninpäivä' },
    ],
};

export function computeHolidays(country, year) {
    const defs = CATALOG[country];
    return defs ? build(year, defs) : [];
}
