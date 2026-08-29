import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { setToken } from '@spa/api/client';
import type { Project, ProjectPage } from '@spa/modules/finance/models/project';
import { useProjectsStore } from '@spa/modules/finance/stores/projects';

const id = '018f4ca3-224d-7d8d-9f00-848484848484';
const response = (status: number, body: unknown, etag = '"0"') => ({ status, ok: status >= 200 && status < 300, headers: new Headers({ ETag: etag }), text: () => Promise.resolve(JSON.stringify(body)) } as Response);
const project = (version = 0, name = 'P'): Project => ({ id, parent_id: null, parent_available: true, name, kind: 'business', status: 'active', partner_reference: null, starts_on: null, due_on: null, budget_minor: '9007199254740993', currency: 'EUR', version, archived: false, created_at: 'now', updated_at: 'now' });
const page = (data: Project[]): ProjectPage => ({ data, meta: { current_page: 1, per_page: 20, total: data.length, last_page: 1 }, links: { first: '', last: '', prev: null, next: null } });

beforeEach(() => {
  setActivePinia(createPinia());
  const values: Record<string, string> = {};
  vi.stubGlobal('localStorage', { getItem: (key: string) => values[key] ?? null, setItem: (key: string, value: string) => { values[key] = value; }, removeItem: (key: string) => { delete values[key]; } });
  setToken('project-token');
});

describe('projects store', () => {
  it('isolates list/detail state, aborts superseded reads and suppresses stale data', async () => {
    let oldResolve!: (value: Response) => void;
    const fetchMock = vi.fn().mockImplementationOnce(() => new Promise<Response>((resolve) => { oldResolve = resolve; })).mockResolvedValueOnce(response(200, page([project(2, 'new')]))).mockResolvedValueOnce(response(200, project(4, 'detail'), '"4"'));
    vi.stubGlobal('fetch', fetchMock);
    const store = useProjectsStore();
    const old = store.loadList({ q: 'old' });
    await store.loadList({ q: 'new' });
    expect(((fetchMock.mock.calls[0][1] as RequestInit).signal as AbortSignal).aborted).toBe(true);
    oldResolve(response(200, page([project(1, 'old')])));
    await old;
    await store.loadProject(id);
    expect(store.items[0].name).toBe('detail');
    expect(store.currentEtag).toBe('"4"');
    expect(store.listError).toBeNull();
    expect(store.detailError).toBeNull();
  });

  it('upserts server current on version conflict without touching read errors', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response(409, { error: 'version_conflict', current: project(8, 'server') }, '"8"')));
    const store = useProjectsStore();
    store.items = [project(1, 'old')];
    await expect(store.update(id, { name: 'mine', kind: 'business', currency: 'EUR', version: 1 })).rejects.toMatchObject({ version: 8 });
    expect(store.current?.name).toBe('server');
    expect(store.items[0].version).toBe(8);
    expect(store.actionError).toBe('version_conflict');
    expect(store.listError).toBeNull();
  });

  it('reuses a key for the same canonical payload and rotates it for changed, successful, or cancelled actions', async () => {
    const randomUUID = vi.fn().mockReturnValueOnce('key-1').mockReturnValueOnce('key-2').mockReturnValueOnce('key-3').mockReturnValueOnce('key-4');
    vi.stubGlobal('crypto', { randomUUID });
    const fetchMock = vi.fn().mockResolvedValueOnce(response(500, {})).mockResolvedValueOnce(response(500, {})).mockResolvedValueOnce(response(201, { target_reference: 'invoice:1', source: { source_type: 'finance_series', source_reference: 'x', pinned_revision_id: null }, navigation_url: '/invoice/1' })).mockResolvedValueOnce(response(500, {})).mockResolvedValueOnce(response(201, { target_reference: 'invoice:2', source: { source_type: 'finance_series', source_reference: 'y', pinned_revision_id: null }, navigation_url: '/invoice/2' }));
    vi.stubGlobal('fetch', fetchMock);
    const store = useProjectsStore();
    await expect(store.createInvoiceDraft(id, ['b', 'a'])).rejects.toBeTruthy();
    await expect(store.createInvoiceDraft(id, ['a', 'b'])).rejects.toBeTruthy();
    await store.createInvoiceDraft(id, ['a', 'b']);
    await expect(store.createInvoiceDraft(id, ['c'])).rejects.toBeTruthy();
    store.cancelAction('invoice', id);
    await store.createInvoiceDraft(id, ['c']);
    const keys = fetchMock.mock.calls.map(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']);
    expect(keys).toEqual(['key-1', 'key-2', 'key-2', 'key-3', 'key-4']);
  });

  it('binds attach and detach retry keys to their exact action payloads', async () => {
    vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValueOnce('attach-key').mockReturnValueOnce('detach-key') });
    const document = { link_id: 41, project_id: id, source: { source_type: 'file', source_reference: 'Opaque:Ref', pinned_revision_id: null }, role: 'file', snapshot: {}, current: null, availability: 'missing', attached_at: 'now', detached: false, detached_at: null };
    const fetchMock = vi.fn().mockResolvedValueOnce(response(500, {})).mockResolvedValueOnce(response(201, document)).mockResolvedValueOnce(response(500, {})).mockResolvedValueOnce(response(200, { ...document, detached: true, detached_at: 'later' }));
    vi.stubGlobal('fetch', fetchMock);
    const store = useProjectsStore();
    const input = { source_type: 'file' as const, source_reference: 'Opaque:Ref', pinned_revision_id: null, role: 'file' as const };
    await expect(store.attachDocument(id, input)).rejects.toBeTruthy();
    await store.attachDocument(id, { role: 'file', pinned_revision_id: null, source_reference: 'Opaque:Ref', source_type: 'file' });
    await expect(store.detachDocument(id, 41)).rejects.toBeTruthy();
    await store.detachDocument(id, 41);
    expect(fetchMock.mock.calls.map(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']))
      .toEqual(['attach-key', 'attach-key', 'detach-key', 'detach-key']);
  });
});
