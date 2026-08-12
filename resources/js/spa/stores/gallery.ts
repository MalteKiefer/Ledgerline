import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, uploadWithProgress } from '@spa/api/client';

export interface Photo {
  id: number; name: string; mime: string | null;
  width: number | null; height: number | null; size: number;
  favorite: boolean; thumb: boolean; preview: boolean; motion: boolean;
  media_type: 'image' | 'video'; status: 'ready' | 'processing' | 'failed'; duration: number | null;
  rotation: number; flip_h: boolean; archived: boolean;
  taken_at: string | null; camera: string | null; place: string | null;
  lat: number | null; lng: number | null; version: number; created_at: string | null;
}

export interface ExifDetail {
  id: number; name: string; mime: string | null; size: number;
  width: number | null; height: number | null;
  taken_at: string | null; camera: string | null; place: string | null;
  lat: number | null; lng: number | null;
  exif: Record<string, Record<string, string>>;
}

export interface PublicShareRow {
  id: number; album_id: number; album?: string | null; token: string;
  has_password: boolean; allow_download: boolean; expires_at: string | null;
}
export interface InternalShareRow {
  id: number; album_id: number | null; album?: string | null; recipient?: string | null; scope: 'album' | 'library';
}
export interface SharedWithMeRow {
  id: number; scope: 'album' | 'library'; name: string | null; owner: string | null; count: number; cover: number | null;
}
export interface SharedPhoto {
  id: number; name: string; media_type: 'image' | 'video'; taken_at: string | null; width: number | null; height: number | null;
}

export interface PhotoEdit {
  taken_at?: string | null; place?: string | null;
  lat?: number | null; lng?: number | null;
  rotation?: number; flip_h?: boolean; version?: number;
}

export interface Album {
  id: number; name: string; count: number; cover_photo_id: number | null; version: number;
}

export interface Person {
  id: number; name: string | null; contact_id: string | null; count: number; cover_face_id: number | null;
}

export interface ContactSuggestion { id: string; name: string }

export interface MlStatus {
  ml: { enabled: boolean; face_enabled: boolean; vector: boolean; clip_model: string | null; face_model: string | null };
  queue: { pending: number };
  counts: { photos: number; videos: number; embedded: number; with_date: number; located: number; faces: number; people: number };
}

export interface Face {
  id: number; person_id: number | null; person_name: string | null;
  box: number[]; score: number; crop: boolean;
}

const WHOLE_LIMIT = 8 * 1024 * 1024;

