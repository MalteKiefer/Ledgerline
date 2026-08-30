import { describe, expect, it } from 'vitest';
import { reactive } from 'vue';
import type { LocationQueryRaw, RouteLocationRaw } from 'vue-router';
import {
  parseQuoteFilters,
  serializeQuoteFilters,
  useQuoteFilters,
} from '@spa/modules/finance/composables/useQuoteFilters';

describe('quote URL filters', () => {
  it('round-trips every supported filter and normalizes invalid query values', () => {
    const parsed = parseQuoteFilters({
      q: 'router',
      status: 'sent',
      effective_status: 'expired',
      sort: 'published_at',
      direction: 'desc',
      page: '3',
    });

    expect(parsed).toEqual({
      q: 'router', status: 'sent', effective_status: 'expired', sort: 'published_at', direction: 'desc', page: 3,
    });
    expect(serializeQuoteFilters(parsed)).toEqual({
      q: 'router', status: 'sent', effective_status: 'expired', sort: 'published_at', direction: 'desc', page: '3',
    });
    expect(parseQuoteFilters({ status: 'unknown', effective_status: ['sent'], page: '-2' })).toEqual({
      q: '', status: null, effective_status: null, sort: 'published_at', direction: 'desc', page: 1,
    });
  });

  it('resets page on filter changes while explicit pagination preserves filters', async () => {
    const route = reactive({ query: { q: 'old', status: 'sent', page: '4', unrelated: 'keep' } });
    const replacements: LocationQueryRaw[] = [];
    const router = { replace: async (target: RouteLocationRaw) => {
      if (typeof target === 'string' || ! ('query' in target) || target.query === undefined) throw new Error('Expected query replacement.');
      replacements.push(target.query);
    } };
    const { filters, update, setPage } = useQuoteFilters(route, router);

    await update({ q: 'new', effective_status: 'expired' });
    expect(filters.value.page).toBe(1);
    expect(replacements[0]).toEqual({
      unrelated: 'keep', q: 'new', status: 'sent', effective_status: 'expired', sort: 'published_at', direction: 'desc', page: '1',
    });

    await setPage(5);
    expect(filters.value.page).toBe(5);
    expect(replacements[1]).toMatchObject({ q: 'new', status: 'sent', effective_status: 'expired', page: '5' });
  });
});
