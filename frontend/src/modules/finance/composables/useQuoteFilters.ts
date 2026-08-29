import { ref, watch, type Ref } from 'vue';
import { useRoute, useRouter, type LocationQuery, type Router } from 'vue-router';
import type { QuoteEffectiveStatus, QuoteStatus } from '@spa/modules/finance/models/quote';

export interface QuoteFilters {
  q: string;
  status: QuoteStatus | null;
  effective_status: QuoteEffectiveStatus | null;
  sort: 'published_at';
  direction: 'desc';
  page: number;
}

interface FilterRoute { query: LocationQuery }
type FilterRouter = Pick<Router, 'replace'>;

const statuses: readonly QuoteStatus[] = ['draft', 'sent', 'accepted', 'declined', 'converted'];
const effectiveStatuses: readonly QuoteEffectiveStatus[] = [...statuses, 'expired'];
const filterKeys = ['q', 'status', 'effective_status', 'sort', 'direction', 'page'] as const;

function scalar(value: unknown): string | null {
  return typeof value === 'string' ? value : null;
}

export function parseQuoteFilters(query: Record<string, unknown>): QuoteFilters {
  const status = scalar(query.status);
  const effectiveStatus = scalar(query.effective_status);
  const page = Number(scalar(query.page));

  return {
    q: scalar(query.q) ?? '',
    status: statuses.includes(status as QuoteStatus) ? status as QuoteStatus : null,
    effective_status: effectiveStatuses.includes(effectiveStatus as QuoteEffectiveStatus)
      ? effectiveStatus as QuoteEffectiveStatus
      : null,
    sort: 'published_at',
    direction: 'desc',
    page: Number.isInteger(page) && page > 0 ? page : 1,
  };
}

export function serializeQuoteFilters(filters: QuoteFilters): Record<string, string> {
  return {
    ...(filters.q === '' ? {} : { q: filters.q }),
    ...(filters.status === null ? {} : { status: filters.status }),
    ...(filters.effective_status === null ? {} : { effective_status: filters.effective_status }),
    sort: filters.sort,
    direction: filters.direction,
    page: String(filters.page),
  };
}

export function useQuoteFilters(
  route: FilterRoute = useRoute(),
  router: FilterRouter = useRouter(),
): {
  filters: Ref<QuoteFilters>;
  update: (patch: Partial<QuoteFilters>) => Promise<void>;
  setPage: (page: number) => Promise<void>;
} {
  const filters = ref(parseQuoteFilters(route.query));

  watch(() => route.query, (query) => {
    filters.value = parseQuoteFilters(query);
  }, { deep: true });

  async function write(next: QuoteFilters): Promise<void> {
    filters.value = next;
    const query: LocationQuery = { ...route.query };
    for (const key of filterKeys) delete query[key];
    Object.assign(query, serializeQuoteFilters(next));
    await router.replace({ query });
  }

  async function update(patch: Partial<QuoteFilters>): Promise<void> {
    await write({ ...filters.value, ...patch, page: 1 });
  }

  async function setPage(page: number): Promise<void> {
    await write({ ...filters.value, page: Number.isInteger(page) && page > 0 ? page : 1 });
  }

  return { filters, update, setPage };
}
