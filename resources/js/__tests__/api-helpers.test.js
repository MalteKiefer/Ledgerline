import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock fetch before importing helpers
const fetchMock = vi.fn();
vi.stubGlobal('fetch', fetchMock);

// Mock document.querySelector for csrfToken
vi.stubGlobal('document', {
    querySelector: () => ({ getAttribute: () => 'test-csrf-token' }),
});

// Import AFTER mocks are set up
const { getJson, postForm } = await import('../shared/api.js');

describe('getJson', () => {
    beforeEach(() => { fetchMock.mockReset(); });

    it('sends a GET with Accept: application/json header', async () => {
        fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({ foo: 1 }) });
        await getJson('/some/url');
        expect(fetchMock).toHaveBeenCalledWith('/some/url', expect.objectContaining({
            method: 'GET',
            headers: expect.objectContaining({ Accept: 'application/json' }),
        }));
    });

    it('returns parsed JSON on success', async () => {
        fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({ data: 42 }) });
        const result = await getJson('/test');
        expect(result).toEqual({ data: 42 });
    });

    it('throws on non-ok response (500)', async () => {
        fetchMock.mockResolvedValueOnce({ ok: false, status: 500, json: async () => ({}) });
        await expect(getJson('/fail')).rejects.toThrow();
    });
});

describe('postForm', () => {
    beforeEach(() => { fetchMock.mockReset(); });

    it('sends a POST with X-CSRF-TOKEN header', async () => {
        fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({}) });
        await postForm('/some/url', { key: 'val' });
        expect(fetchMock).toHaveBeenCalledWith('/some/url', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'test-csrf-token' }),
        }));
    });

    it('sends the body as JSON', async () => {
        fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({}) });
        await postForm('/some/url', { a: 1 });
        const call = fetchMock.mock.calls[0][1];
        expect(JSON.parse(call.body)).toEqual({ a: 1 });
    });

    it('throws on non-ok response', async () => {
        fetchMock.mockResolvedValueOnce({ ok: false, status: 422, json: async () => ({}) });
        await expect(postForm('/fail', {})).rejects.toThrow();
    });

    it('returns parsed JSON on success', async () => {
        fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({ created: true }) });
        const result = await postForm('/create', { name: 'test' });
        expect(result).toEqual({ created: true });
    });
});
