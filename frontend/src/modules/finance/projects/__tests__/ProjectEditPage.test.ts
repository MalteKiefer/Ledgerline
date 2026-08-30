// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Project } from '@spa/modules/finance/models/project';
import ProjectEditPage from '@spa/modules/finance/projects/ProjectEditPage.vue';
import { projectRoutes } from '@spa/modules/finance/projects/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'finance-projects.add': 'Add project',
    'finance-projects.title': 'Projects',
    'finance-projects.version_conflict': 'This project changed elsewhere. Reload the current version before saving again.',
    'finance-projects.version_conflict_reload': 'Load current version',
    'common.save': 'Save',
  }[key] ?? key),
}));

const projectId = '018f4ca3-224d-7d8d-9f00-848484848484';

function project(overrides: Partial<Project> = {}): Project {
  return {
    id: projectId, parent_id: null, parent_available: true, name: 'Network refresh', kind: 'business', status: 'planned',
    partner_reference: null, starts_on: null, due_on: null, budget_minor: null, currency: 'EUR',
    version: 1, archived: false, created_at: 'now', updated_at: 'now', ...overrides,
  };
}

function http(body: unknown, status = 200, etag = '"1"'): Response {
  return { ok: status >= 200 && status < 300, status, headers: new Headers({ ETag: etag }), text: () => Promise.resolve(JSON.stringify(body)) } as Response;
}

async function mounted(fetchMock: ReturnType<typeof vi.fn>, path: string) {
  vi.stubGlobal('fetch', fetchMock);
  const router = createRouter({ history: createMemoryHistory(), routes: projectRoutes });
  await router.push(path);
  await router.isReady();
  const wrapper = mount(ProjectEditPage, { global: { plugins: [createPinia(), router] } });
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  setToken('project-token');
});

describe('ProjectEditPage', () => {
  it('creates a new project and navigates to its detail route', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce(http(project(), 201));
    const { wrapper, router } = await mounted(fetchMock, '/finance/projects/new');

    await wrapper.get('[data-field="name"] input, input[data-field="name"]').setValue('Network refresh');
    await wrapper.get('[data-action="save"]').trigger('click');
    await flushPromises();

    await vi.waitFor(() => expect(router.currentRoute.value.fullPath).toBe(`/finance/projects/${projectId}`));
  });

  it('shows a version conflict and lets the user reload the server current before retrying', async () => {
    const server = project({ version: 9, name: 'Server won' });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(project(), 200, '"1"'))
      .mockResolvedValueOnce(http({ error: 'version_conflict', current: server }, 409, '"9"'));
    const { wrapper } = await mounted(fetchMock, `/finance/projects/${projectId}/edit`);

    await wrapper.get('[data-action="save"]').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('changed elsewhere');
    await wrapper.get('[data-action="load-conflict"]').trigger('click');
    const nameInput = wrapper.get('input[data-field="name"], [data-field="name"] input');
    expect((nameInput.element as HTMLInputElement).value).toBe('Server won');
  });
});
