import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface NoteFolder {
  id: number; parent_id: number | null; name: string; color: string | null; position: number; version: number;
}
export interface NoteRow {
  id: number; note_folder_id: number | null; title: string; tags: string[];
  pinned: boolean; favorite: boolean; updated_at: string | null;
}
export interface Backlink { id: number; title: string; snippet: string }
export interface NoteAttachment { id: number; name: string; mime: string | null; size: number }
export interface NoteDetail extends NoteRow { body: string; version?: number; backlinks?: Backlink[]; attachments?: NoteAttachment[] }
export interface TagCount { name: string; count: number }

export const useNotesStore = defineStore('notes', () => {
  const notes = ref<NoteRow[]>([]);
  const folders = ref<NoteFolder[]>([]);
  const tags = ref<TagCount[]>([]);

  async function load() {
    const r = await api.get<{ folders: NoteFolder[]; notes: NoteRow[]; tags: TagCount[] }>('/api/v1/notes/data');
    folders.value = r.folders ?? [];
    notes.value = r.notes ?? [];
    tags.value = r.tags ?? [];
  }

  const show = (id: number) => api.get<{ note: NoteDetail }>(`/api/v1/notes/${id}`).then((r) => r.note);
  const create = (body: Record<string, unknown>) => api.post<{ note: NoteDetail }>('/api/v1/notes', body).then((r) => r.note);
  const update = (id: number, body: Record<string, unknown>) => api.put<{ note: NoteDetail }>(`/api/v1/notes/${id}`, body).then((r) => r.note);
  const destroy = (id: number) => api.delete(`/api/v1/notes/${id}`);
  const favorite = (id: number, fav: boolean) => api.patch(`/api/v1/notes/${id}/favorite`, { favorite: fav });
  const pin = (id: number, pinned: boolean) => api.patch(`/api/v1/notes/${id}/pin`, { pinned });
  const search = (q: string) => api.get<{ notes: NoteRow[] }>(`/api/v1/notes/search?q=${encodeURIComponent(q)}`).then((r) => r.notes);
  const backlinks = (id: number) => api.get<{ backlinks: Backlink[] }>(`/api/v1/notes/${id}/backlinks`).then((r) => r.backlinks);

  // Attachments + Markdown export
  function attach(noteId: number, file: File) {
    const fd = new FormData();
    fd.append('file', file);
    return api.upload<{ attachment: NoteAttachment }>(`/api/v1/notes/${noteId}/attachments`, fd).then((r) => r.attachment);
  }
  // Embed an existing Files file or Gallery photo (image/video) into a note by
  // copying it into a note attachment server-side.
  const attachFrom = (noteId: number, source: 'file' | 'gallery', id: number) =>
    api.post<{ attachment: NoteAttachment }>(`/api/v1/notes/${noteId}/attachments/from`, { source, id }).then((r) => r.attachment);
  const deleteAttachment = (noteId: number, attId: number) => api.delete(`/api/v1/notes/${noteId}/attachments/${attId}`);
  const attachmentUrl = (noteId: number, attId: number) => api.streamUrl(`/api/v1/notes/${noteId}/attachments/${attId}/raw`);
  const exportUrl = (id: number) => api.streamUrl(`/api/v1/notes/${id}/export?download=1`);

  const createFolder = (body: Record<string, unknown>) => api.post<{ folder: NoteFolder }>('/api/v1/notes/folders', body).then((r) => r.folder);
  const updateFolder = (id: number, body: Record<string, unknown>) => api.put(`/api/v1/notes/folders/${id}`, body);
  const deleteFolder = (id: number) => api.delete(`/api/v1/notes/folders/${id}`);

  // Recycle bin
  const trash = () => api.get<{ notes: NoteRow[]; folders: { id: number; name: string }[] }>('/api/v1/notes/trash');
  const restore = (id: number) => api.post(`/api/v1/notes/${id}/restore`);
  const forceDelete = (id: number) => api.delete(`/api/v1/notes/${id}/force`);
  const restoreFolder = (id: number) => api.post(`/api/v1/notes/folders/${id}/restore`);

  return {
    notes, folders, tags,
    load, show, create, update, destroy, favorite, pin, search, backlinks,
    attach, attachFrom, deleteAttachment, attachmentUrl, exportUrl,
    createFolder, updateFolder, deleteFolder,
    trash, restore, forceDelete, restoreFolder,
  };
});