export const useGalleryStore = defineStore('gallery', () => {
  const photos = ref<Photo[]>([]);
  const albums = ref<Album[]>([]);

  const load = (albumId?: number) => {
    const q = albumId ? `?album_id=${albumId}` : '';
    return api.get<{ photos: Photo[] }>(`/api/v1/gallery/data${q}`).then((r) => { photos.value = r.photos ?? []; });
  };

  // Lightweight refresh used by the thumbnail/processing poll: fetch the same
  // list but PATCH the volatile fields (thumb/status/preview/…) in place when
  // the photo set is unchanged, so the array identity stays stable — the grid
  // only re-renders the tiles whose fields actually changed, never rebuilds all
  // ~1200 rows. Only structural changes (add/remove) replace the array.
  const mergeData = async (albumId?: number) => {
    const q = albumId ? `?album_id=${albumId}` : '';
    const r = await api.get<{ photos: Photo[] }>(`/api/v1/gallery/data${q}`);
    const fresh = r.photos ?? [];
    const byId = new Map(photos.value.map((p) => [p.id, p]));
    let sameSet = fresh.length === photos.value.length;
    for (const nf of fresh) {
      const ex = byId.get(nf.id);
      if (!ex) { sameSet = false; break; }
      ex.thumb = nf.thumb; ex.preview = nf.preview; ex.status = nf.status;
      ex.motion = nf.motion; ex.duration = nf.duration; ex.media_type = nf.media_type;
      ex.width = nf.width; ex.height = nf.height;
    }
    if (!sameSet) photos.value = fresh; // add/remove → structural rebuild
  };
  const loadArchived = () => api.get<{ photos: Photo[] }>('/api/v1/gallery/data?archived=1').then((r) => { photos.value = r.photos ?? []; });
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
  const archive = (id: number, archived: boolean) => api.patch(`/api/v1/gallery/${id}/archive`, { archived });
  const bulkArchive = (ids: number[], archived: boolean) => api.post('/api/v1/gallery/bulk-archive', { ids, archived });
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
  const previewUrl = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/preview`);
  const rawUrl = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/raw`);

  // People / faces (opt-in face recognition)
  const people = () => api.get<{ people: Person[] }>('/api/v1/gallery/people').then((r) => r.people ?? []);
  // Load a person's photos (sortable by capture date) into the main grid.
  const browsePerson = (id: number, sort: 'asc' | 'desc' = 'desc') =>
    api.get<{ person: Person; photos: Photo[] }>(`/api/v1/gallery/people/${id}?sort=${sort}`)
      .then((r) => { photos.value = r.photos ?? []; return r.person; });
  const updatePerson = (id: number, patch: { name?: string | null; contact_id?: string | null; cover_face_id?: number | null }) =>
    api.put<{ ok: boolean; person: Person }>(`/api/v1/gallery/people/${id}`, patch).then((r) => r.person);
  const deletePerson = (id: number) => api.delete(`/api/v1/gallery/people/${id}`);
  const mergePeople = (fromId: number, intoId: number) => api.post('/api/v1/gallery/people/merge', { from_id: fromId, into_id: intoId });
  const photoFaces = (photoId: number) => api.get<{ faces: Face[] }>(`/api/v1/gallery/${photoId}/faces`).then((r) => r.faces ?? []);
  const assignFace = (faceId: number, target: { person_id?: number; contact_id?: string; name?: string }) =>
    api.post<Face>(`/api/v1/gallery/faces/${faceId}/assign`, target);
  const setFaceCover = (personId: number, faceId: number) => updatePerson(personId, { cover_face_id: faceId });
  const hideFace = (faceId: number) => api.post(`/api/v1/gallery/faces/${faceId}/hide`);
  const faceCropUrl = (faceId: number) => api.streamUrl(`/api/v1/gallery/faces/${faceId}/crop`);
  const reprocess = (scope: 'faces' | 'embeddings' | 'exif' | 'all') => api.post<{ queued: number }>('/api/v1/gallery/reprocess', { scope });
  const mlStatus = () => api.get<MlStatus>('/api/v1/gallery/ml-status');
  const loadExif = (id: number) => api.get<ExifDetail>(`/api/v1/gallery/${id}/exif`);
  // Address-book contact autocomplete for naming people.
  const nameSuggest = (q: string) =>
    api.get<{ contacts: ContactSuggestion[] }>(`/api/v1/contacts/suggest?q=${encodeURIComponent(q)}`)
      .then((r) => r.contacts ?? []).catch(() => [] as ContactSuggestion[]);
  const contactPhotos = (contactId: string) =>
    api.get<{ photos: Photo[] }>(`/api/v1/gallery/contacts/${contactId}/photos`).then((r) => r.photos ?? []).catch(() => [] as Photo[]);

  // ---- Sharing (owner side) ----
  const loadShares = () => api.get<{ public: PublicShareRow[]; internal: InternalShareRow[] }>('/api/v1/gallery/shares');
  const createPublicShare = (body: { album_id: number; allow_download?: boolean; password?: string | null; expires_at?: string | null }) =>
    api.post<PublicShareRow>('/api/v1/gallery/shares/public', body);
  const updatePublicShare = (id: number, body: Record<string, unknown>) => api.put<PublicShareRow>(`/api/v1/gallery/shares/public/${id}`, body);
  const deletePublicShare = (id: number) => api.delete(`/api/v1/gallery/shares/public/${id}`);
  const shareInternal = (body: { email: string; album_id?: number | null }) => api.post<{ ok: boolean; id: number }>('/api/v1/gallery/shares/internal', body);
  const deleteInternalShare = (id: number) => api.delete(`/api/v1/gallery/shares/internal/${id}`);
  const publicShareUrl = (token: string) => `${window.location.origin}/g/${token}`;

  // ---- Shared with me (recipient side) ----
  const sharedWithMe = () => api.get<{ shares: SharedWithMeRow[] }>('/api/v1/gallery/shared-with-me').then((r) => r.shares ?? []);
  const browseShared = (id: number) => api.get<{ name: string | null; photos: SharedPhoto[] }>(`/api/v1/gallery/shared-with-me/${id}`);
  const sharedThumbUrl = (share: number, photo: number) => api.streamUrl(`/api/v1/gallery/shared-with-me/${share}/photo/${photo}/thumb`);
  const sharedPreviewUrl = (share: number, photo: number) => api.streamUrl(`/api/v1/gallery/shared-with-me/${share}/photo/${photo}/preview`);
  const sharedRawUrl = (share: number, photo: number) => api.streamUrl(`/api/v1/gallery/shared-with-me/${share}/photo/${photo}/raw`);

  return {
    photos, albums, load, loadArchived, mergeData, trash, search, duplicates, loadAlbums, upload, attachMotion, motionUrl, playUrl, favorite, update, downloadUrl, destroy, bulkDestroy, archive, bulkArchive,
    restore, forceDelete, emptyTrash, createAlbum, renameAlbum, setAlbumCover, deleteAlbum,
    addToAlbum, removeFromAlbum, thumbUrl, previewUrl, rawUrl,
    people, browsePerson, updatePerson, deletePerson, mergePeople, photoFaces, assignFace, setFaceCover, hideFace, faceCropUrl, reprocess, mlStatus, loadExif, nameSuggest, contactPhotos,
    loadShares, createPublicShare, updatePublicShare, deletePublicShare, shareInternal, deleteInternalShare, publicShareUrl,
    sharedWithMe, browseShared, sharedThumbUrl, sharedPreviewUrl, sharedRawUrl,
  };
});
