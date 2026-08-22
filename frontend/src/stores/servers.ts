import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ApiError, api, getToken } from '@spa/api/client';

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
  /** Structured, because the tty is what an operator acts on when ending one. */
  sessions: { user: string; tty: string; since: string; from: string }[];
  /** Largest resident processes; memory, not CPU — see the parser for why. */
  processes: { name: string; rss_kb: number }[];
  temp_c: number | null;
  network: {
    /** The host's default route. Per-interface gateways are on the interface. */
    gateway: string | null;
    dns: string[];
    /** Search domain from resolv.conf. */
    search: string | null;
    interfaces: NetInterface[];
  };
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

/**
 * One interface. Byte counters are totals since boot, not a rate — one snapshot
 * cannot give throughput.
 */
export interface NetInterface {
  name: string;
  rx_bytes: number;
  tx_bytes: number;
  /** bridge, bond, vlan, veth, wireguard, tunnel, wireless or ethernet. */
  kind?: string;
  up?: boolean | null;
  mtu?: number | null;
  mac?: string | null;
  addresses?: string[];
  /** This interface's own default route, where it has one. */
  gateway?: string | null;
  /** Per-link resolvers, where systemd-resolved is in charge. */
  dns?: string[];
}

export interface FirewallInfo {
  name: string;
  present: boolean;
  /** False means we saw it but could not read its rules — not that it has none. */
  readable: boolean;
  active: boolean | null;
  summary: string;
  detail: string;
}

export interface BanInfo { name: string; present: boolean; readable: boolean; summary: string; detail: string }

/** A socket the host is listening on. `exposed` means it answers from off-box. */
export interface ListeningPort {
  proto: string;
  address: string;
  port: number;
  process: string;
  exposed: boolean;
}

/** One judgement about an sshd setting, with the reason spelled out. */
export interface SshFinding { key: string; level: 'ok' | 'warn' | 'danger' | string; note: string }

export interface HostKey { bits: number; fingerprint: string; type: string }
export interface WebServer { name: string; version: string; active: string }
export interface CertificateInfo { path: string; expires: string }

export interface SecurityAudit {
  ok: boolean;
  firewalls: FirewallInfo[];
  bans: BanInfo[];
  ssh: Record<string, string>;
  /** The resolved sshd configuration read through `sshd -T`, judged. */
  ssh_findings: SshFinding[];
  ssh_host_keys: HostKey[];
  ssh_authorized: { path: string; keys: number }[];
  listening: ListeningPort[];
  /** The subset of `listening` bound to a wildcard address. */
  exposed: ListeningPort[];
  addresses: string[];
  web: WebServer[];
  certificates: CertificateInfo[];
  sysctl: Record<string, string>;
  accounts: { empty_password: string[]; uid_zero: string[] };
  sudoers_nopasswd: string[];
  updates: { unattended: boolean; reboot_required: boolean };
  error: string | null;
}

export interface DockerContainer {
  id: string;
  name: string;
  image: string;
  state: string;
  status: string;
  ports: string;
  created: string;
  cpu: string;
  mem: string;
  mem_pct: string;
  net: string;
  block: string;
  health: string;
  compose: string;
}

export interface DockerImage { id: string; repo: string; tag: string; size: string; created: string }
export interface DockerVolume { name: string; driver: string; mount: string }
export interface DockerNetwork { name: string; driver: string; scope: string }
export interface DockerDisk { type: string; total: string; active: string; size: string; reclaimable: string }

export interface DockerState {
  available: boolean;
  /** `not_installed` and `no_access` are opposite answers, never conflated. */
  error: string | null;
  version: string | null;
  containers: DockerContainer[];
  images: DockerImage[];
  volumes: DockerVolume[];
  networks: DockerNetwork[];
  disk: DockerDisk[];
  compose: string[];
}

/** fail2ban groups its bans by jail; CrowdSec keeps one flat list of decisions. */
export interface BanList {
  ok: boolean;
  fail2ban: { jail: string; ips: string[] }[];
  crowdsec: { ip: string; reason: string; expires: string }[];
  error: string | null;
}

export interface FileEntry {
  name: string;
  path: string;
  type: 'file' | 'dir' | 'link' | 'special';
  perms: string;
  owner: string;
  group: string;
  size: number;
  /** As the host printed it: sftp gives no year for a recent file. */
  modified: string;
}

