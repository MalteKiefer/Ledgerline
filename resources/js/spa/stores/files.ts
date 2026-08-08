import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface FileFolder { id: number; name: string; file_folder_id: number | null; version: number }
export interface FileLabel { id: number; name: string; color: string }
export interface FileEntry {
  id: number; name: string; mime: string; size: number; file_folder_id: number | null;
  favorite: boolean; tags: string[]; note: string | null; version: number;
  updated_at: string; labels?: FileLabel[];
}
export interface Usage { used: number; quota: number | null }

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

  const rawUrl = (f: FileEntry) => `/api/v1/files/entries/${f.id}/raw`;
  const thumbUrl = (f: FileEntry) => `/api/v1/files/entries/${f.id}/thumb`;

  return {
    folders, files, labels, usage,
    load, loadTrash, createFolder, upload, rename, move, toggleFav,
    trashFile, restoreFile, forceFile, renameFolder, trashFolder, emptyTrash, search,
    rawUrl, thumbUrl,
  };
});
