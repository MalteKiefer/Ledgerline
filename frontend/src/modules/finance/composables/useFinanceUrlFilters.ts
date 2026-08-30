import { ref, watch, type Ref } from 'vue';
import { useRoute, useRouter, type LocationQuery, type Router } from 'vue-router';

interface FilterRoute { query: LocationQuery }
type FilterRouter = Pick<Router, 'replace'>;

export interface FinanceUrlFilters<F extends { page: number }> {
  filters: Ref<F>;
  update: (patch: Partial<F>) => Promise<void>;
  setPage: (page: number) => Promise<void>;
}

/**
 * Shared URL-owns-filters mechanism for the invoice, payment, and recurring
 * list/run pages: parses the current route query into a typed filter object,
 * writes filter changes back to the route (resetting to page 1, except a
 * dedicated page change), and re-parses whenever the route query changes —
 * including back/forward navigation, not just this composable's own writes.
 *
 * @param keys every query-string key this filter set owns; used to clear
 *   stale values before writing a new filter set so an old key never lingers.
 */
export function useFinanceUrlFilters<F extends { page: number }>(
  parse: (query: Record<string, unknown>) => F,
  serialize: (filters: F) => Record<string, string>,
  keys: readonly string[],
  route: FilterRoute = useRoute(),
  router: FilterRouter = useRouter(),
): FinanceUrlFilters<F> {
  const filters = ref(parse(route.query)) as Ref<F>;

  watch(() => route.query, (query) => {
    filters.value = parse(query);
  }, { deep: true });

  async function write(next: F): Promise<void> {
    filters.value = next;
    const query: LocationQuery = { ...route.query };
    for (const key of keys) delete query[key];
    Object.assign(query, serialize(next));
    await router.replace({ query });
  }

  async function update(patch: Partial<F>): Promise<void> {
    await write({ ...filters.value, ...patch, page: 1 });
  }

  async function setPage(page: number): Promise<void> {
    await write({ ...filters.value, page: Number.isInteger(page) && page > 0 ? page : 1 });
  }

  return { filters, update, setPage };
}

export function scalar(value: unknown): string | null {
  return typeof value === 'string' ? value : null;
}

export function boolFlag(value: unknown): boolean | null {
  const raw = scalar(value);
  if (raw === '1' || raw === 'true') return true;
  if (raw === '0' || raw === 'false') return false;

  return null;
}

export function pageNumber(value: unknown): number {
  const page = Number(scalar(value));

  return Number.isInteger(page) && page > 0 ? page : 1;
}
