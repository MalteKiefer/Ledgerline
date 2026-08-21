import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ApiError, api } from '@spa/api/client';

export type AuthType = 'password' | 'key';

export interface ServerDisk { fs: string; mount: string; size_kb: number; used_kb: number; avail_kb: number; used_pct: number }
export interface ServerContainer { name: string; status: string }

export interface ServerFacts {
  hostname: string | null;
  os: { name: string | null; id: string | null; version: string | null };
  kernel: string | null;
  arch: string | null;
  uptime_s: number | null;
  load: number[];
  cpu: { cores: number | null; model: string | null };
  mem: {
    total_kb: number | null;
    available_kb: number | null;
    used_pct: number | null;
    swap_total_kb: number | null;
    swap_used_kb: number | null;
  };
  disks: ServerDisk[];
  disk_max_pct: number | null;
  reboot_required: boolean;
  failed_units: string[];
  ports: string[];
  containers: ServerContainer[];
  /** Null where no supported package manager answered — not the same as zero. */
  updates: number | null;
}

export interface ServerStatus { ok: boolean; error: string | null; collected_at: string; duration_ms: number }

export interface Server {
  id: number;
  name: string;
  host: string;
  port: number;
  username: string;
  auth_type: AuthType;
  group: string | null;
  note: string | null;
  enabled: boolean;
  restricted_key: boolean;
  host_fingerprint: string | null;
  status: ServerStatus | null;
  facts: ServerFacts | null;
}

export interface TrendPoint {
  ok: boolean;
  error: string | null;
  collected_at: string;
  duration_ms: number;
  load: number[];
  mem_used_pct: number | null;
  disk_max_pct: number | null;
}

export interface ProbeResult {
  ok: boolean;
  error: string | null;
  fingerprint: string | null;
  facts?: ServerFacts | null;
  duration_ms: number;
}

/**
 * A rejected probe is a 422 whose body has the same shape as a success — it even
 * carries the fingerprint, so a mismatch can be shown. Unwrap it rather than
 * letting the caller handle two shapes.
 */
function probeFrom(e: unknown): ProbeResult {
  const body = e instanceof ApiError ? e.body : null;
  if (body && typeof body === 'object' && 'ok' in body) return body as ProbeResult;
  return { ok: false, error: null, fingerprint: null, duration_ms: 0 };
}

export const useServersStore = defineStore('servers', () => {
  const servers = ref<Server[]>([]);

  const load = () => api.get<{ servers: Server[] }>('/api/v1/servers')
    .then((r) => { servers.value = r.servers ?? []; });

  const show = (id: number) => api.get<{ server: Server; history: TrendPoint[] }>(`/api/v1/servers/${id}`);

  const create = (body: Record<string, unknown>) => api.post<{ server: Server }>('/api/v1/servers', body).then((r) => r.server);
  const update = (id: number, body: Record<string, unknown>) => api.put<{ server: Server }>(`/api/v1/servers/${id}`, body).then((r) => r.server);
  const remove = (id: number) => api.delete(`/api/v1/servers/${id}`);

  /**
   * Probing is queued, never awaited: the endpoint answers 202 and the snapshot
   * shows up in the next load(). The caller decides when to re-read.
   */
  const refresh = (id: number) => api.post(`/api/v1/servers/${id}/refresh`, {});
  const refreshAll = () => api.post<{ queued: number }>('/api/v1/servers/refresh', {});

  /** The only call that opens an SSH session inline. */
  const test = async (body: Record<string, unknown>): Promise<ProbeResult> => {
    try {
      return await api.post<ProbeResult>('/api/v1/servers/test', body);
    } catch (e) {
      return probeFrom(e);
    }
  };

  const testStored = async (id: number): Promise<ProbeResult> => {
    try {
      return await api.post<ProbeResult>(`/api/v1/servers/${id}/test`, {});
    } catch (e) {
      return probeFrom(e);
    }
  };

  const probeScript = () => api.get<{ script: string }>('/api/v1/servers/probe-script').then((r) => r.script);

  return { servers, load, show, create, update, remove, refresh, refreshAll, test, testStored, probeScript };
});
