import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { projectApi } from '@spa/modules/finance/api/projectApi';
import { useProjectDetail } from '@spa/modules/finance/composables/useProjectDetail';
import type { Project } from '@spa/modules/finance/models/project';
import { useProjectsStore } from '@spa/modules/finance/stores/projects';

vi.mock('@spa/modules/finance/api/projectApi', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@spa/modules/finance/api/projectApi')>();
  return { ...actual, projectApi: Object.fromEntries(Object.entries(actual.projectApi).map(([key, value]) => [key, typeof value === 'function' ? vi.fn() : value])) };
});

beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks(); });

const projectView = (id: string, version: number): Project => ({
  id, parent_id: null, parent_available: true, name: id, kind: 'business', status: 'active', partner_reference: null,
  starts_on: null, due_on: null, budget_minor: null, currency: 'EUR', version, archived: false, created_at: 'now', updated_at: 'now',
});

describe('project detail loader', () => {
  it('isolates panel failures and keeps unrelated cursors and errors during a targeted refresh', async () => {
    vi.mocked(projectApi.listDocuments).mockRejectedValueOnce(new Error('documents unavailable'));
    vi.mocked(projectApi.listWorkItems).mockResolvedValue({ data: [], meta: { current_page: 2, per_page: 20, total: 0 } });
    vi.mocked(projectApi.listActivity).mockResolvedValue({ data: [], next_cursor: 'next-history' });
    const detail = useProjectDetail();
    detail.documents.query.state = 'all';
    detail.documents.query.page = 3;
    detail.activity.query.cursor = 'start-history';
    await Promise.allSettled([detail.loadDocuments('p'), detail.loadWork('p'), detail.loadActivity('p')]);
    expect(detail.documents.error).toBe('request_failed');
    expect(detail.work.error).toBeNull();
    expect(detail.activity.nextCursor).toBe('next-history');

    vi.mocked(projectApi.listWorkItems).mockResolvedValueOnce({ data: [], meta: { current_page: 2, per_page: 20, total: 0 } });
    await detail.refresh('work');
    expect(detail.documents.error).toBe('request_failed');
    expect(detail.documents.query.page).toBe(3);
    expect(detail.activity.nextCursor).toBe('next-history');
  });

  it('aborts every old project panel and suppresses stale responses on project change', async () => {
    let resolveOld!: (value: { data: never[]; meta: { current_page: number; per_page: number; total: number } }) => void;
    vi.mocked(projectApi.listWorkItems).mockImplementationOnce((_id, _query, signal) => new Promise((resolve) => { signal?.addEventListener('abort', () => undefined); resolveOld = resolve; })).mockResolvedValueOnce({ data: [], meta: { current_page: 1, per_page: 20, total: 0 } });
    const detail = useProjectDetail();
    const old = detail.loadWork('old');
    await detail.open('new', ['work']);
    resolveOld({ data: [], meta: { current_page: 9, per_page: 20, total: 0 } });
    await old;
    expect(detail.projectId).toBe('new');
    expect(detail.work.data?.meta.current_page).toBe(1);
  });

  it('keeps each panel pagination independent and refreshes only mutation-relevant panels', async () => {
    vi.mocked(projectApi.listTimeEntries).mockResolvedValue({ data: [], meta: { current_page: 4, per_page: 10, total: 0 } });
    vi.mocked(projectApi.getTotals).mockResolvedValue({ project_id: 'p', currencies: {} });
    vi.mocked(projectApi.createTimeEntry).mockResolvedValue({ resource_type: 'time_entry', id: 't', work_item_id: null, worked_on: '2026-08-29', quantity_scaled: '10000', description: null, billable: false, hourly_rate_minor: null, currency: 'EUR', invoice_target_reference: null, invoiced_at: null, version: 0 });
    const detail = useProjectDetail();
    await detail.open('p', []);
    detail.time.query.page = 4;
    detail.work.query.page = 2;
    detail.notes.query.page = 6;
    await detail.createTime({ work_item_id: null, worked_on: '2026-08-29', hours: '1.0000', description: null, billable: false, hourly_rate_minor: null, currency: 'EUR' });
    expect(projectApi.listTimeEntries).toHaveBeenCalledWith('p', { page: 4, per_page: 20 }, expect.any(AbortSignal));
    expect(projectApi.getTotals).toHaveBeenCalled();
    expect(projectApi.listWorkItems).not.toHaveBeenCalled();
    expect(detail.work.query.page).toBe(2);
    expect(detail.notes.query.page).toBe(6);
  });

  it('retains the project ETag and keeps detail and store representations synchronized and id-gated', async () => {
    const oldId = '018f4ca3-224d-7d8d-9f00-848484848484';
    const newId = '018f4ca3-224d-7d8d-9f00-858585858585';
    vi.mocked(projectApi.showResponse)
      .mockResolvedValueOnce({ data: projectView(oldId, 4), status: 200, etag: '"4"' })
      .mockRejectedValueOnce(new Error('same refresh failed'))
      .mockRejectedValueOnce(new Error('new project failed'));
    const detail = useProjectDetail();
    const store = useProjectsStore();

    await detail.loadProject(oldId);
    expect(detail.project.data).toEqual(store.current);
    expect(detail.project.etag).toBe('"4"');
    expect(store.currentEtag).toBe('"4"');

    await expect(detail.loadProject(oldId)).rejects.toBeTruthy();
    expect(detail.project.data?.id).toBe(oldId);
    expect(detail.project.etag).toBe('"4"');
    expect(store.current?.id).toBe(oldId);

    const switched = detail.loadProject(newId);
    expect(detail.project.data).toBeNull();
    expect(detail.project.etag).toBeNull();
    expect(store.current).toBeNull();
    expect(store.currentEtag).toBeNull();
    await expect(switched).rejects.toBeTruthy();
    expect(detail.project.data).toBeNull();
    expect(store.current).toBeNull();
  });
});
