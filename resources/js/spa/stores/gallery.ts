import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, uploadWithProgress } from '@spa/api/client';

export interface Photo {
  id: number; name: string; mime: string | null;
  width: number | null; height: number | null; size: number;
  favorite: boolean; taken_at: string | null; camera: string | null;
  lat: number | null; lng: number | null; created_at: string | null;
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
  const loadAlbums = () => api.get<{ albums: Album[] }>('/api/v1/gallery/albums').then((r) => { albums.value = r.albums ?? []; });

  async function upload(file: File, onProgress?: (fr: number) => void) {
    if (file.size > WHOLE_LIMIT) { await uploadChunked(file, onProgress); return; }
    const fd = new FormData();
    fd.append('file', file);
    await uploadWithProgress('/api/v1/gallery', fd, onProgress);
  }
  async function uploadChunked(file: File, onProgress?: (fr: number) => void) {
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
      await api.post('/api/v1/gallery/chunk/complete', { id: init.id });
      onProgress?.(1);
    } catch (e) {
      await api.post('/api/v1/gallery/chunk/abort', { id: init.id }).catch(() => { /* best-effort */ });
      throw e;
    }
  }

  const favorite = (id: number, fav: boolean) => api.patch(`/api/v1/gallery/${id}/favorite`, { favorite: fav });
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
    photos, albums, load, trash, loadAlbums, upload, favorite, destroy, bulkDestroy,
    restore, forceDelete, emptyTrash, createAlbum, renameAlbum, setAlbumCover, deleteAlbum,
    addToAlbum, removeFromAlbum, thumbUrl, rawUrl,
  };
});
