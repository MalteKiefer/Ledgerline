import { beforeEach, describe, expect, expectTypeOf, it, vi } from 'vitest';
import { ApiError, setToken } from '@spa/api/client';
import { projectApi, projectErrorCode } from '@spa/modules/finance/api/projectApi';
import type { Project, ProjectInput, ProjectPage } from '@spa/modules/finance/models/project';

const projectId = '018f4ca3-224d-7d8d-9f00-848484848484';

function response(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return { status, ok: status >= 200 && status < 300, headers: new Headers(headers), text: () => Promise.resolve(body === null ? '' : JSON.stringify(body)) } as Response;
}

function project(version = 0): Project {
  return {
    id: projectId, parent_id: null, parent_available: true, name: 'Exact project', kind: 'business', status: 'active',
    partner_reference: null, starts_on: null, due_on: null, budget_minor: '9007199254740993', currency: 'EUR',
    version, archived: false, created_at: '2026-08-29T10:00:00.123456+00:00', updated_at: '2026-08-29T10:00:00.123456+00:00',
  };
}

beforeEach(() => {
  const values: Record<string, string> = {};
  vi.stubGlobal('localStorage', { getItem: (key: string) => values[key] ?? null, setItem: (key: string, value: string) => { values[key] = value; }, removeItem: (key: string) => { delete values[key]; } });
  setToken('project-token');
});

