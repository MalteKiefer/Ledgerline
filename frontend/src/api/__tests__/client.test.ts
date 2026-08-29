import { describe, it, expect, vi, beforeEach } from 'vitest';
import { api, setToken, ApiError, VersionConflict } from '@spa/api/client';

function mockRes(status: number, body: unknown, headers: Record<string, string> = {}) {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: new Headers(headers),
    text: () => Promise.resolve(body === null ? '' : JSON.stringify(body)),
  } as Response;
}

beforeEach(() => {
  const store: Record<string, string> = {};
  vi.stubGlobal('localStorage', {
    getItem: (k: string) => store[k] ?? null,
    setItem: (k: string, v: string) => { store[k] = v; },
    removeItem: (k: string) => { delete store[k]; },
  });
  setToken('tok123');
});

describe('api client (bearer)', () => {
  it('sends Authorization: Bearer on requests', async () => {
    const f = vi.fn().mockResolvedValue(mockRes(200, { user: { id: 1 } }));
    vi.stubGlobal('fetch', f);
    await api.get('/api/v1/me');
    const headers = (f.mock.calls[0][1] as RequestInit).headers as Record<string, string>;
    expect(headers.Authorization).toBe('Bearer tok123');
  });

  it('maps 409 version_conflict to VersionConflict', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockRes(409, { error: 'version_conflict', version: 7 })));
    await expect(api.put('/api/v1/invoices/1', {})).rejects.toBeInstanceOf(VersionConflict);
  });

  it('preserves the current resource on a typed version conflict while keeping version compatibility', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockRes(409, {
      error: 'version_conflict',
      current: { id: '018f4ca3-224d-7d8d-9f00-848484848484', version: 9 },
    }, { ETag: '"9"' })));

    try {
      await api.put('/api/v1/finance-v2/quotes/018f4ca3-224d-7d8d-9f00-848484848484/draft', {});
      throw new Error('should have thrown');
    } catch (error) {
      expect(error).toBeInstanceOf(VersionConflict);
      const conflict = error as VersionConflict<{ id: string; version: number }>;
      expect(conflict.version).toBe(9);
      expect(conflict.current).toEqual({ id: '018f4ca3-224d-7d8d-9f00-848484848484', version: 9 });
      expect(conflict.etag).toBe('"9"');
    }
  });

  it('forwards abort signals and exposes response status and ETag without changing existing body callers', async () => {
    const response = mockRes(202, { id: 'quote-1' }, { ETag: '"7"' });
    const fetchMock = vi.fn().mockResolvedValue(response);
    vi.stubGlobal('fetch', fetchMock);
    const controller = new AbortController();

    const detailed = await api.postResponse<{ id: string }>('/api/v1/finance-v2/quotes/quote-1/send', {}, {}, controller.signal);

    expect((fetchMock.mock.calls[0][1] as RequestInit).signal).toBe(controller.signal);
    expect(detailed).toEqual({ data: { id: 'quote-1' }, status: 202, etag: '"7"' });
  });

  it('maps 422 to ApiError with fields', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockRes(422, { errors: { email: ['required'] } })));
    try {
      await api.post('/api/v1/x', { a: 1 });
      throw new Error('should have thrown');
    } catch (e) {
      expect(e).toBeInstanceOf(ApiError);
      expect((e as ApiError).fields?.email).toEqual(['required']);
    }
  });

  it('drops the token and throws on 401', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockRes(401, null)));
    await expect(api.get('/api/v1/me')).rejects.toMatchObject({ status: 401 });
    expect(localStorage.getItem('ll_token')).toBeNull();
  });
});
