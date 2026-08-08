import { describe, it, expect, vi, beforeEach } from 'vitest';
import { api, ApiError, VersionConflict } from '@spa/api/client';

function mockRes(status: number, body: unknown) {
  return {
    status,
    ok: status >= 200 && status < 300,
    text: () => Promise.resolve(body === null ? '' : JSON.stringify(body)),
  } as Response;
}

beforeEach(() => {
  vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=tok123' });
});

describe('api client', () => {
  it('returns parsed body on 200', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockRes(200, { user: { id: 1 } })));
    const r = await api.get<{ user: { id: number } }>('/api/v1/me');
    expect(r.user.id).toBe(1);
  });

  it('maps 409 version_conflict to VersionConflict', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(mockRes(409, { error: 'version_conflict', version: 7 })));
    await expect(api.get('/api/v1/invoices/1')).rejects.toMatchObject({ version: 7 });
    await expect(api.get('/api/v1/invoices/1')).rejects.toBeInstanceOf(VersionConflict);
  });

  it('maps 422 to ApiError with fields, sends XSRF on writes', async () => {
    const f = vi.fn()
      .mockResolvedValueOnce(mockRes(204, null)) // csrf-cookie
      .mockResolvedValueOnce(mockRes(422, { errors: { email: ['required'] } }));
    vi.stubGlobal('fetch', f);
    try {
      await api.post('/api/v1/x', { a: 1 });
      throw new Error('should have thrown');
    } catch (e) {
      expect(e).toBeInstanceOf(ApiError);
      expect((e as ApiError).status).toBe(422);
      expect((e as ApiError).fields?.email).toEqual(['required']);
    }
    const writeCall = f.mock.calls[1][1] as RequestInit;
    expect((writeCall.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('tok123');
  });
});
