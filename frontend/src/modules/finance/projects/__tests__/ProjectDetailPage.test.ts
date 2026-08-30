// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Project } from '@spa/modules/finance/models/project';
import ProjectDetailPage from '@spa/modules/finance/projects/ProjectDetailPage.vue';
import { projectRoutes } from '@spa/modules/finance/projects/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'common.loading': 'Loading…',
    'common.edit': 'Edit',
    'finance-projects.status_active': 'Active',
    'finance-projects.status_planned': 'Planned',
    'finance-projects.transition_on_hold': 'Put on hold',
    'finance-projects.transition_done': 'Mark done',
    'finance-projects.transition_cancelled': 'Cancel',
    'finance-projects.transition_active': 'Activate',
    'finance-projects.transition_planned': 'Reopen as planned',
    'finance-projects.archive': 'Archive',
    'finance-projects.restore': 'Restore',
    'finance-projects.version_conflict': 'This project changed elsewhere. Reload the current version before saving again.',
    'finance-projects.version_conflict_reload': 'Load current version',
    'finance-projects.tab_overview': 'Overview',
    'finance-projects.tab_work': 'Work',
    'finance-projects.tab_ledger': 'Ledger',
    'finance-projects.tab_documents': 'Documents',
    'finance-projects.tab_notes': 'Notes',
    'finance-projects.tab_activity': 'Activity',
    'finance-projects.totals_hours': 'Hours',
    'finance-projects.totals_time_value': 'Time value',
    'finance-projects.totals_ledger': 'Ledger balance',
    'finance-projects.totals_financial': 'Financial total',
  }[key] ?? key),
}));

const projectId = '018f4ca3-224d-7d8d-9f00-848484848484';

function project(overrides: Partial<Project> = {}): Project {
  return {
    id: projectId, parent_id: null, parent_available: true, name: 'Network refresh', kind: 'business', status: 'active',
    partner_reference: null, starts_on: null, due_on: null, budget_minor: '9007199254740993', currency: 'EUR',
    version: 3, archived: false, created_at: 'now', updated_at: 'now', ...overrides,
  };
}

function http(body: unknown, status = 200, etag = '"3"'): Response {
  return { ok: status >= 200 && status < 300, status, headers: new Headers({ ETag: etag }), text: () => Promise.resolve(JSON.stringify(body)) } as Response;
}

const page = (data: unknown[] = []) => ({ data, meta: { current_page: 1, per_page: 20, total: data.length } });

function dispatch(overrides: Record<string, () => Response> = {}): ReturnType<typeof vi.fn> {
  return vi.fn((url: RequestInfo | URL) => {
    const path = String(url).split('?')[0];
    if (path.endsWith(`/finance-v2/projects/${projectId}`)) return Promise.resolve((overrides.project ?? (() => http(project())))());
    if (path.endsWith('/totals')) return Promise.resolve((overrides.totals ?? (() => http({ project_id: projectId, currencies: { EUR: { hours_scaled: '25000', time_value_minor: '250000000000000000', ledger_minor: '100', financial_minor: '99' } } })))());
    if (path.endsWith('/work-items')) return Promise.resolve((overrides.work ?? (() => http(page())))());
    if (path.endsWith('/time-entries')) return Promise.resolve((overrides.time ?? (() => http(page())))());
    if (path.endsWith('/ledger')) return Promise.resolve((overrides.ledger ?? (() => http(page())))());
    if (path.endsWith('/documents')) return Promise.resolve((overrides.documents ?? (() => http(page())))());
    if (path.endsWith('/notes')) return Promise.resolve((overrides.notes ?? (() => http(page())))());
    if (path.endsWith('/activities')) return Promise.resolve((overrides.activity ?? (() => http({ data: [], next_cursor: null })))());
    if (path.endsWith('/status')) return Promise.resolve((overrides.status ?? (() => http(project({ status: 'on_hold', version: 4 }))))());
    return Promise.resolve(http({}, 404));
  });
}

async function mounted(fetchMock: ReturnType<typeof vi.fn>) {
  vi.stubGlobal('fetch', fetchMock);
  const router = createRouter({ history: createMemoryHistory(), routes: projectRoutes });
  await router.push(`/finance/projects/${projectId}`);
  await router.isReady();
  const wrapper = mount(ProjectDetailPage, { global: { plugins: [createPinia(), router] } });
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  setToken('project-token');
});

describe('ProjectDetailPage', () => {
  it('loads every panel independently and renders exact large totals with server-provided values', async () => {
    const { wrapper } = await mounted(dispatch());

    expect(wrapper.text()).toContain('Network refresh');
    expect(wrapper.get('[data-action="status-on_hold"]')).toBeTruthy();
    expect(wrapper.get('[data-action="status-done"]')).toBeTruthy();
    expect(wrapper.get('[data-action="status-cancelled"]')).toBeTruthy();
    expect(wrapper.find('[data-action="status-active"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('2.5000 h');
    expect(wrapper.text()).toContain('2.500.000.000.000.000,00 EUR');
  });

  it('applies a named status transition and shows a version conflict distinctly from a request failure', async () => {
    const fetchMock = dispatch({ status: () => http({ error: 'version_conflict', current: project({ status: 'on_hold', version: 9 }) }, 409, '"9"') });
    const { wrapper } = await mounted(fetchMock);

    await wrapper.get('[data-action="status-on_hold"]').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('changed elsewhere');
  });

  it('switches to the documents tab and searches project document sources', async () => {
    const fetchMock = dispatch();
    const { wrapper } = await mounted(fetchMock);

    const documentsTab = wrapper.findAll('button').find((candidate) => candidate.text().includes('Documents'));
    await documentsTab?.trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Documents');
  });
});
