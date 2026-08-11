import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, uploadWithProgress } from '@spa/api/client';

export interface Photo {
  id: number; name: string; mime: string | null;
  width: number | null; height: number | null; size: number;
  favorite: boolean; thumb: boolean; motion: boolean;
  media_type: 'image' | 'video'; status: 'ready' | 'processing' | 'failed'; duration: number | null;
  rotation: number; flip_h: boolean;
  taken_at: string | null; camera: string | null; place: string | null;
  lat: number | null; lng: number | null; version: number; created_at: string | null;
}

export interface PhotoEdit {
  taken_at?: string | null; place?: string | null;
  lat?: number | null; lng?: number | null;
  rotation?: number; flip_h?: boolean; version?: number;
}

export interface Album {
  id: number; name: string; count: number; cover_photo_id: number | null; version: number;
}

const WHOLE_LIMIT = 8 * 1024 * 1024;

export const useGalleryStore = defineStore('gallery', () => {
  const photos = ref<Photo[]>([]);
  const albums = ref<Album[]>([]);

  const load = (albumId?: number) => {
    const q = albumId ? `?album_id=${albumId}` : '';
    return api.get<{ photos: Photo[] }>(`/api/v1/gallery/data${q}`).then((r) => { photos.value = r.photos ?? []; });
  };
  const trash = () => api.get<{ photos: Photo[] }>('/api/v1/gallery/trash').then((r) => r.photos);
  const search = (q: string) => api.get<{ photos: Photo[] }>(`/api/v1/gallery/search?q=${encodeURIComponent(q)}`).then((r) => r.photos ?? []);
  const duplicates = () => api.get<{ groups: { photos: Photo[] }[] }>('/api/v1/gallery/duplicates').then((r) => r.groups ?? []);
  const loadAlbums = () => api.get<{ albums: Album[] }>('/api/v1/gallery/albums').then((r) => { albums.value = r.albums ?? []; });

  type UploadResult = { photo: Photo; duplicate?: boolean };

  async function upload(file: File, onProgress?: (fr: number) => void): Promise<UploadResult> {
    if (file.size > WHOLE_LIMIT) return uploadChunked(file, onProgress);
    const fd = new FormData();
    fd.append('file', file);
    return uploadWithProgress<UploadResult>('/api/v1/gallery', fd, onProgress);
  }
  async function uploadChunked(file: File, onProgress?: (fr: number) => void): Promise<UploadResult> {
    const init = await api.post<{ id: string; partSize: number }>('/api/v1/gallery/chunk/init', { name: file.name, size: file.size });
    const partSize = init.partSize > 0 ? init.partSize : WHOLE_LIMIT;
    const parts = Math.max(1, Math.ceil(file.size / partSize));
    try {
      for (let i = 0; i < parts; i++) {
        const fd = new FormData();
        fd.append('id', init.id);
        fd.append('index', String(i));
        fd.append('file', file.slice(i * partSize, (i + 1) * partSize), file.name);
        await uploadWithProgress('/api/v1/gallery/chunk/part', fd, (pf) => onProgress?.((i + pf) / parts));
      }
      const res = await api.post<UploadResult>('/api/v1/gallery/chunk/complete', { id: init.id });
      onProgress?.(1);
      return res;
    } catch (e) {
      await api.post('/api/v1/gallery/chunk/abort', { id: init.id }).catch(() => { /* best-effort */ });
      throw e;
    }
  }
  const attachMotion = (photoId: number, file: File, onProgress?: (fr: number) => void) => {
    const fd = new FormData();
    fd.append('file', file);
    return uploadWithProgress<{ photo: Photo }>(`/api/v1/gallery/${photoId}/motion`, fd, onProgress);
  };
  const motionUrl = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/motion`);
  const playUrl = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/play`);

  const favorite = (id: number, fav: boolean) => api.patch(`/api/v1/gallery/${id}/favorite`, { favorite: fav });
  const update = (id: number, patch: PhotoEdit) => api.put<{ photo: Photo }>(`/api/v1/gallery/${id}`, patch);
  const downloadUrl = (id: number, variant: 'original' | 'edited') => api.streamUrl(`/api/v1/gallery/${id}/download?variant=${variant}`);
  const destroy = (id: number) => api.delete(`/api/v1/gallery/${id}`);
  const bulkDestroy = (ids: number[]) => api.post('/api/v1/gallery/bulk-destroy', { ids });
  const restore = (id: number) => api.post(`/api/v1/gallery/${id}/restore`);
  const forceDelete = (id: number) => api.delete(`/api/v1/gallery/${id}/force`);
  const emptyTrash = () => api.post('/api/v1/gallery/trash/empty');

  const createAlbum = (name: string) => api.post<{ album: Album }>('/api/v1/gallery/albums', { name });
  const renameAlbum = (id: number, name: string) => api.put(`/api/v1/gallery/albums/${id}`, { name });
  const setAlbumCover = (id: number, coverPhotoId: number | null) => api.put(`/api/v1/gallery/albums/${id}`, { cover_photo_id: coverPhotoId });
  const deleteAlbum = (id: number) => api.delete(`/api/v1/gallery/albums/${id}`);
  const addToAlbum = (albumId: number, ids: number[]) => api.post(`/api/v1/gallery/albums/${albumId}/photos`, { ids });
  const removeFromAlbum = (albumId: number, ids: number[]) => api.delete(`/api/v1/gallery/albums/${albumId}/photos`, { ids });

  const thumbUrl = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/thumb`);
  const rawUrl = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/raw`);

  return {
    photos, albums, load, trash, search, duplicates, loadAlbums, upload, attachMotion, motionUrl, playUrl, favorite, update, downloadUrl, destroy, bulkDestroy,
    restore, forceDelete, emptyTrash, createAlbum, renameAlbum, setAlbumCover, deleteAlbum,
    addToAlbum, removeFromAlbum, thumbUrl, rawUrl,
  };
});
