import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, uploadWithProgress } from '@spa/api/client';

export interface Mount { id: number; name: string; type: 's3' | 'sftp'; read_only: boolean }
export interface MountEntry { name: string; path: string; size?: number | null; last_modified?: number | null }
export interface MountListing { path: string; dirs: MountEntry[]; files: MountEntry[]; read_only: boolean }

export const useMountsStore = defineStore('mounts', () => {
  const mounts = ref<Mount[]>([]);

  const load = () => api.get<{ mounts: Mount[] }>('/api/v1/mounts').then((r) => { mounts.value = r.mounts ?? []; });
  const create = (body: Record<string, unknown>) => api.post<{ mount: Mount }>('/api/v1/mounts', body).then((r) => r.mount);
  const update = (id: number, body: Record<string, unknown>) => api.put<{ mount: Mount }>(`/api/v1/mounts/${id}`, body).then((r) => r.mount);
  const remove = (id: number) => api.delete(`/api/v1/mounts/${id}`);
  const test = (body: Record<string, unknown>) => api.post<{ ok: boolean; message?: string }>('/api/v1/mounts/test', body);

  const list = (id: number, path = '') => api.get<MountListing>(`/api/v1/mounts/${id}/list?path=${encodeURIComponent(path)}`);
  const downloadUrl = (id: number, path: string) => api.streamUrl(`/api/v1/mounts/${id}/file?path=${encodeURIComponent(path)}`);
  const upload = (id: number, file: File, path: string, onProgress?: (fr: number) => void) => {
    const fd = new FormData(); fd.append('file', file); fd.append('path', path);
    return uploadWithProgress<{ ok: boolean; path: string }>(`/api/v1/mounts/${id}/upload`, fd, onProgress);
  };
  const mkdir = (id: number, path: string, name: string) => api.post(`/api/v1/mounts/${id}/mkdir`, { path, name });
  const deletePath = (id: number, path: string, dir: boolean) => api.post(`/api/v1/mounts/${id}/delete`, { path, dir });

  return { mounts, load, create, update, remove, test, list, downloadUrl, upload, mkdir, deletePath };
});
