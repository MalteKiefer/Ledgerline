import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

// Admin "security portal" store: verbose request log, IP block-list, per-user
// block toggle, and a cross-user session/device overview. All endpoints are
// admin-gated (can:manage-global-settings) behind the bearer token — this store
// only wraps them; the views enforce nothing extra.

export interface RequestLogRow {
  id: number;
  time: string | null;
  ip: string | null;
  method: string;
  path: string;
  status: number;
  user: { id: number; name: string } | null;
  user_agent: string | null;
  duration_ms: number | null;
}

export interface RequestLogMeta {
  current_page: number;
  last_page: number;
  total: number;
}

/** Filters accepted by the request-log index + export. Empty values are dropped. */
export interface RequestLogParams {
  page?: number;
  per_page?: number;
  ip?: string;
  user_id?: string | number;
  method?: string;
  status?: string | number;
  path?: string;
  since?: string;
}

export interface BlockedIp {
  id: number;
  cidr: string;
  reason: string | null;
  created_at: string | null;
}

export interface WebSession {
  kind: 'web';
  user_id: number;
  ip: string | null;
  user_agent: string | null;
  last_activity: string | null;
}

export interface DeviceSession {
  kind: 'device';
  user_id: number;
  name: string | null;
  ip: string | null;
  last_used_at: string | null;
}

export interface SessionsOverview {
  web: WebSession[];
  devices: DeviceSession[];
}

/** Serialise filters into a query string, dropping empty/undefined values. */
function buildQuery(params: Record<string, unknown>): string {
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null || v === '') continue;
    q.set(k, String(v));
  }
  const s = q.toString();
  return s ? `?${s}` : '';
}

export const useSecurityStore = defineStore('security', () => {
  const requests = ref<RequestLogRow[]>([]);
  const requestMeta = ref<RequestLogMeta>({ current_page: 1, last_page: 1, total: 0 });
  const blocks = ref<BlockedIp[]>([]);
  const sessions = ref<SessionsOverview>({ web: [], devices: [] });

  // Request log
  const loadRequestLog = async (params: RequestLogParams = {}) => {
    const r = await api.get<{ data: RequestLogRow[]; meta: RequestLogMeta }>(
      `/api/v1/admin/request-log${buildQuery(params as Record<string, unknown>)}`,
    );
    requests.value = r.data ?? [];
    requestMeta.value = r.meta ?? { current_page: 1, last_page: 1, total: 0 };
    return r;
  };
  /** Token-carrying absolute URL for the CSV/JSON export (for an <a>/download). */
  const exportRequestLogUrl = (format: 'csv' | 'json', params: RequestLogParams = {}) =>
    api.streamUrl(`/api/v1/admin/request-log/export${buildQuery({ ...(params as Record<string, unknown>), export: format })}`);

  // IP block-list
  const loadBlocks = async () => { blocks.value = (await api.get<{ blocks: BlockedIp[] }>('/api/v1/admin/blocked-ips')).blocks ?? []; };
  const blockIp = (cidr: string, reason?: string) =>
    api.post<{ id: number }>('/api/v1/admin/blocked-ips', { cidr, reason: reason || null });
  const unblockIp = (id: number) => api.delete<{ ok: boolean }>(`/api/v1/admin/blocked-ips/${id}`);

  // Per-user block toggle
  const blockUser = (id: number) => api.post<{ ok: boolean; blocked_at: string }>(`/api/v1/admin/users/${id}/block`);
  const unblockUser = (id: number) => api.post<{ ok: boolean }>(`/api/v1/admin/users/${id}/unblock`);

  // Cross-user sessions overview
  const loadSessions = async () => {
    const r = await api.get<SessionsOverview>('/api/v1/admin/sessions');
    sessions.value = { web: r.web ?? [], devices: r.devices ?? [] };
    return sessions.value;
  };

  return {
    requests, requestMeta, blocks, sessions,
    loadRequestLog, exportRequestLogUrl,
    loadBlocks, blockIp, unblockIp,
    blockUser, unblockUser,
    loadSessions,
  };
});
