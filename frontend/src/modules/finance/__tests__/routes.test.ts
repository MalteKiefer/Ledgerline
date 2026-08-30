// @vitest-environment jsdom
// createWebHistory() (used by the real router) needs `window`; the project's
// default vitest environment is 'node', so this file opts into jsdom alone.
import { describe, expect, it } from 'vitest';
import { router } from '@spa/router';

/**
 * The invoice/payment/recurring module routes activated on the global router
 * (Task 17 cutover) resolve to their intended page component and take
 * priority over the legacy 'finance/:section?' catch-all for the same static
 * path -- a client hitting /finance/invoices must land on the new module
 * page, never inside a Finance.vue tab named "invoices".
 */
describe('invoicePaymentRecurringRoutes activation', () => {
  it.each([
    ['/finance/invoices', 'finance.invoices.index'],
    ['/finance/invoices/new', 'finance.invoices.new'],
    ['/finance/invoices/abc-123', 'finance.invoices.show'],
    ['/finance/invoices/abc-123/edit', 'finance.invoices.edit'],
    ['/finance/payments', 'finance.payments.index'],
    ['/finance/payments/abc-123', 'finance.payments.show'],
    ['/finance/recurring-invoices', 'finance.recurring-invoices.index'],
    ['/finance/recurring-invoices/new', 'finance.recurring-invoices.new'],
    ['/finance/recurring-invoices/abc-123/edit', 'finance.recurring-invoices.edit'],
    ['/finance/recurring-invoices/abc-123/runs', 'finance.recurring-invoices.runs'],
  ])('%s resolves to %s, not the finance tab catch-all', (path, expectedName) => {
    const resolved = router.resolve(path);
    expect(resolved.name).toBe(expectedName);
    expect(resolved.name).not.toBe('finance');
  });

  it('leaves other finance sections on the legacy Finance.vue tab route', () => {
    const resolved = router.resolve('/finance/partners');
    expect(resolved.name).toBe('finance');
    expect(resolved.params.section).toBe('partners');
  });

  it('named route params still build the same paths', () => {
    expect(router.resolve({ name: 'finance.invoices.show', params: { invoice: 'xyz' } }).path)
      .toBe('/finance/invoices/xyz');
    expect(router.resolve({ name: 'finance.recurring-invoices.runs', params: { template: 'xyz' } }).path)
      .toBe('/finance/recurring-invoices/xyz/runs');
  });
});
