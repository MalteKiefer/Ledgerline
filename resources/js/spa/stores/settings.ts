import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

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
  encrypt: boolean;
  destination_id: number | null;
  schedule?: string | null;
}

export interface BackupDestination { id: number; name: string; driver: string }
export interface BackupRun { id: number; job_id: number | null; status: string; created_at: string; bytes: number | null; archives?: { source: string; ext: string }[] }
export interface AuditRow { id: number; action: string; actor: string | null; ip: string | null; created_at: string; meta?: Record<string, unknown> }

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

  // Security log
  const loadAudit = async (params = '') => { const r = await api.get<{ data?: AuditRow[] }>(`/api/v1/security-log${params}`); audit.value = r.data ?? []; };

  return {
    users, groups, jobs, destinations, runs, audit,
    loadUsers, createUser, updateUser, deleteUser, resetPassword, reset2fa, inviteLink,
    loadGroups, createGroup, updateGroup, deleteGroup,
    loadBackup, runJob, deleteJob, loadAudit,
  };
});