describe('projectApi', () => {
  it('preserves exact wire strings, opaque references and ETags', async () => {
    const page: ProjectPage = { data: [project()], meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 }, links: { first: '/p?page=1', last: '/p?page=1', prev: null, next: null } };
    const fetchMock = vi.fn().mockResolvedValueOnce(response(200, page)).mockResolvedValueOnce(response(200, project(7), { ETag: '"7"' }));
    vi.stubGlobal('fetch', fetchMock);

    const listed = await projectApi.list({ q: 'exact', archived: false, sort: 'updated_at', direction: 'desc', page: 1 });
    const shown = await projectApi.showResponse(projectId);

    expect(listed.data[0].budget_minor).toBe('9007199254740993');
    expectTypeOf(listed.data[0].budget_minor).toEqualTypeOf<string | null>();
    expect(shown).toEqual({ data: project(7), status: 200, etag: '"7"' });
    expect(String(fetchMock.mock.calls[0][0])).toContain('/api/v1/finance-v2/projects?q=exact&archived=false&sort=updated_at&direction=desc&page=1');
  });

  it('targets the complete project-v2 surface and forwards keys only where required', async () => {
    const fetchMock = vi.fn().mockImplementation((_url: string, init: RequestInit) => {
      if (init.method === 'DELETE' && String(_url).includes('/work-items/')) return Promise.resolve(response(204, null));
      return Promise.resolve(response(init.method === 'POST' ? 201 : 200, project()));
    });
    vi.stubGlobal('fetch', fetchMock);
    const input: ProjectInput = { name: 'P', kind: 'business', budget_minor: '9007199254740993', currency: 'EUR', partner_reference: null, parent_id: null, starts_on: null, due_on: null };

    await projectApi.create(input);
    await projectApi.update(projectId, { ...input, version: 0 });
    await projectApi.changeStatus(projectId, { version: 0, status: 'done' });
    await projectApi.move(projectId, { version: 0, parent_id: null });
    await projectApi.archive(projectId, 0);
    await projectApi.restore(projectId, 1);
    await projectApi.listWorkItems(projectId, { page: 2, per_page: 10 });
    await projectApi.createWorkItem(projectId, { title: 'Work', status: 'open', estimate_hours: '1.2500', is_milestone: false });
    await projectApi.updateWorkItem(projectId, 'work-id', { title: 'Work', status: 'done', is_milestone: false, version: 1 });
    await projectApi.deleteWorkItem(projectId, 'work-id', 2);
    await projectApi.reorderWorkItems(projectId, ['work-id']);
    await projectApi.listTimeEntries(projectId, { page: 1, per_page: 25 });
    await projectApi.createTimeEntry(projectId, { worked_on: '2026-08-29', hours: '1.2500', billable: true, hourly_rate_minor: '9007199254740993', currency: 'EUR' });
    await projectApi.updateTimeEntry(projectId, 'time-id', { worked_on: '2026-08-29', hours: '1.5000', billable: true, hourly_rate_minor: '9007199254740993', currency: 'EUR', version: 1 });
    await projectApi.deleteTimeEntry(projectId, 'time-id', 1);
    await projectApi.getTotals(projectId);
    await projectApi.listLedger(projectId, { direction: 'in', from: '2026-01-01', page: 1 });
    await projectApi.createLedgerEntry(projectId, { direction: 'in', amount_minor: '9007199254740993', currency: 'EUR' });
    await projectApi.updateLedgerEntry(projectId, 'ledger-id', { direction: 'out', amount_minor: '9007199254740995', currency: 'EUR', version: 1 });
    await projectApi.deleteLedgerEntry(projectId, 'ledger-id', 1);
    await projectApi.listDocuments(projectId, { state: 'all', page: 1 });
    await projectApi.searchDocumentSources(projectId, { cursor: 'opaque+/=', per_page: 20 });
    await projectApi.listNotes(projectId, { page: 1 });
    await projectApi.listActivity(projectId, { cursor: 'history-cursor', per_page: 20 });
    await projectApi.listDocumentNotes('series-id', { page: 3 });
    await projectApi.appendNote(projectId, { type: 'note', visibility: 'internal', body: 'Safe body' });
    await projectApi.appendDocumentNote('series-id', { revision_id: 7, type: 'decision', visibility: 'customer', body: 'Approved' });
    await projectApi.createInvoiceDraft(projectId, { time_entry_ids: ['time-id'] }, 'invoice-key');
    await projectApi.attachDocument(projectId, { source_type: 'file', source_reference: 'Opaque:Ref', pinned_revision_id: null, role: 'file' }, 'attach-key');
    await projectApi.detachDocument(projectId, 41, 'detach-key');

    for (const [url] of fetchMock.mock.calls) expect(String(url)).toMatch(/^\/api\/v1\/finance-v2\//);
    const keyed = fetchMock.mock.calls.flatMap(([, init]) => {
      const key = ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key'];
      return key ? [key] : [];
    });
    expect(keyed).toEqual(['invoice-key', 'attach-key', 'detach-key']);
    expect(String(fetchMock.mock.calls.find(([url]) => String(url).includes('document-sources'))?.[0])).toContain('cursor=opaque%2B%2F%3D');
    const calls = new Set(fetchMock.mock.calls.map(([url, init]) => `${String((init as RequestInit).method)} ${String(url).split('?')[0]}`));
    expect(calls).toEqual(new Set([
      'POST /api/v1/finance-v2/projects', `PUT /api/v1/finance-v2/projects/${projectId}`,
      `POST /api/v1/finance-v2/projects/${projectId}/status`, `POST /api/v1/finance-v2/projects/${projectId}/move`,
      `DELETE /api/v1/finance-v2/projects/${projectId}`, `POST /api/v1/finance-v2/projects/${projectId}/restore`,
      `GET /api/v1/finance-v2/projects/${projectId}/work-items`, `POST /api/v1/finance-v2/projects/${projectId}/work-items`,
      `PUT /api/v1/finance-v2/projects/${projectId}/work-items/work-id`, `DELETE /api/v1/finance-v2/projects/${projectId}/work-items/work-id`,
      `POST /api/v1/finance-v2/projects/${projectId}/work-items/reorder`, `GET /api/v1/finance-v2/projects/${projectId}/time-entries`,
      `POST /api/v1/finance-v2/projects/${projectId}/time-entries`, `PUT /api/v1/finance-v2/projects/${projectId}/time-entries/time-id`,
      `DELETE /api/v1/finance-v2/projects/${projectId}/time-entries/time-id`, `GET /api/v1/finance-v2/projects/${projectId}/totals`,
      `GET /api/v1/finance-v2/projects/${projectId}/ledger`, `POST /api/v1/finance-v2/projects/${projectId}/ledger`,
      `PUT /api/v1/finance-v2/projects/${projectId}/ledger/ledger-id`, `DELETE /api/v1/finance-v2/projects/${projectId}/ledger/ledger-id`,
      `GET /api/v1/finance-v2/projects/${projectId}/documents`, `GET /api/v1/finance-v2/projects/${projectId}/document-sources`,
      `GET /api/v1/finance-v2/projects/${projectId}/notes`, `POST /api/v1/finance-v2/projects/${projectId}/notes`,
      `GET /api/v1/finance-v2/projects/${projectId}/activities`, `GET /api/v1/finance-v2/document-series/series-id/notes`,
      `POST /api/v1/finance-v2/document-series/series-id/notes`, `POST /api/v1/finance-v2/projects/${projectId}/invoice-drafts`,
      `POST /api/v1/finance-v2/projects/${projectId}/documents`, `DELETE /api/v1/finance-v2/projects/${projectId}/documents/41`,
    ]));
  });

  it('exposes stable project error codes without leaking arbitrary server strings', () => {
    expect(projectErrorCode(new ApiError(409, { error: 'time_invoiced' }))).toBe('time_invoiced');
    expect(projectErrorCode(new ApiError(500, { error: 'SQLSTATE secret' }))).toBeNull();
  });
});
