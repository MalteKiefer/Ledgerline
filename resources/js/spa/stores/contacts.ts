import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface ContactRow {
  id: string; book: string; fn: string; first_name: string | null; last_name: string | null;
  org: string | null; emails: { value: string; type?: string }[]; phones: { value: string; type?: string }[];
  has_photo: boolean; favorite: boolean; avatar: string | null;
}
export interface AddressBook { id: string; name: string; uri: string; owned: boolean }
export interface ContactGroup { id: number; name: string }
export type ContactDetail = Record<string, unknown> & { id: string; book: string; group_ids: number[] };

export interface DuplicateContact {
  id: string; book: string; fn: string | null; first_name: string | null; last_name: string | null;
  org: string | null; emails: string[]; phones: string[]; has_photo: boolean; avatar: string | null;
}
export interface DuplicateGroup { signature: string; reasons: string[]; contacts: DuplicateContact[] }
export interface ImportResult { created: number; updated: number; skipped: number }

export const useContactsStore = defineStore('contacts', () => {
  const contacts = ref<ContactRow[]>([]);
  const books = ref<AddressBook[]>([]);
  const groups = ref<ContactGroup[]>([]);

  async function load(params: { book_id?: string; group_id?: number; favorites?: boolean; q?: string } = {}) {
    const qs = new URLSearchParams();
    if (params.book_id) qs.set('book_id', params.book_id);
    if (params.group_id) qs.set('group_id', String(params.group_id));
    if (params.favorites) qs.set('favorites', '1');
    if (params.q) qs.set('q', params.q);
    const r = await api.get<{ contacts: ContactRow[]; books: AddressBook[]; groups: ContactGroup[] }>(`/api/v1/contacts/data?${qs}`);
    contacts.value = r.contacts; books.value = r.books; groups.value = r.groups;
  }
  const show = (id: string) => api.get<ContactDetail>(`/api/v1/contacts/${id}`);
  const create = (body: Record<string, unknown>) => api.post<{ id: string }>('/api/v1/contacts', body);
  const update = (id: string, body: Record<string, unknown>) => api.put(`/api/v1/contacts/${id}`, body);
  const destroy = (id: string) => api.delete(`/api/v1/contacts/${id}`);
  const favorite = (id: string, fav: boolean) => api.patch(`/api/v1/contacts/${id}/favorite`, { favorite: fav });
  const createBook = (name: string) => api.post('/api/v1/address-books', { name });
  const avatarUrl = (c: ContactRow) => c.avatar || (c.has_photo ? `/api/v1/contacts/${c.id}/avatar` : null);

  // Groups
  const createGroup = (name: string) => api.post<{ id: number }>('/api/v1/contact-groups', { name });
  const deleteGroup = (id: number) => api.delete(`/api/v1/contact-groups/${id}`);

  // Duplicates
  async function loadDuplicates() {
    const r = await api.get<{ groups: DuplicateGroup[] }>('/api/v1/contacts/duplicates/data');
    return r.groups;
  }
  const mergeDuplicates = (payload: { primary_id: string; ids: string[] }) => api.post('/api/v1/contacts/duplicates/merge', payload);
  const dismissDuplicate = (payload: { ids: string[] }) => api.post('/api/v1/contacts/duplicates/dismiss', payload);

  // Import / export
  function importVcf(file: File, bookId: string) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('book_id', bookId);
    return api.upload<ImportResult>('/api/v1/contacts/import', fd);
  }
  const exportUrl = (bookId?: string) => `/api/v1/contacts/export${bookId ? `?book=${encodeURIComponent(bookId)}` : ''}`;

  // Avatar (server field is `photo`) + bulk delete
  function uploadAvatar(id: string, file: File) {
    const fd = new FormData();
    fd.append('photo', file);
    return api.upload<{ ok: boolean; avatar: string }>(`/api/v1/contacts/${id}/avatar`, fd);
  }
  const bulkDestroy = (ids: string[]) => api.delete<{ deleted: number }>('/api/v1/contacts/bulk-destroy', { ids });

  return {
    contacts, books, groups, load, show, create, update, destroy, favorite, createBook, avatarUrl,
    createGroup, deleteGroup, loadDuplicates, mergeDuplicates, dismissDuplicate,
    importVcf, exportUrl, uploadAvatar, bulkDestroy,
  };
});
