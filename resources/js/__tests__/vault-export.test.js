import { describe, it, expect } from 'vitest';
import { buildBitwardenJson, buildCsv, encryptExport, decryptExport } from '../shared/vault-export.js';

const items = [{ id: '1', type: 'login', title: 'Ex', folder: null, tags: [], fields: { username: 'u', password: 'p', urls: ['https://x.tld'], note: 'n' } }];

describe('vault export', () => {
    it('builds a Bitwarden-shaped json', () => {
        const j = buildBitwardenJson(items, []);
        expect(j.encrypted).toBe(false);
        expect(Array.isArray(j.items)).toBe(true);
        expect(j.items[0].type).toBe(1); // bitwarden login type
        expect(j.items[0].login.username).toBe('u');
        expect(j.items[0].login.password).toBe('p');
    });
    it('builds csv with a header', () => {
        const csv = buildCsv(items);
        expect(csv.split('\n')[0]).toContain('name');
    });
    it('encrypted export round-trips and rejects a wrong passphrase', async () => {
        const env = await encryptExport(JSON.stringify(items), 'pass-1234');
        const back = await decryptExport(env, 'pass-1234');
        expect(JSON.parse(back)[0].title).toBe('Ex');
        await expect(decryptExport(env, 'wrong')).rejects.toThrow();
    });
    it('rejects a tampered mem value in the envelope', async () => {
        const env = await encryptExport(JSON.stringify(items), 'pass-1234');
        const parsed = JSON.parse(env);
        parsed.mem = 1048576; // tampered to a much lower cost
        await expect(decryptExport(JSON.stringify(parsed), 'pass-1234')).rejects.toThrow('unsupported export parameters');
    });
});
