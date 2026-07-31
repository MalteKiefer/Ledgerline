import { describe, it, expect } from 'vitest';
import passwords from '../components/passwords.js';
describe('identity + secure_note types', () => {
    const c = passwords();
    it('registers both types', () => {
        expect(c.types.identity).toBeTruthy();
        expect(c.types.secure_note).toBeTruthy();
    });
    it('identity field keys are the personal-info set', () => {
        expect(c.types.identity.fields.map((f) => f[0])).toEqual(['firstName','lastName','email','phone','company','street','city','state','zip','country','note']);
    });
    it('secure_note has only a note field', () => {
        expect(c.types.secure_note.fields.map((f) => f[0])).toEqual(['note']);
    });
    it('both are user-creatable', () => {
        expect(c.creatableTypes).toContain('identity');
        expect(c.creatableTypes).toContain('secure_note');
    });
    it('health skips both (not in _pwTypes)', () => {
        expect(c._pwTypes).not.toContain('identity');
        expect(c._pwTypes).not.toContain('secure_note');
    });
});
