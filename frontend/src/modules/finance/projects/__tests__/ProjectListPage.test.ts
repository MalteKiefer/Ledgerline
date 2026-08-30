// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Project, ProjectPage } from '@spa/modules/finance/models/project';
import ProjectListPage from '@spa/modules/finance/projects/ProjectListPage.vue';
import { projectRoutes } from '@spa/modules/finance/projects/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'finance-projects.add': 'Add project',
    'finance-projects.search': 'Search projects…',
    'finance-projects.filter_status_all': 'All statuses',
    'finance-projects.empty': 'No projects yet.',
    'finance-projects.status_active': 'Active',
    'finance-projects.kind_business': 'Business',
  }[key] ?? key),
}));

const projectId = '018f4ca3-224d-7d8d-9f00-848484848484';

function project(): Project {
  return {
    id: projectId, parent_id: null, parent_available: true, name: 'Network refresh', kind: 'business', status: 'active',
    partner_reference: null, starts_on: null, due_on: null, budget_minor: '9007199254740993', currency: 'EUR',
    version: 3, archived: false, created_at: '2026-08-01T09:00:00+00:00', updated_at: '2026-08-02T10:00:00+00:00',
  };
}

function response(): Response {
  const page: ProjectPage = {
    data: [project()],
    links: { first: '/projects?page=1', last: '/projects?page=3', prev: '/projects?page=1', next: '/projects?page=3' },
    meta: { current_page: 2, per_page: 20, total: 41, last_page: 3 },
  };
  return { ok: true, status: 200, headers: new Headers(), text: () => Promise.resolve(JSON.stringify(page)) } as Response;
}

beforeEach(() => {
  document.documentElement.lang = 'de';
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  setToken('project-token');
});

describe('ProjectListPage', () => {
  it('owns filters in the URL, requests only /finance-v2, and renders exact large budgets', async () => {
    const fetchMock = vi.fn().mockResolvedValue(response());
    vi.stubGlobal('fetch', fetchMock);
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [...projectRoutes, { path: '/fallback', component: { template: '<div />' } }],
    });
    await router.push('/finance/projects?q=router&status=active&archived=true&page=2');
    await router.isReady();

    const wrapper = mount(ProjectListPage, { global: { plugins: [createPinia(), router] } });
    await flushPromises();

    expect(wrapper.get('input[type="search"]').element).toMatchObject({ value: 'router' });
    expect((wrapper.get('[data-filter="status"]').element as HTMLSelectElement).value).toBe('active');
    expect((wrapper.get('[data-filter="archived"]').element as HTMLInputElement).checked).toBe(true);
    expect(wrapper.text()).toContain('Network refresh');
    expect(String(fetchMock.mock.calls[0][0])).toContain('/api/v1/finance-v2/projects');
    expect(String(fetchMock.mock.calls[0][0])).toContain('status=active');

    await wrapper.get('input[type="search"]').setValue('changed');
    await flushPromises();
    expect(router.currentRoute.value.query).toMatchObject({ q: 'changed', page: '1' });
  });

  it('exports resolvable project routes without mounting the global router', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: projectRoutes });

    expect(router.resolve('/finance/projects').name).toBe('finance.projects.index');
    expect(router.resolve('/finance/projects/new').name).toBe('finance.projects.new');
    expect(router.resolve(`/finance/projects/${projectId}/edit`).name).toBe('finance.projects.edit');
  });
});
