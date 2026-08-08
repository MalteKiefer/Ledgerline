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

  return { contacts, books, groups, load, show, create, update, destroy, favorite, createBook, avatarUrl };
});
