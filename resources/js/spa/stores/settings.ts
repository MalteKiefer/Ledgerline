import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, ensureCsrf } from '@spa/api/client';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'user';
  max_connected_devices: number | null;
  modules: string[] | null;
  groups: { id: number; name: string }[];
  verified: boolean;
  two_factor: boolean;
  last_login_at: string | null;
}

export interface Group {
  id: number;
  name: string;
  shareable?: boolean;
  files_quota_mb: number | null;
  gallery_quota_mb: number | null;
  max_connected_devices: number | null;
  modules: string[] | null;
  members?: number[];
}

export interface BackupJob {
  id: number;
  name: string;
  sources: string[];
  mode: string;
  keep_daily: number | null;
  keep_weekly: number | null;
  keep_monthly: number | null;
  cron: string | null;
  encrypt: boolean;
  notify_channels: string[];
  destination_id: number | null;
  enabled?: boolean;
}

export interface BackupDestination { id: number; name: string; driver: string }

/** One source's archive inside a run's batch — carries the per-source capabilities. */
export interface BackupArchive { source: string; encrypted: boolean; restorable: boolean }

export interface BackupRun {
  id: number;
  status: string;
  message: string | null;
  startedIso: string | null;
  startedHuman: string | null;
  size: number | null;
  source?: string | null;
  encrypted?: boolean;
  restorable?: boolean;
  archives?: BackupArchive[];
  cancellable?: boolean;
  cancelling?: boolean;
  job?: string | null;
}
export interface AuditRow { id: number; action: string; actor: string | null; ip: string | null; at: string | null; user_id: number | null; user_agent?: string | null; meta?: Record<string, unknown> }

export const useSettingsStore = defineStore('settings', () => {
  const users = ref<AdminUser[]>([]);
  const groups = ref<Group[]>([]);
  const jobs = ref<BackupJob[]>([]);
  const destinations = ref<BackupDestination[]>([]);
  const runs = ref<BackupRun[]>([]);
  const audit = ref<AuditRow[]>([]);

  // Users
  const loadUsers = async () => { users.value = (await api.get<{ users: AdminUser[] }>('/api/v1/users')).users; };
  const createUser = (body: Record<string, unknown>) => api.post('/api/v1/users', body);
  const updateUser = (id: number, body: Record<string, unknown>) => api.put(`/api/v1/users/${id}`, body);
  const deleteUser = (id: number) => api.delete(`/api/v1/users/${id}`);
  const resetPassword = (id: number) => api.post<{ link?: string }>(`/api/v1/users/${id}/reset-password`);
  const reset2fa = (id: number) => api.post(`/api/v1/users/${id}/reset-2fa`);
  const inviteLink = (id: number, ttl: string) => api.post<{ url: string }>(`/api/v1/users/${id}/invite-link`, { ttl });

  // Groups
  const loadGroups = async () => { groups.value = (await api.get<{ groups: Group[] }>('/api/v1/groups')).groups; };
  const createGroup = (body: Record<string, unknown>) => api.post('/api/v1/groups', body);
  const updateGroup = (id: number, body: Record<string, unknown>) => api.put(`/api/v1/groups/${id}`, body);
  const deleteGroup = (id: number) => api.delete(`/api/v1/groups/${id}`);

  // Backup
  const loadBackup = async () => {
    const [j, d, r] = await Promise.all([
      api.get<{ jobs: BackupJob[] }>('/api/v1/backup/jobs'),
      api.get<{ destinations: BackupDestination[] }>('/api/v1/backup/destinations'),
      api.get<{ runs: BackupRun[] }>('/api/v1/backup/runs'),
    ]);
    jobs.value = j.jobs ?? []; destinations.value = d.destinations ?? []; runs.value = r.runs ?? [];
  };
  const runJob = (id: number) => api.post(`/api/v1/backup/jobs/${id}/run`);
  const deleteJob = (id: number) => api.delete(`/api/v1/backup/jobs/${id}`);

  // Destinations (credentials write-only: never returned by the list endpoint)
  const saveDestination = (body: Record<string, unknown>, id?: number) =>
    id ? api.put(`/api/v1/backup/destinations/${id}`, body) : api.post('/api/v1/backup/destinations', body);
  const deleteDestination = (id: number) => api.delete(`/api/v1/backup/destinations/${id}`);
  const testDestination = (body: Record<string, unknown>) =>
    api.post<{ ok: boolean; detail?: string }>('/api/v1/backup/destinations/test', body);

  // Jobs
  const saveJob = (body: Record<string, unknown>, id?: number) =>
    id ? api.put(`/api/v1/backup/jobs/${id}`, body) : api.post('/api/v1/backup/jobs', body);

  // Runs — download/verify/decrypt/restore all target one source within a run's batch.
  const verifyRun = (id: number, source: string, passphrase?: string) =>
    api.post<{ ok: boolean; message: string | null; verifiedHuman?: string | null }>(
      `/api/v1/backup/runs/${id}/verify`, { source, passphrase: passphrase || null });
  const cancelRun = (id: number) => api.post<{ ok: boolean; forced: boolean }>(`/api/v1/backup/runs/${id}/cancel`);
  const restoreRun = (id: number, source: string) =>
    api.post<{ ok: boolean; files?: number; message?: string }>(`/api/v1/backup/runs/${id}/restore`, { source });
  const downloadRunUrl = (id: number, source: string) =>
    `/api/v1/backup/runs/${id}/download?source=${encodeURIComponent(source)}`;
  /** POST decrypt streams the plaintext archive — fetch it as a Blob for the caller to save. */
  const decryptRun = async (id: number, source: string, passphrase: string): Promise<Blob> => {
    await ensureCsrf();
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    const xsrf = m ? decodeURIComponent(m[1]) : '';
    const res = await fetch(`/api/v1/backup/runs/${id}/decrypt`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/octet-stream',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify({ source, passphrase }),
    });
    if (!res.ok) throw new Error(String(res.status));
    return res.blob();
  };

  // Security log
  const loadAudit = async (params = '') => { const r = await api.get<{ data?: AuditRow[] }>(`/api/v1/security-log${params}`); audit.value = r.data ?? []; };

  return {
    users, groups, jobs, destinations, runs, audit,
    loadUsers, createUser, updateUser, deleteUser, resetPassword, reset2fa, inviteLink,
    loadGroups, createGroup, updateGroup, deleteGroup,
    loadBackup, runJob, deleteJob,
    saveDestination, deleteDestination, testDestination,
    saveJob, verifyRun, cancelRun, restoreRun, downloadRunUrl, decryptRun,
    loadAudit,
  };
});
