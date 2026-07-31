import { describe, it, expect } from 'vitest';
import passwords from '../components/passwords.js';

describe('passkey item type', () => {
    const c = passwords();
    it('registers a passkey type with user-facing fields', () => {
        expect(c.types.passkey).toBeTruthy();
        const keys = c.types.passkey.fields.map((f) => f[0]);
        expect(keys).toEqual(['rpId', 'userName', 'userDisplayName', 'note']);
    });
    it('treats privateKey as a secret field', () => {
        expect(c.secretFields).toContain('privateKey');
    });
    it('excludes passkeys from password-health scanning', () => {
        expect(c._pwTypes).not.toContain('passkey');
    });
});
