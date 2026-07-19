import { describe, it, expect } from 'vitest';
import passwords from '../components/passwords.js';

describe('passkey nested-secret masking in versionDiff', () => {
    it('does not surface privateKey through versionDiff', () => {
        const c = passwords();
        const item = {
            id: 'item-1',
            type: 'login',
            title: 'My Login',
            fields: {
                username: 'alice',
                password: 'hunter2',
                urls: ['https://example.com'],
                passkeys: [
                    {
                        rpId: 'example.com',
                        userName: 'alice',
                        credentialId: 'abc123',
                        alg: -7,
                        privateKey: 'SECRET_PRIVATE_KEY_VALUE',
                        publicKey: 'SECRET_PUBLIC_KEY_VALUE',
                        createdAt: '2026-07-19T00:00:00.000Z',
                    },
                ],
            },
            custom: [],
            versions: [
                {
                    at: '2026-07-18T00:00:00.000Z',
                    title: 'My Login',
                    fields: {
                        username: 'alice',
                        password: 'hunter2',
                        urls: ['https://example.com'],
                        passkeys: [
                            {
                                rpId: 'example.com',
                                userName: 'alice',
                                credentialId: 'abc123',
                                alg: -7,
                                privateKey: 'SECRET_PRIVATE_KEY_VALUE',
                                publicKey: 'SECRET_PUBLIC_KEY_VALUE',
                                createdAt: '2026-07-18T00:00:00.000Z',
                            },
                        ],
                    },
                    custom: [],
                },
            ],
        };
        c.current = item;

        const diff = c.versionDiff(0);
        const serialised = JSON.stringify(diff);

        // Private/public key material must NEVER appear in the diff output
        expect(serialised).not.toContain('SECRET_PRIVATE_KEY_VALUE');
        expect(serialised).not.toContain('SECRET_PUBLIC_KEY_VALUE');

        // passkeys differ (createdAt changed) → must appear as masked sentinel, not dropped
        expect(diff).toHaveProperty('passkeys');
        expect(diff.passkeys).toBe('(changed)');
    });

    it('preserves non-secret passkey metadata in versionDiff', () => {
        const c = passwords();
        const now = '2026-07-19T00:00:00.000Z';
        const older = '2026-07-18T00:00:00.000Z';
        const item = {
            id: 'item-2',
            type: 'login',
            title: 'My Login',
            fields: {
                username: 'alice',
                password: 'hunter2',
                urls: ['https://example.com'],
                passkeys: [
                    {
                        rpId: 'example.com',
                        userName: 'alice-new',
                        credentialId: 'abc123',
                        alg: -7,
                        privateKey: 'SECRET_PRIVATE_KEY_VALUE',
                        publicKey: 'SECRET_PUBLIC_KEY_VALUE',
                        createdAt: now,
                    },
                ],
            },
            custom: [],
            versions: [
                {
                    at: older,
                    title: 'My Login',
                    fields: {
                        username: 'alice',
                        password: 'hunter2',
                        urls: ['https://example.com'],
                        passkeys: [
                            {
                                rpId: 'example.com',
                                userName: 'alice-old',
                                credentialId: 'abc123',
                                alg: -7,
                                privateKey: 'SECRET_PRIVATE_KEY_VALUE',
                                publicKey: 'SECRET_PUBLIC_KEY_VALUE',
                                createdAt: older,
                            },
                        ],
                    },
                    custom: [],
                },
            ],
        };
        c.current = item;

        const diff = c.versionDiff(0);
        const serialised = JSON.stringify(diff);

        // Private/public key material must not appear
        expect(serialised).not.toContain('SECRET_PRIVATE_KEY_VALUE');
        expect(serialised).not.toContain('SECRET_PUBLIC_KEY_VALUE');

        // passkeys key changed → should appear in diff as masked value
        expect(diff).toHaveProperty('passkeys');
        expect(diff.passkeys).toBe('(changed)');
    });
});
