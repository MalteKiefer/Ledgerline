import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ApiError, api } from '@spa/api/client';

/** Key only: OpenSSH takes no password without a terminal. */
export type AuthType = 'key';

export interface ServerDisk { fs: string; mount: string; size_kb: number; used_kb: number; avail_kb: number; used_pct: number }
export interface ServerContainer { name: string; status: string }

export interface ServerFacts {
  hostname: string | null;
  os: { name: string | null; id: string | null; id_like: string | null; version: string | null };
  kernel: string | null;
  arch: string | null;
  uptime_s: number | null;
  load: number[];
  /** used_pct is measured from two /proc/stat samples, not inferred from load. */
  cpu: { cores: number | null; model: string | null; used_pct: number | null };
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
  /** Interface + CIDR per line, e.g. "eth0 192.168.3.200/24". */
  addresses: string[];
  /** Hypervisor, or null on bare metal — "none" is not reported as a type. */
  virt: string | null;
  boot_at: string | null;
  sessions: string[];
  /** Largest resident processes; memory, not CPU — see the parser for why. */
  processes: { name: string; rss_kb: number }[];
  temp_c: number | null;
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
  /** Whether the setup created the account; null for rows predating the field. */
  account_created: boolean | null;
  host_fingerprint: string | null;
  /** Extra TCP ports to watch. The SSH port is always checked and is not listed here. */
  monitor_ports: { port: number; label: string | null }[];
  /** Only returned by show(): derived from the stored key for the removal steps. */
  public_key?: string | null;
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
  cpu_used_pct: number | null;
  disk_max_pct: number | null;
}

/** One reachability sample. */
export interface CheckPoint { t: string; ms: number | null; ok: boolean }

export interface ServerCheckSeries {
  /** 'icmp' (no port) or 'tcp'. */
  kind: string;
  port: number | null;
  /** 'SSH' for the port we always check, the owner's label otherwise. */
  label: string | null;
  uptime_pct: number;
  samples: number;
  last: { ok: boolean; ms: number | null; error: string | null; t: string } | null;
  points: CheckPoint[];
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

  /** Reachability history. Bounded by hours so the answer does not shift with port count. */
  const checks = (id: number, hours = 24) =>
    api.get<{ hours: number; checks: ServerCheckSeries[] }>(`/api/v1/servers/${id}/checks?hours=${hours}`);

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

  /**
   * Generate a keypair for a server about to be added. Only the public half comes
   * back — the private key waits on the server under `token` and is redeemed by
   * test()/create(), so it never passes through the browser.
   */
  const keypair = () => api.post<{ token: string; public_key: string; expires_in_minutes: number }>('/api/v1/servers/keypair', {});

  return { servers, load, show, checks, create, update, remove, refresh, refreshAll, test, testStored, probeScript, keypair };
});
