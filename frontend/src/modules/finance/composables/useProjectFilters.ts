import { ref, watch, type Ref } from 'vue';
import { useRoute, useRouter, type LocationQuery, type Router } from 'vue-router';
import type { ProjectKind, ProjectSort, ProjectStatus } from '@spa/modules/finance/models/project';

export interface ProjectFilters {
  q: string; status: ProjectStatus | null; kind: ProjectKind | null; parent_id: string | null; partner_reference: string | null;
  archived: boolean; starts_from: string | null; starts_to: string | null; due_from: string | null; due_to: string | null;
  sort: ProjectSort; direction: 'asc' | 'desc'; page: number;
}
interface FilterRoute { query: LocationQuery }
type FilterRouter = Pick<Router, 'replace'>;

const statuses: readonly ProjectStatus[] = ['planned', 'active', 'on_hold', 'done', 'cancelled'];
const kinds: readonly ProjectKind[] = ['business', 'private'];
const sorts: readonly ProjectSort[] = ['updated_at', 'name', 'starts_on', 'due_on', 'status'];
const keys = ['q', 'status', 'kind', 'parent_id', 'partner_reference', 'archived', 'starts_from', 'starts_to', 'due_from', 'due_to', 'sort', 'direction', 'page'] as const;
const scalar = (value: unknown): string | null => typeof value === 'string' ? value : null;
const date = (value: unknown): string | null => /^\d{4}-\d{2}-\d{2}$/.test(scalar(value) ?? '') ? scalar(value) : null;

export function parseProjectFilters(query: Record<string, unknown>): ProjectFilters {
  const status = scalar(query.status);
  const kind = scalar(query.kind);
  const sort = scalar(query.sort);
  const direction = scalar(query.direction);
  const page = Number(scalar(query.page));
  return {
    q: scalar(query.q) ?? '',
    status: statuses.includes(status as ProjectStatus) ? status as ProjectStatus : null,
    kind: kinds.includes(kind as ProjectKind) ? kind as ProjectKind : null,
    parent_id: scalar(query.parent_id),
    partner_reference: scalar(query.partner_reference),
    archived: scalar(query.archived) === 'true',
    starts_from: date(query.starts_from), starts_to: date(query.starts_to), due_from: date(query.due_from), due_to: date(query.due_to),
    sort: sorts.includes(sort as ProjectSort) ? sort as ProjectSort : 'updated_at',
    direction: direction === 'asc' || direction === 'desc' ? direction : 'desc',
    page: Number.isInteger(page) && page > 0 ? page : 1,
  };
}

export function serializeProjectFilters(filters: ProjectFilters): Record<string, string> {
  return {
    ...(filters.q ? { q: filters.q } : {}), ...(filters.status ? { status: filters.status } : {}), ...(filters.kind ? { kind: filters.kind } : {}),
    ...(filters.parent_id ? { parent_id: filters.parent_id } : {}), ...(filters.partner_reference ? { partner_reference: filters.partner_reference } : {}),
    archived: String(filters.archived), ...(filters.starts_from ? { starts_from: filters.starts_from } : {}), ...(filters.starts_to ? { starts_to: filters.starts_to } : {}),
    ...(filters.due_from ? { due_from: filters.due_from } : {}), ...(filters.due_to ? { due_to: filters.due_to } : {}),
    sort: filters.sort, direction: filters.direction, page: String(filters.page),
  };
}

export function useProjectFilters(route: FilterRoute = useRoute(), router: FilterRouter = useRouter()): {
  filters: Ref<ProjectFilters>; update: (patch: Partial<ProjectFilters>) => Promise<void>; setPage: (page: number) => Promise<void>;
} {
  const filters = ref(parseProjectFilters(route.query));
  watch(() => route.query, (query) => { filters.value = parseProjectFilters(query); }, { deep: true });
  async function write(next: ProjectFilters): Promise<void> {
    filters.value = next;
    const query: LocationQuery = { ...route.query };
    for (const key of keys) delete query[key];
    Object.assign(query, serializeProjectFilters(next));
    await router.replace({ query });
  }
  const update = (patch: Partial<ProjectFilters>) => write({ ...filters.value, ...patch, page: 1 });
  const setPage = (page: number) => write({ ...filters.value, page: Number.isInteger(page) && page > 0 ? page : 1 });
  return { filters, update, setPage };
}
