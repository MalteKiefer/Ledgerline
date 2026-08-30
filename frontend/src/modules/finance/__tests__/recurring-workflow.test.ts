// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { RecurringInvoiceRun, RecurringInvoiceTemplate } from '@spa/modules/finance/models/recurring';
import RecurringInvoiceRunsPage from '@spa/modules/finance/recurring/RecurringInvoiceRunsPage.vue';
import { invoicePaymentRecurringRoutes } from '@spa/modules/finance/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'common.loading': 'Loading…',
    'common.back': 'Back',
    'invoices.recurring_runs': 'Runs',
    'invoices.recurring_runs_empty': 'No runs yet.',
    'invoices.recurring_run_retry': 'Retry',
    'invoices.recurring_run_status_pending': 'Pending',
    'invoices.recurring_run_status_failed': 'Failed',
    'invoices.recurring_run_status_sent': 'Sent',
  }[key] ?? key),
}));

const templateId = '018f4ca3-224d-7d8d-9f00-222222222222';
const runId = '018f4ca3-224d-7d8d-9f01-222222222222';

function run(status: RecurringInvoiceRun['status'], overrides: Partial<RecurringInvoiceRun> = {}): RecurringInvoiceRun {
  return {
    id: runId,
    scheduled_for: '2026-09-28T06:00:00+00:00',
    scheduled_local_date: '2026-09-28',
    status,
    last_completed_step: null,
    attempts: 1,
    claimed_at: null,
    claim_expires_at: null,
    next_retry_at: null,
    last_error_code: status === 'failed' ? 'invoice_finalization_conflict' : null,
    created_at: '2026-09-28T06:00:00+00:00',
    updated_at: '2026-09-28T06:00:00+00:00',
    ...overrides,
  };
}

function runPage(items: RecurringInvoiceRun[]): { data: RecurringInvoiceRun[]; links: unknown; meta: unknown } {
  return {
    data: items,
    links: { first: '', last: '', prev: null, next: null },
    meta: { current_page: 1, per_page: 20, total: items.length, last_page: 1 },
  };
}

function http(body: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

beforeEach(() => {
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValue('11111111-1111-4111-8111-111111111111') });
  setToken('recurring-token');
});

describe('recurring run retry', () => {
  it('shows a failed run truthfully (never an optimistic success) and lets the user retry it from its persisted safe step', async () => {
    const failed = run('failed');
    const resumed = run('pending', { attempts: 1, last_error_code: null });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(runPage([failed]))) // initial runs load
      .mockResolvedValueOnce(http(run('pending'))) // POST retry response
      .mockResolvedValueOnce(http(runPage([resumed]))); // runs reloaded after retry
    vi.stubGlobal('fetch', fetchMock);
    const router = createRouter({ history: createMemoryHistory(), routes: invoicePaymentRecurringRoutes });
    await router.push(`/finance/recurring-invoices/${templateId}/runs`);
    await router.isReady();
    const wrapper = mount(RecurringInvoiceRunsPage, { global: { plugins: [createPinia(), router] } });
    await flushPromises();

    expect(wrapper.text()).toContain('Failed');
    expect(wrapper.text()).toContain('invoice_finalization_conflict');

    await wrapper.get('button[data-action="retry"]').trigger('click');
    await flushPromises();

    expect(fetchMock.mock.calls[1]![0]).toBe(`/api/v1/finance/recurring-invoice-runs/${runId}/retry`);
    expect(wrapper.text()).toContain('Pending');
    expect(wrapper.text()).not.toContain('invoice_finalization_conflict');
  });
});

describe('recurring template version conflict', () => {
  it('surfaces a version conflict from the store instead of silently overwriting a concurrent edit', async () => {
    const { useRecurringStore } = await import('@spa/modules/finance/stores/recurring');
    const { setActivePinia, createPinia: createPiniaInstance } = await import('pinia');
    setActivePinia(createPiniaInstance());
    const store = useRecurringStore();

    const current: RecurringInvoiceTemplate = {
      id: templateId,
      mode: 'draft',
      interval: 'monthly',
      timezone: 'Europe/Berlin',
      start_date: '2026-08-28',
      end_date: null,
      run_time: '08:00:00',
      anchor_day: 28,
      month_end_anchor: false,
      next_run_at: '2026-09-28T06:00:00+00:00',
      status: 'active',
      paused_at: null,
      current_version: { number: 2, effective_from: '2026-09-28', snapshot_sha256: 'b'.repeat(64) },
      version: 2,
    };
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(http(
      { error: 'recurring_template_version_conflict', current },
      409,
    )));

    await expect(store.pause(templateId, 0, 'pause-key')).rejects.toThrow();
    expect(store.actionError).toBe('recurring_template_version_conflict');
    // The store adopted the server's current state instead of pretending the stale pause succeeded.
    expect(store.current?.version).toBe(2);
    expect(store.current?.status).toBe('active');
  });
});
