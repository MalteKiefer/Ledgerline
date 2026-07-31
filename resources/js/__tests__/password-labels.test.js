/**
 * Regression guard: every JS type key and field key in the TYPES registry must
 * have a matching lang key (passwords.type_* / passwords.f_*) in BOTH lang/en
 * and lang/de, AND a corresponding entry in the blade labels.types /
 * labels.fields maps.
 *
 * Reads the canonical sources directly so this test can never drift from them:
 *   - TYPES export from passwords.js (the JS registry)
 *   - lang/en/passwords.php  (PHP text file — scanned for key presence)
 *   - lang/de/passwords.php  (PHP text file — scanned for key presence)
 *   - resources/views/passwords/index.blade.php (blade map — scanned for key presence)
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { TYPES } from '../components/passwords.js';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Path resolution relative to the repo root (3 levels up from resources/js/__tests__). */
const repoRoot = resolve(import.meta.dirname, '../../../');

function readFile(relPath) {
    return readFileSync(resolve(repoRoot, relPath), 'utf8');
}

/**
 * Returns true if a PHP lang file contains the given key as an active (non-
 * commented) top-level array key.  Matches lines where the key appears as
 * the first non-whitespace token, e.g.  '    \'key\' => …'.  Lines that start
 * with // or # (possibly preceded by whitespace) are excluded.
 */
function phpHasKey(phpSource, key) {
    const escaped = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    // ^\\s* — leading whitespace; then NOT // or # — then the quoted key => …
    return new RegExp(`^\\s*(?!\\s*(?://|#))\\s*['"]${escaped}['"]\\s*=>`, 'm').test(phpSource);
}

/**
 * Returns true if the blade view exposes the given lang key in a @js(__(...))
 * call.  Matches  passwords.key  inside  @js(__('passwords.key'))  or
 * @js(__("passwords.key")).
 */
function bladeHasLangKey(bladeSource, langKey) {
    const escaped = langKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return new RegExp(`@js\\s*\\(\\s*__\\s*\\(\\s*['"]${escaped}['"]`).test(bladeSource);
}

// ---------------------------------------------------------------------------
// Load sources
// ---------------------------------------------------------------------------

const phpEn = readFile('lang/en/passwords.php');
const phpDe = readFile('lang/de/passwords.php');
const blade = readFile('resources/views/passwords/index.blade.php');

// ---------------------------------------------------------------------------
// Derive expected keys from the TYPES registry
// ---------------------------------------------------------------------------

const typeKeys = Object.keys(TYPES);

// Collect every unique field key across all types.
const fieldKeySet = new Set();
for (const typeDef of Object.values(TYPES)) {
    for (const [fieldKey] of typeDef.fields) {
        fieldKeySet.add(fieldKey);
    }
}
const fieldKeys = [...fieldKeySet];

// ---------------------------------------------------------------------------
// _pwTypes derivation
// ---------------------------------------------------------------------------

describe('_pwTypes derived from registry', () => {
    it('is the set of types that have a password field', () => {
        const derived = typeKeys.filter((t) => TYPES[t].fields.some(([k]) => k === 'password'));
        // Behaviour-lock: must equal exactly this set (order-insensitive).
        expect(new Set(derived)).toEqual(new Set(['login', 'password', 'server', 'wifi']));
    });
});

// ---------------------------------------------------------------------------
// Type key parity
// ---------------------------------------------------------------------------

describe('type label parity — lang/en/passwords.php', () => {
    for (const t of typeKeys) {
        it(`has key type_${t}`, () => {
            expect(phpHasKey(phpEn, `type_${t}`), `missing 'type_${t}' in lang/en/passwords.php`).toBe(true);
        });
    }
});

describe('type label parity — lang/de/passwords.php', () => {
    for (const t of typeKeys) {
        it(`has key type_${t}`, () => {
            expect(phpHasKey(phpDe, `type_${t}`), `missing 'type_${t}' in lang/de/passwords.php`).toBe(true);
        });
    }
});

describe('type label parity — blade labels.types map', () => {
    for (const t of typeKeys) {
        it(`maps type_${t} in the blade view`, () => {
            expect(
                bladeHasLangKey(blade, `passwords.type_${t}`),
                `missing @js(__('passwords.type_${t}')) in index.blade.php`,
            ).toBe(true);
        });
    }
});

// ---------------------------------------------------------------------------
// Field key parity
// ---------------------------------------------------------------------------

describe('field label parity — lang/en/passwords.php', () => {
    for (const f of fieldKeys) {
        it(`has key f_${f}`, () => {
            expect(phpHasKey(phpEn, `f_${f}`), `missing 'f_${f}' in lang/en/passwords.php`).toBe(true);
        });
    }
});

describe('field label parity — lang/de/passwords.php', () => {
    for (const f of fieldKeys) {
        it(`has key f_${f}`, () => {
            expect(phpHasKey(phpDe, `f_${f}`), `missing 'f_${f}' in lang/de/passwords.php`).toBe(true);
        });
    }
});

describe('field label parity — blade labels.fields map', () => {
    for (const f of fieldKeys) {
        it(`maps f_${f} in the blade view`, () => {
            expect(
                bladeHasLangKey(blade, `passwords.f_${f}`),
                `missing @js(__('passwords.f_${f}')) in index.blade.php`,
            ).toBe(true);
        });
    }
});