export interface FileListing { ok: boolean; path: string; entries: FileEntry[]; error: string | null }
export interface FileRead { ok: boolean; content: string; binary: boolean; size: number; error: string | null }
export interface FileResult { ok: boolean; error: string | null; output?: string }

export interface FilePermissions {
  ok: boolean;
  mode: string;
  owner: string;
  group: string;
  uid: number;
  gid: number;
  type: string;
  acl: string[];
  /** False means the tools are missing, not that there are no ACLs. */
  acl_supported: boolean;
  users: string[];
  groups: string[];
  error: string | null;
}

export interface ServiceUnit { name: string; load: string; active: string; sub: string; description: string }

export interface ProcessRow { pid: number; user: string; cpu: number; mem: number; rss_kb: number; command: string }

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

  /** What the host can offer: which log systems exist, and the units, containers and files really present. */
  const logSources = (id: number) =>
    api.get<{ journal: boolean; units: string[]; containers: string[]; files: string[]; error: string | null }>(
      `/api/v1/servers/${id}/log-sources`,
    );

  /** Tail one log. The selection must be one the host itself reported. */
  const readLog = (id: number, body: Record<string, unknown>) =>
    api.post<{ text: string }>(`/api/v1/servers/${id}/logs`, body);

  /**
   * Open an interactive session. The account password is required every time
   * and is never remembered anywhere — not here, not on the server.
   */
  const terminalOpen = (id: number, body: Record<string, unknown>) =>
    api.post<{ session: string }>(`/api/v1/servers/${id}/terminal`, body);

  /** Read output from the cursor forward. Bytes arrive base64-encoded. */
  const terminalPoll = (id: number, session: string, cursor: number) =>
    api.get<{ ready: boolean; data: string; cursor: number; closed: string | null }>(
      `/api/v1/servers/${id}/terminal/${session}?cursor=${cursor}`,
    );

  /** Send keystrokes, base64-encoded — a terminal carries control bytes, not text. */
  const terminalInput = (id: number, session: string, data: string) =>
    api.post(`/api/v1/servers/${id}/terminal/${session}/input`, { data });

  const terminalClose = (id: number, session: string) =>
    api.delete(`/api/v1/servers/${id}/terminal/${session}`);

  /** Firewalls, ban daemons, sshd posture and update hygiene, in one round trip. */
  const security = (id: number) => api.get<SecurityAudit>(`/api/v1/servers/${id}/security`);

  const services = (id: number) =>
    api.get<{ ok: boolean; units: ServiceUnit[]; error: string | null }>(`/api/v1/servers/${id}/services`);

  /** Act on a service. The host's own refusal comes back in `output`. */
  const serviceAction = (id: number, unit: string, action: string) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(`/api/v1/servers/${id}/services`, { unit, action });

  const processes = (id: number) =>
    api.get<{ ok: boolean; processes: ProcessRow[]; error: string | null }>(`/api/v1/servers/${id}/processes`);

  const processSignal = (id: number, pid: number, signal: string) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(`/api/v1/servers/${id}/processes/signal`, { pid, signal });

  /** Reboot, force-reboot, shut down, or call off a pending shutdown. */
  const power = (id: number, action: string) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(`/api/v1/servers/${id}/power`, { action });

  /** End somebody's login session by the terminal they are on. */
  const killSession = (id: number, tty: string) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(`/api/v1/servers/${id}/sessions/kill`, { tty });

  const bans = (id: number) => api.get<BanList>(`/api/v1/servers/${id}/bans`);

  // ---- file browser ----
  //
  // Every call carries the unlock grant in a header rather than the URL, so it
  // never lands in an access log or a bookmark.

  const filesUnlock = (id: number, password: string) =>
    api.post<{ token: string; expires_in: number }>(`/api/v1/servers/${id}/files/unlock`, { password });

  const filesLock = (id: number, grant: string) =>
    api.post<{ ok: boolean }>(`/api/v1/servers/${id}/files/lock`, {}, { 'X-File-Grant': grant });

  const filesList = (id: number, grant: string, path: string) =>
    api.get<FileListing>(`/api/v1/servers/${id}/files?path=${encodeURIComponent(path)}`, { 'X-File-Grant': grant });

  const filesRead = (id: number, grant: string, path: string) =>
    api.get<FileRead>(`/api/v1/servers/${id}/files/read?path=${encodeURIComponent(path)}`, { 'X-File-Grant': grant });

  const filesWrite = (id: number, grant: string, path: string, content: string) =>
    api.post<FileResult>(`/api/v1/servers/${id}/files/write`, { path, content }, { 'X-File-Grant': grant });

  const filesMutate = (id: number, grant: string, body: { action: string; path: string; target?: string; mode?: string }) =>
    api.post<FileResult>(`/api/v1/servers/${id}/files/mutate`, body, { 'X-File-Grant': grant });

  const filesUpload = (id: number, grant: string, path: string, file: File) => {
    const form = new FormData();
    form.append('path', path);
    form.append('file', file);

    return api.upload<FileResult>(`/api/v1/servers/${id}/files/upload`, form, { 'X-File-Grant': grant });
  };

  const filesPermissions = (id: number, grant: string, path: string) =>
    api.get<FilePermissions>(`/api/v1/servers/${id}/files/permissions?path=${encodeURIComponent(path)}`, { 'X-File-Grant': grant });

  const filesSetPermissions = (
    id: number,
    grant: string,
    body: { path: string; mode?: string; owner?: string; group?: string; recursive?: boolean; acl?: string[]; acl_remove?: boolean },
  ) => api.post<FileResult>(`/api/v1/servers/${id}/files/permissions`, body, { 'X-File-Grant': grant });

  /** Downloads go through fetch so the grant can ride in the header. */
  const filesDownload = async (id: number, grant: string, path: string): Promise<Blob> => {
    const res = await fetch(api.url(`/api/v1/servers/${id}/files/download?path=${encodeURIComponent(path)}`), {
      headers: { Authorization: `Bearer ${getToken() ?? ''}`, 'X-File-Grant': grant },
    });
    if (!res.ok) throw new Error(String(res.status));

    return res.blob();
  };

  /**
   * A directory as a tar, built on the host.
   *
   * There is no sensible way to hand a browser a tree, and building the archive
   * on the far side means one transfer rather than one per file.
   */
  const filesDownloadDir = async (id: number, grant: string, path: string): Promise<Blob> => {
    const res = await fetch(api.url(`/api/v1/servers/${id}/files/download-dir?path=${encodeURIComponent(path)}`), {
      headers: { Authorization: `Bearer ${getToken() ?? ''}`, 'X-File-Grant': grant },
    });
    if (!res.ok) throw new Error(String(res.status));

    return res.blob();
  };

  /** What this host can pack and unpack - asked, never assumed. */
  const archiveTools = (id: number, grant: string) =>
    api.get<{ pack: string[]; extract: string[] }>(`/api/v1/servers/${id}/files/archive-tools`, { 'X-File-Grant': grant });

  /**
   * Pack a selection on the host and download the single file.
   *
   * POST rather than GET because a selection can run to hundreds of paths, and
   * no query string should be asked to carry that.
   */
  const filesArchive = async (id: number, grant: string, paths: string[], format: string): Promise<Blob> => {
    const res = await fetch(api.url(`/api/v1/servers/${id}/files/archive`), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${getToken() ?? ''}`,
        'X-File-Grant': grant,
      },
      body: JSON.stringify({ paths, format }),
    });
    if (!res.ok) throw new Error(String(res.status));

    return res.blob();
  };

  const filesExtract = (id: number, grant: string, path: string, destination?: string) =>
    api.post<{ ok: boolean; dest: string | null; error: string | null }>(
      `/api/v1/servers/${id}/files/extract`,
      { path, destination },
      { 'X-File-Grant': grant },
    );

  const docker = (id: number) => api.get<DockerState>(`/api/v1/servers/${id}/docker`);

  const dockerAction = (id: number, container: string, action: string) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(
      `/api/v1/servers/${id}/docker/action`,
      { container, action },
    );

  const dockerPrune = (id: number, target: string) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(`/api/v1/servers/${id}/docker/prune`, { target });

  const banAction = (id: number, body: { daemon: string; action: string; ip: string; jail?: string }) =>
    api.post<{ ok: boolean; output: string; error: string | null }>(`/api/v1/servers/${id}/bans`, body);

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

  return { servers, load, show, checks, logSources, readLog, security, services, serviceAction, processes, processSignal, power, killSession, bans, banAction, filesUnlock, filesLock, filesList, filesRead, filesWrite, filesMutate, filesUpload, filesDownload, filesDownloadDir, filesPermissions, filesSetPermissions, archiveTools, filesArchive, filesExtract, docker, dockerAction, dockerPrune, terminalOpen, terminalPoll, terminalInput, terminalClose, create, update, remove, refresh, refreshAll, test, testStored, probeScript, keypair };
});
