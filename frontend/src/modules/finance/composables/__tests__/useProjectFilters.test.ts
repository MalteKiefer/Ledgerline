import { describe, expect, it } from 'vitest';
import { reactive } from 'vue';
import type { LocationQueryRaw, RouteLocationRaw } from 'vue-router';
import { parseProjectFilters, serializeProjectFilters, useProjectFilters } from '@spa/modules/finance/composables/useProjectFilters';

describe('project URL filters', () => {
  it('round-trips every supported filter and normalizes invalid values', () => {
    const parsed = parseProjectFilters({ q: 'north', status: 'active', kind: 'business', parent_id: 'parent', partner_reference: 'partner', archived: 'true', starts_from: '2026-01-01', starts_to: '2026-02-01', due_from: '2026-03-01', due_to: '2026-04-01', sort: 'name', direction: 'asc', page: '4' });
    expect(serializeProjectFilters(parsed)).toEqual({ q: 'north', status: 'active', kind: 'business', parent_id: 'parent', partner_reference: 'partner', archived: 'true', starts_from: '2026-01-01', starts_to: '2026-02-01', due_from: '2026-03-01', due_to: '2026-04-01', sort: 'name', direction: 'asc', page: '4' });
    expect(parseProjectFilters({ status: 'bad', archived: 'sometimes', sort: 'bad', direction: 'sideways', page: '-1' })).toMatchObject({ status: null, archived: false, sort: 'updated_at', direction: 'desc', page: 1 });
  });

  it('resets only filter changes to page one and preserves unrelated query keys', async () => {
    const route = reactive({ query: { q: 'old', page: '4', tab: 'documents' } });
    const replacements: LocationQueryRaw[] = [];
    const router = { replace: async (target: RouteLocationRaw) => { if (typeof target !== 'string' && 'query' in target && target.query) replacements.push(target.query); } };
    const { filters, update, setPage } = useProjectFilters(route, router);
    await update({ status: 'done' });
    expect(filters.value.page).toBe(1);
    expect(replacements[0]).toMatchObject({ q: 'old', status: 'done', page: '1', tab: 'documents' });
    await setPage(3);
    expect(filters.value.page).toBe(3);
  });
});
