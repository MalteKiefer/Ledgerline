import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, ensureCsrf } from '@spa/api/client';

export interface FileFolder { id: number; name: string; file_folder_id: number | null; version: number }
export interface FileLabel { id: number; name: string; color: string }
export interface FileEntry {
  id: number; name: string; mime: string; size: number; file_folder_id: number | null;
  favorite: boolean; tags: string[]; note: string | null; version: number;
  updated_at: string; labels?: FileLabel[];
}
export interface FileVersion {
  id: number; file_id: number; size: number; mime: string | null; sha256: string | null; created_at: string | null;
}
export interface FileShare {
  id: number; token: string; kind: 'file' | 'folder';
  file_id: number | null; file_folder_id: number | null;
  needs_password: boolean; allow_download: boolean; expires_at: string | null; version: number;
}
export interface DuplicateFile { id: number; name: string; size: number; path: string }
export interface FileStats {
  used: number; by_type: Record<string, number>; duplicates: DuplicateFile[][];
}
export interface Usage { used: number; quota: number | null }

/** XSRF-TOKEN cookie reader (the raw-fetch zip path can't go through `api`). */
function xsrfToken(): string {
  const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return m ? decodeURIComponent(m[1]) : '';
}

export const useFilesStore = defineStore('files', () => {
  const folders = ref<FileFolder[]>([]);
  const files = ref<FileEntry[]>([]);
  const labels = ref<FileLabel[]>([]);
  const usage = ref<Usage | null>(null);

  async function load() {
    const r = await api.get<{ folders: FileFolder[]; files: FileEntry[]; labels: FileLabel[]; usage: Usage }>('/api/v1/files/data');
    folders.value = r.folders; files.value = r.files; labels.value = r.labels; usage.value = r.usage;
  }
  async function loadTrash() {
    return api.get<{ files: FileEntry[]; folders: FileFolder[] }>('/api/v1/files/trash');
  }
  async function createFolder(name: string, parent: number | null) {
    await api.post('/api/v1/files/folders', { name, file_folder_id: parent });
    await load();
  }
  async function upload(file: File, folder: number | null) {
    const form = new FormData();
    form.append('file', file);
    if (folder != null) form.append('file_folder_id', String(folder));
    await api.upload('/api/v1/files/entries', form);
  }
  const rename = (f: FileEntry, name: string) => api.put(`/api/v1/files/entries/${f.id}`, { name, version: f.version });
  const move = (f: FileEntry, folder: number | null) => api.put(`/api/v1/files/entries/${f.id}`, { file_folder_id: folder, version: f.version });
  const toggleFav = (f: FileEntry) => api.post(`/api/v1/files/entries/${f.id}/toggle`, { field: 'favorite' });
  const trashFile = (f: FileEntry) => api.delete(`/api/v1/files/entries/${f.id}`);
  const restoreFile = (id: number) => api.post(`/api/v1/files/entries/${id}/restore`);
  const forceFile = (id: number) => api.delete(`/api/v1/files/entries/${id}/force`);
  const renameFolder = (fo: FileFolder, name: string) => api.put(`/api/v1/files/folders/${fo.id}`, { name, version: fo.version });
  const trashFolder = (fo: FileFolder) => api.delete(`/api/v1/files/folders/${fo.id}`);
  const emptyTrash = () => api.post('/api/v1/files/entries/trash/empty');
  const search = (q: string) => api.get<{ files: FileEntry[] }>(`/api/v1/files/search?q=${encodeURIComponent(q)}`);

  // ---- Info / tags / note ----
  const updateEntry = (f: FileEntry, patch: { name?: string; tags?: string[]; note?: string | null; file_folder_id?: number | null }) =>
    api.put<{ file: FileEntry }>(`/api/v1/files/entries/${f.id}`, { ...patch, version: f.version });

  // ---- Labels ----
  const createLabel = (name: string, color: string) => api.post<{ label: FileLabel }>('/api/v1/files/labels', { name, color });
  const updateLabel = (id: number, name: string, color: string) => api.put<{ label: FileLabel }>(`/api/v1/files/labels/${id}`, { name, color });
  const deleteLabel = (id: number) => api.delete(`/api/v1/files/labels/${id}`);
  const setFileLabels = (f: FileEntry, labelIds: number[]) =>
    api.post<{ file: FileEntry }>(`/api/v1/files/entries/${f.id}/labels`, { label_ids: labelIds });

  // ---- Versions ----
  const versions = (f: FileEntry) => api.get<{ versions: FileVersion[] }>(`/api/v1/files/entries/${f.id}/versions`);
  const restoreVersion = (f: FileEntry, version: number) => api.post<{ file: FileEntry }>(`/api/v1/files/entries/${f.id}/versions/${version}/restore`);
  const versionRawUrl = (f: FileEntry, version: number) => `/api/v1/files/entries/${f.id}/versions/${version}/raw?download=1`;

  // ---- Public share links ----
  interface SharePayload { password?: string; remove_password?: boolean; allow_download?: boolean; expires_at?: string | null }
  const createShare = (fileId: number, payload: SharePayload = {}) =>
    api.post<{ share: FileShare }>('/api/v1/files/rel-shares', { kind: 'file', file_id: fileId, ...payload });
  const updateShare = (id: number, version: number, payload: SharePayload) =>
    api.put<{ share: FileShare }>(`/api/v1/files/rel-shares/${id}`, { ...payload, version });
  const deleteShare = (id: number) => api.delete(`/api/v1/files/rel-shares/${id}`);
  const shareUrl = (token: string) => `${window.location.origin}/file-share/${token}`;

  // ---- Storage stats ----
  const stats = () => api.get<FileStats>('/api/v1/files/stats');

  /**
   * ZIP download. Streams a blob via raw fetch (the shared `api` client only
   * decodes JSON), then triggers a browser download through a temporary anchor.
   */
  async function zip(sel: { ids?: number[]; folder_id?: number | null }) {
    await ensureCsrf();
    const res = await fetch('/api/v1/files/zip', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
      },
      body: JSON.stringify(sel),
    });
    if (!res.ok) throw new Error(`zip ${res.status}`);
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `files-${new Date().toISOString().slice(0, 10)}.zip`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  const rawUrl = (f: FileEntry) => `/api/v1/files/entries/${f.id}/raw`;
  const thumbUrl = (f: FileEntry) => `/api/v1/files/entries/${f.id}/thumb`;

  return {
    folders, files, labels, usage,
    load, loadTrash, createFolder, upload, rename, move, toggleFav,
    trashFile, restoreFile, forceFile, renameFolder, trashFolder, emptyTrash, search,
    updateEntry, createLabel, updateLabel, deleteLabel, setFileLabels,
    versions, restoreVersion, versionRawUrl,
    createShare, updateShare, deleteShare, shareUrl,
    stats, zip,
    rawUrl, thumbUrl,
  };
});
