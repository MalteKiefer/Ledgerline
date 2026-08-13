// Dependency-free country detection from an E.164 phone number's calling code.
// Only numbers written in international form (leading "+") can be resolved; a
// national number without a country code returns null (no reliable guess).
//
// Overlapping codes (NANP "+1", "+7" RU/KZ, "+44" UK, etc.) map to the primary
// assignee — good enough for a personal address book, no libphonenumber needed.

// Calling code (digits, no "+") → ISO 3166-1 alpha-2 country code.
const CODES: Record<string, string> = {
  '1': 'US', '7': 'RU',
  '20': 'EG', '27': 'ZA', '30': 'GR', '31': 'NL', '32': 'BE', '33': 'FR', '34': 'ES',
  '36': 'HU', '39': 'IT', '40': 'RO', '41': 'CH', '43': 'AT', '44': 'GB', '45': 'DK',
  '46': 'SE', '47': 'NO', '48': 'PL', '49': 'DE', '51': 'PE', '52': 'MX', '53': 'CU',
  '54': 'AR', '55': 'BR', '56': 'CL', '57': 'CO', '58': 'VE', '60': 'MY', '61': 'AU',
  '62': 'ID', '63': 'PH', '64': 'NZ', '65': 'SG', '66': 'TH', '81': 'JP', '82': 'KR',
  '84': 'VN', '86': 'CN', '90': 'TR', '91': 'IN', '92': 'PK', '93': 'AF', '94': 'LK',
  '95': 'MM', '98': 'IR',
  '211': 'SS', '212': 'MA', '213': 'DZ', '216': 'TN', '218': 'LY', '220': 'GM',
  '221': 'SN', '222': 'MR', '223': 'ML', '224': 'GN', '225': 'CI', '226': 'BF',
  '227': 'NE', '228': 'TG', '229': 'BJ', '230': 'MU', '231': 'LR', '232': 'SL',
  '233': 'GH', '234': 'NG', '235': 'TD', '236': 'CF', '237': 'CM', '238': 'CV',
  '239': 'ST', '240': 'GQ', '241': 'GA', '242': 'CG', '243': 'CD', '244': 'AO',
  '245': 'GW', '248': 'SC', '249': 'SD', '250': 'RW', '251': 'ET', '252': 'SO',
  '253': 'DJ', '254': 'KE', '255': 'TZ', '256': 'UG', '257': 'BI', '258': 'MZ',
  '260': 'ZM', '261': 'MG', '263': 'ZW', '264': 'NA', '265': 'MW', '266': 'LS',
  '267': 'BW', '268': 'SZ', '269': 'KM', '291': 'ER', '297': 'AW', '298': 'FO',
  '299': 'GL', '350': 'GI', '351': 'PT', '352': 'LU', '353': 'IE', '354': 'IS',
  '355': 'AL', '356': 'MT', '357': 'CY', '358': 'FI', '359': 'BG', '370': 'LT',
  '371': 'LV', '372': 'EE', '373': 'MD', '374': 'AM', '375': 'BY', '376': 'AD',
  '377': 'MC', '378': 'SM', '380': 'UA', '381': 'RS', '382': 'ME', '383': 'XK',
  '385': 'HR', '386': 'SI', '387': 'BA', '389': 'MK', '420': 'CZ', '421': 'SK',
  '423': 'LI', '500': 'FK', '501': 'BZ', '502': 'GT', '503': 'SV', '504': 'HN',
  '505': 'NI', '506': 'CR', '507': 'PA', '509': 'HT', '590': 'GP', '591': 'BO',
  '592': 'GY', '593': 'EC', '595': 'PY', '597': 'SR', '598': 'UY', '673': 'BN',
  '674': 'NR', '675': 'PG', '676': 'TO', '677': 'SB', '678': 'VU', '679': 'FJ',
  '680': 'PW', '685': 'WS', '852': 'HK', '853': 'MO', '855': 'KH', '856': 'LA',
  '880': 'BD', '886': 'TW', '960': 'MV', '961': 'LB', '962': 'JO', '963': 'SY',
  '964': 'IQ', '965': 'KW', '966': 'SA', '967': 'YE', '968': 'OM', '970': 'PS',
  '971': 'AE', '972': 'IL', '973': 'BH', '974': 'QA', '975': 'BT', '976': 'MN',
  '977': 'NP', '992': 'TJ', '993': 'TM', '994': 'AZ', '995': 'GE', '996': 'KG',
  '998': 'UZ',
};

// Calling codes sorted longest-first so the most specific prefix wins.
const KEYS = Object.keys(CODES).sort((a, b) => b.length - a.length);

/** ISO 3166-1 alpha-2 → flag emoji (regional-indicator pair). */
export function flagOf(iso: string): string {
  if (iso.length !== 2) return '';
  return String.fromCodePoint(...[...iso.toUpperCase()].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
}

export interface PhoneCountry { iso: string; flag: string }

/** Split a digit string into left-to-right groups of `n` (last may be shorter). */
function chunk(s: string, n: number): string[] {
  const out: string[] = [];
  for (let i = 0; i < s.length; i += n) out.push(s.slice(i, i + n));
  return out;
}

/**
 * Format a phone number for readable display, E.123 / DIN 5008 flavoured.
 *
 * International form ("+" or "00") → "+<cc> <grouped national>" with a leading
 * subscriber/area block followed by 3-4 digit groups (NANP gets the canonical
 * 3-3-4). A national number (no country code) is grouped in 4-digit blocks,
 * keeping any leading 0. Never merges digits wrongly — no libphonenumber, so
 * exact per-country area-code boundaries are approximated, not guaranteed.
 */
export function formatPhone(value: string): string {
  const s = (value ?? '').trim();
  if (!s) return '';
  const hasExt = s.includes('x') || s.includes(';');
  const core = hasExt ? s.replace(/[x;].*$/i, '').trim() : s;
  const intl = core.startsWith('+') || core.startsWith('00');
  let digits = core.replace(/\D/g, '');
  if (!digits) return s;

  if (!intl) {
    const lead0 = core.trim().startsWith('0');
    return (lead0 && !digits.startsWith('0') ? '0' : '') + chunk(digits, 4).join(' ');
  }

  digits = digits.replace(/^00/, '');
  const cc = KEYS.find((c) => digits.startsWith(c));
  if (!cc) return s;
  const nat = digits.slice(cc.length);
  if (nat.length <= 4) return `+${cc} ${nat}`;

  let grouped: string;
  if (cc === '1' && nat.length === 10) {
    grouped = `${nat.slice(0, 3)} ${nat.slice(3, 6)} ${nat.slice(6)}`;
  } else {
    grouped = [nat.slice(0, 3), ...chunk(nat.slice(3), 4)].join(' ');
  }
  return `+${cc} ${grouped}`;
}

/** Resolve a phone number's country from its "+" calling code, else null. */
export function phoneCountry(value: string): PhoneCountry | null {
  const s = (value ?? '').trim();
  if (!s.startsWith('+') && !s.startsWith('00')) return null;
  const digits = s.replace(/[^\d]/g, '').replace(/^00/, '');
  for (const code of KEYS) {
    if (digits.startsWith(code)) {
      const iso = CODES[code];
      return { iso, flag: flagOf(iso) };
    }
  }
  return null;
}
