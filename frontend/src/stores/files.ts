import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, getToken, uploadWithProgress } from '@spa/api/client';

export interface FileFolder { id: number; name: string; parent_id: number | null; version: number }
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
  name?: string;
}
export interface UploadLink {
  id: number; token: string; label: string | null; file_folder_id: number | null;
  folder_name?: string | null; needs_password?: boolean; expires_at: string | null;
}
// ---- Cross-user folder sharing (owner side + member side) ----
export interface FolderShareMember {
  id: number; user_id: number; name: string | null; email: string | null; role: 'viewer' | 'editor';
}
export interface FolderShare {
  id: number; kind: 'file' | 'folder';
  file_folder_id: number | null; folder_name: string | null;
  file_id: number | null; file_name: string | null;
  members: FolderShareMember[];
}
export interface SharedWithMeEntry {
  id: number; kind: 'file' | 'folder';
  folder_name?: string | null; file_name?: string | null;
  role: 'owner' | 'viewer' | 'editor';
  owner: { id: number | null; name: string | null; email: string | null };
}
export interface SharedFolderNode { id: number; name: string; parent_id: number | null }
export interface FileActivity {
  id: number; action: string; file_id: number | null; file_name: string | null;
  file_folder_id: number | null; actor: string | null; meta: Record<string, unknown> | null; created_at: string;
}
export interface FileInfo {
  sha256: string | null;
  created_at: string | null;
  updated_at: string | null;
  version: number;
  versions: number;
  path: string;
  metadata: { kind: string; fields: Record<string, string> } | null;
  snippet: string | null;
  share: { expires_at: string | null; allow_download: boolean; protected: boolean } | null;
  duplicates: { id: number; name: string; path: string }[];
  activity: FileActivity[];
}
export interface SharedFileNode {
  id: number; name: string; mime: string | null; size: number; file_folder_id: number | null; updated_at: string | null;
}
export interface SharedFileTarget { id: number; name: string; mime: string | null; size: number; updated_at: string | null }
export interface SharedBrowse {
  share_id: number; role: 'owner' | 'viewer' | 'editor'; kind: 'file' | 'folder'; root_id: number | null;
  file?: SharedFileTarget; folders?: SharedFolderNode[]; files?: SharedFileNode[];
}
export interface DuplicateFile { id: number; name: string; size: number; path: string }
export interface FileStats {
  used: number; by_type: Record<string, number>; duplicates: DuplicateFile[][];
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
    await api.post('/api/v1/files/folders', { name, parent_id: parent });
    await load();
  }
  // Create a folder and return its id (no reload) — used by directory upload to
  // rebuild the picked tree before placing files.
  async function createFolderId(name: string, parent: number | null): Promise<number> {
    const r = await api.post<{ folder: { id: number } }>('/api/v1/files/folders', { name, parent_id: parent });
    return r.folder.id;
  }
  const WHOLE_UPLOAD_LIMIT = 8 * 1024 * 1024; // above this, fall back to chunks

  async function upload(file: File, folder: number | null, onProgress?: (f: number) => void) {
    if (file.size > WHOLE_UPLOAD_LIMIT) { await uploadChunked(file, folder, onProgress); return; }
    const form = new FormData();
    form.append('file', file);
    if (folder != null) form.append('file_folder_id', String(folder));
    await uploadWithProgress('/api/v1/files/entries', form, onProgress);
  }
  // Large files stream in 8-MiB parts (init → part* → complete) so they aren't
  // bounded by post_max_size / a single request's memory. Progress is the
  // fraction of parts completed (+ the running part's own bytes).
  async function uploadChunked(file: File, folder: number | null, onProgress?: (f: number) => void) {
    const init = await api.post<{ id: string; partSize: number }>('/api/v1/files/upload/chunk/init', {
      name: file.name, size: file.size, file_folder_id: folder,
    });
    const partSize = init.partSize > 0 ? init.partSize : WHOLE_UPLOAD_LIMIT;
    const parts = Math.max(1, Math.ceil(file.size / partSize));
    try {
      for (let i = 0; i < parts; i++) {
        const form = new FormData();
        form.append('id', init.id);
        form.append('index', String(i));
        form.append('file', file.slice(i * partSize, (i + 1) * partSize), file.name);
        await uploadWithProgress('/api/v1/files/upload/chunk/part', form, (pf) => onProgress?.((i + pf) / parts));
      }
      await api.post('/api/v1/files/upload/chunk/complete', { id: init.id });
      onProgress?.(1);
    } catch (e) {
      await api.post('/api/v1/files/upload/chunk/abort', { id: init.id }).catch(() => { /* best-effort */ });
      throw e;
    }
  }
  // Replace an existing file's bytes (new revision) — used by upload-conflict "overwrite".
  function replaceContent(f: FileEntry, file: File, onProgress?: (fr: number) => void) {
    const form = new FormData();
    form.append('file', file);
    return uploadWithProgress(`/api/v1/files/entries/${f.id}/content`, form, onProgress);
  }
  const rename = (f: FileEntry, name: string) => api.put<{ file: FileEntry }>(`/api/v1/files/entries/${f.id}`, { name, version: f.version });
  const move = (f: FileEntry, folder: number | null) => api.put(`/api/v1/files/entries/${f.id}`, { file_folder_id: folder, version: f.version });
  const copy = (f: FileEntry, folder: number | null) => api.post<{ file: FileEntry }>(`/api/v1/files/entries/${f.id}/copy`, { file_folder_id: folder });
  const toggleFav = (f: FileEntry) => api.post(`/api/v1/files/entries/${f.id}/toggle`, { field: 'favorite' });
  const trashFile = (f: FileEntry) => api.delete(`/api/v1/files/entries/${f.id}`);
  const restoreFile = (id: number) => api.post(`/api/v1/files/entries/${id}/restore`);
  const forceFile = (id: number) => api.delete(`/api/v1/files/entries/${id}/force`);
  const renameFolder = (fo: FileFolder, name: string) => api.put(`/api/v1/files/folders/${fo.id}`, { name, version: fo.version });
  const moveFolder = (fo: FileFolder, parent: number | null) => api.post(`/api/v1/files/folders/${fo.id}/move`, { parent_id: parent });
  const trashFolder = (fo: FileFolder) => api.delete(`/api/v1/files/folders/${fo.id}`);
  const restoreFolder = (id: number) => api.post(`/api/v1/files/folders/${id}/restore`);
  const forceFolder = (id: number) => api.delete(`/api/v1/files/folders/${id}/force`);
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
  const versionRawUrl = (f: FileEntry, version: number) => api.streamUrl(`/api/v1/files/entries/${f.id}/versions/${version}/raw?download=1`);

  // ---- Public share links (anonymous, token-gated) ----
  interface SharePayload { password?: string; remove_password?: boolean; allow_download?: boolean; expires_at?: string | null }
  // Public inbound upload links (owner side + anonymous side).
  const loadUploadLinks = () => api.get<{ links: UploadLink[] }>('/api/v1/files/upload-links').then((r) => r.links);
  const createUploadLink = (body: { file_folder_id: number; label?: string; expires_at: string; password?: string }) =>
    api.post<{ link: UploadLink }>('/api/v1/files/upload-links', body).then((r) => r.link);
  const deleteUploadLink = (id: number) => api.delete(`/api/v1/files/upload-links/${id}`);
  const uploadLinkUrl = (token: string) => `${window.location.origin}/u/${token}`;
  const uploadLinkMeta = (token: string) => api.get<{ label: string | null; owner: string; needs_password: boolean }>(`/api/v1/upload-link/${encodeURIComponent(token)}`);
  function uploadLinkSend(token: string, file: File, onProgress?: (fr: number) => void, password?: string) {
    const fd = new FormData();
    fd.append('file', file);
    if (password) fd.append('password', password);
    return uploadWithProgress(`/api/v1/upload-link/${encodeURIComponent(token)}`, fd, onProgress);
  }

  const loadShares = () => api.get<{ shares: FileShare[] }>('/api/v1/files/rel-shares').then((r) => r.shares);
  const createShare = (fileId: number, payload: SharePayload = {}) =>
    api.post<{ share: FileShare }>('/api/v1/files/rel-shares', { kind: 'file', file_id: fileId, ...payload });
  const createFolderShareLink = (folderId: number, payload: SharePayload = {}) =>
    api.post<{ share: FileShare }>('/api/v1/files/rel-shares', { kind: 'folder', file_folder_id: folderId, ...payload });
  const updateShare = (id: number, version: number, payload: SharePayload) =>
    api.put<{ share: FileShare }>(`/api/v1/files/rel-shares/${id}`, { ...payload, version });
  const deleteShare = (id: number) => api.delete(`/api/v1/files/rel-shares/${id}`);
  // The SPA renders the anonymous consumer at /share/:token (PublicShare.vue).
  const shareUrl = (token: string) => `${window.location.origin}/share/${token}`;

  // ---- Cross-user folder sharing: owner side ----
  const loadFolderShares = () => api.get<{ shares: FolderShare[] }>('/api/v1/files/folder-shares');
  const shareToUser = (payload: { kind?: 'file' | 'folder'; file_folder_id?: number; file_id?: number; email: string; role: 'viewer' | 'editor' }) =>
    api.post<{ share: FolderShare }>('/api/v1/files/folder-shares', payload);
  const updateShareMember = (shareId: number, payload: { user_id: number; role: 'viewer' | 'editor' }) =>
    api.put<{ share: FolderShare }>(`/api/v1/files/folder-shares/${shareId}/members`, payload);
  // DELETE with a JSON body — the member is identified by user_id, not a path id.
  const removeShareMember = (shareId: number, userId: number) =>
    api.delete(`/api/v1/files/folder-shares/${shareId}/members`, { user_id: userId });
  const deleteFolderShare = (shareId: number) => api.delete(`/api/v1/files/folder-shares/${shareId}`);

  // ---- Cross-user folder sharing: member side ("shared with me") ----
  const loadSharedWithMe = () => api.get<{ shares: SharedWithMeEntry[] }>('/api/v1/shared-with-me');
  // The API returns the whole shared subtree in one call; the view navigates it
  // client-side, so `folderId` is accepted for the contract but not sent.
  const browseShared = (shareId: number, _folderId?: number | null) =>
    api.get<SharedBrowse>(`/api/v1/shared-with-me/${shareId}`);
  const sharedRawUrl = (shareId: number, fileId: number, download = false) =>
    api.streamUrl(`/api/v1/shared-with-me/${shareId}/files/${fileId}/raw${download ? '?download=1' : ''}`);
  async function uploadToShared(shareId: number, file: File, folderId?: number | null) {
    const form = new FormData();
    form.append('file', file);
    if (folderId != null) form.append('file_folder_id', String(folderId));
    return api.upload<{ file: FileEntry }>(`/api/v1/shared-with-me/${shareId}/upload`, form);
  }
  const renameShared = (shareId: number, fileId: number, name: string) =>
    api.put<{ file: FileEntry }>(`/api/v1/shared-with-me/${shareId}/files/${fileId}`, { name });
  const deleteShared = (shareId: number, fileId: number) =>
    api.delete(`/api/v1/shared-with-me/${shareId}/files/${fileId}`);

  // ---- Storage stats ----
  const stats = () => api.get<FileStats>('/api/v1/files/stats');
  const activity = () => api.get<{ activity: FileActivity[] }>('/api/v1/files/activity').then((r) => r.activity);
  const fileActivity = (fileId: number) => api.get<{ activity: FileActivity[] }>(`/api/v1/files/entries/${fileId}/activity`).then((r) => r.activity);
  const fileInfo = (fileId: number) => api.get<FileInfo>(`/api/v1/files/entries/${fileId}/info`);
  const getEntry = (fileId: number) => api.get<{ file: FileEntry }>(`/api/v1/files/entries/${fileId}/show`).then((r) => r.file);

  /**
   * ZIP download. Streams a blob via raw fetch (the shared `api` client only
   * decodes JSON), then triggers a browser download through a temporary anchor.
   */
  async function zip(sel: { ids?: number[]; folder_id?: number | null }) {
    const token = getToken();
    const res = await fetch(api.url('/api/v1/files/zip'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
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

  const rawUrl = (f: FileEntry) => api.streamUrl(`/api/v1/files/entries/${f.id}/raw`);
  const thumbUrl = (f: FileEntry) => api.streamUrl(`/api/v1/files/entries/${f.id}/thumb`);

  // Create an archive from a selection (or folder subtree) — saved as a file in
  // the target folder and returned (downloadable via its normal raw URL).
  interface ArchivePayload { ids?: number[]; folder_id?: number | null; target_folder_id?: number | null; format: string; level?: number; password?: string; name?: string }
  const createArchive = (payload: ArchivePayload) => api.post<{ file: FileEntry }>('/api/v1/files/archive', payload).then((r) => r.file);
  // Extract an archive into a new folder (worker does the untrusted decode).
  const extractArchive = (fileId: number, opts?: { password?: string; target_folder_id?: number | null }) =>
    api.post<{ folder: { id: number; name: string; parent_id: number | null } }>(`/api/v1/files/entries/${fileId}/extract`, opts ?? {}).then((r) => r.folder);
  // Which names are extractable archives (drives the "Extract here" action).
  const ARCHIVE_RE = /\.(zip|7z|rar|tar\.gz|tgz|tar\.xz|txz|tar\.bz2|tbz2?|tar\.zst|tzst|tar|gz|bz2|xz|zst)$/i;
  const isArchive = (name: string) => ARCHIVE_RE.test(name);

  return {
    folders, files, labels, usage,
    load, loadTrash, createFolder, createFolderId, upload, replaceContent, rename, move, copy, toggleFav,
    trashFile, restoreFile, forceFile, renameFolder, moveFolder, trashFolder, restoreFolder, forceFolder, emptyTrash, search,
    updateEntry, createLabel, updateLabel, deleteLabel, setFileLabels,
    versions, restoreVersion, versionRawUrl,
    loadUploadLinks, createUploadLink, deleteUploadLink, uploadLinkUrl, uploadLinkMeta, uploadLinkSend,
    loadShares, createShare, createFolderShareLink, updateShare, deleteShare, shareUrl,
    loadFolderShares, shareToUser, updateShareMember, removeShareMember, deleteFolderShare,
    loadSharedWithMe, browseShared, sharedRawUrl, uploadToShared, renameShared, deleteShared,
    stats, zip, activity, fileActivity, fileInfo, getEntry,
    createArchive, extractArchive, isArchive,
    rawUrl, thumbUrl,
  };
});
