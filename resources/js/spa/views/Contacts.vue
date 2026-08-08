<template>
  <div class="d-flex flex-column flex-md-row ga-4" style="min-height:calc(100vh - 120px)">
    <!-- Books / groups rail -->
    <v-card rounded="xl" border flat width="220" class="flex-shrink-0" style="align-self:flex-start">
      <div class="pa-3">
        <v-btn color="primary" block :prepend-icon="mdiPlus" @click="openNew">{{ t('contacts.ui.new_contact') }}</v-btn>
      </div>
      <v-list density="compact" nav>
        <v-list-subheader>{{ t('contacts.ui.books') }}</v-list-subheader>
        <v-list-item :active="!bookId && !favOnly && groupId === null" :prepend-icon="mdiAccountMultiple" :title="t('contacts.ui.all_books')" @click="pick(null, false)" />
        <v-list-item :active="favOnly" :prepend-icon="mdiStar" :title="t('contacts.ui.favorites')" @click="pick(null, true)" />
        <v-list-item v-for="b in c.books" :key="b.id" :active="bookId === b.id" :prepend-icon="mdiBookOpenPageVariant" :title="b.name" @click="pick(b.id, false)" />
        <v-list-item :prepend-icon="mdiFolderPlus" :title="t('contacts.ui.new_book')" @click="newBook" />

        <v-list-subheader>{{ t('contacts.ui.groups') }}</v-list-subheader>
        <v-list-item v-for="g in c.groups" :key="g.id" :active="groupId === g.id" :prepend-icon="mdiAccountGroup" :title="g.name" @click="pickGroup(g.id)">
          <template #append>
            <v-btn variant="text" size="x-small" :icon="mdiDelete" :title="t('common.delete')" @click.stop="removeGroup(g)" />
          </template>
        </v-list-item>
        <v-list-item :prepend-icon="mdiAccountMultiplePlus" :title="t('contacts.ui.new_group')" @click="newGroup" />
      </v-list>
    </v-card>

    <!-- List -->
    <v-card rounded="xl" border flat width="340" class="flex-shrink-0 d-flex flex-column" style="align-self:stretch">
      <!-- Toolbar -->
      <div class="pa-2 border-b d-flex ga-1 align-center">
        <v-btn variant="text" size="small" :prepend-icon="mdiUpload" @click="openImport">{{ t('contacts.ui.import') }}</v-btn>
        <v-btn variant="text" size="small" :prepend-icon="mdiContentDuplicate" @click="openDuplicates">{{ t('contacts.ui.duplicates') }}</v-btn>
        <v-spacer />
        <v-btn variant="text" size="small" :icon="mdiDownload" :href="c.exportUrl(bookId ?? undefined)" :title="t('contacts.ui.export')" />
      </div>
      <div class="pa-3 border-b">
        <v-text-field v-model="query" :placeholder="t('common.search')" :prepend-inner-icon="mdiMagnify" variant="solo-filled" flat density="compact" hide-details single-line @update:model-value="debouncedLoad" />
      </div>
      <!-- Selection bar -->
      <div v-if="selected_ids.length" class="px-3 py-2 border-b d-flex align-center ga-1">
        <span class="text-caption text-medium-emphasis">{{ t('contacts.ui.selected_count', { count: String(selected_ids.length) }) }}</span>
        <v-spacer />
        <v-btn variant="text" size="x-small" @click="selectAll">{{ t('contacts.ui.select_all') }}</v-btn>
        <v-btn variant="text" size="x-small" @click="clearSelection">{{ t('contacts.ui.clear_selection') }}</v-btn>
        <v-btn variant="text" size="x-small" color="error" :loading="bulkBusy" @click="deleteSelected">{{ t('contacts.ui.delete_selected') }}</v-btn>
      </div>
      <div class="flex-grow-1 overflow-y-auto">
        <v-list density="comfortable">
          <v-list-item v-for="row in c.contacts" :key="row.id" :active="selected?.id === row.id" @click="openDetail(row)">
            <template #prepend>
              <v-checkbox-btn :model-value="selected_ids.includes(row.id)" density="compact" class="mr-1" @click.stop="toggleSelect(row.id)" />
              <v-avatar size="40" :color="color(row)">
                <v-img v-if="c.avatarUrl(row)" :src="bust(c.avatarUrl(row))!" />
                <span v-else class="text-body-2">{{ initials(row) }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ row.fn || (row.first_name + ' ' + row.last_name) }}</v-list-item-title>
            <v-list-item-subtitle>{{ row.org || row.emails[0]?.value || '' }}</v-list-item-subtitle>
            <template #append><v-icon v-if="row.favorite" :icon="mdiStar" color="amber" size="small" /></template>
          </v-list-item>
          <v-list-item v-if="!c.contacts.length" :title="t('contacts.ui.empty')" class="text-medium-emphasis" />
        </v-list>
      </div>
    </v-card>

    <!-- Detail -->
    <v-card rounded="xl" border flat class="flex-grow-1" style="min-width:0">
      <template v-if="detail">
        <v-toolbar flat color="surface">
          <v-avatar size="48" :color="'primary'" class="ml-2">
            <v-img v-if="selected && c.avatarUrl(selected)" :src="bust(c.avatarUrl(selected))!" />
            <span v-else>{{ selected ? initials(selected) : '' }}</span>
          </v-avatar>
          <v-toolbar-title class="ml-3">{{ str(detail.fn) }}</v-toolbar-title>
          <v-spacer />
          <v-btn variant="text" :icon="selected?.favorite ? mdiStar : mdiStarOutline" :title="selected?.favorite ? t('contacts.ui.favorite_remove') : t('contacts.ui.favorite_add')" @click="toggleFav" />
          <v-btn variant="text" :icon="mdiPencil" :title="t('common.edit')" @click="openEdit" />
          <v-btn variant="text" color="error" :icon="mdiDelete" :title="t('common.delete')" @click="onDelete" />
        </v-toolbar>
        <v-divider />
        <v-card-text>
          <div v-if="str(detail.org)" class="mb-4 text-medium-emphasis">{{ str(detail.org) }}<span v-if="str(detail.title)"> · {{ str(detail.title) }}</span></div>
          <v-list density="comfortable">
            <v-list-item v-for="(e, i) in arr(detail.emails)" :key="'e'+i" :prepend-icon="mdiEmail" :title="e.value" :subtitle="e.type" :href="'mailto:' + e.value" />
            <v-list-item v-for="(p, i) in arr(detail.phones)" :key="'p'+i" :prepend-icon="mdiPhone" :title="p.value" :subtitle="p.type" :href="'tel:' + p.value" />
            <v-list-item v-for="(u, i) in arr(detail.urls)" :key="'u'+i" :prepend-icon="mdiWeb" :title="u.value" :subtitle="t('contacts.ui.website')" :href="u.value" target="_blank" />
          </v-list>
          <!-- Addresses -->
          <template v-if="addressList(detail).length">
            <div class="text-caption text-medium-emphasis mt-3 mb-1">{{ t('contacts.ui.addresses') }}</div>
            <v-list density="comfortable">
              <v-list-item v-for="(a, i) in addressList(detail)" :key="'a'+i" :prepend-icon="mdiMapMarker" :title="a.text" :subtitle="a.type">
                <template #append>
                  <v-btn variant="text" size="small" :icon="mdiMapSearch" :href="mapUrl(a.text)" target="_blank" rel="noopener" :title="t('contacts.ui.map_open_osm')" />
                </template>
              </v-list-item>
            </v-list>
          </template>
          <div v-if="str(detail.note)" class="mt-4"><div class="text-caption text-medium-emphasis">{{ t('contacts.ui.note') }}</div>{{ str(detail.note) }}</div>
        </v-card-text>
      </template>
      <div v-else class="d-flex align-center justify-center fill-height text-medium-emphasis" style="min-height:300px">{{ t('contacts.ui.empty') }}</div>
    </v-card>
  </div>

  <!-- Editor -->
  <v-dialog v-model="editor" max-width="640" scrollable>
    <v-card rounded="xl">
      <v-card-title>{{ editing ? t('contacts.ui.edit_contact') : t('contacts.ui.new_contact') }}</v-card-title>
      <v-card-text>
        <!-- Avatar (only once the contact exists) -->
        <div v-if="editing && selected" class="d-flex align-center ga-3 mb-3">
          <v-avatar size="56" color="primary">
            <v-img v-if="c.avatarUrl(selected)" :src="bust(c.avatarUrl(selected))!" />
            <span v-else>{{ initials(selected) }}</span>
          </v-avatar>
          <v-btn variant="tonal" size="small" :prepend-icon="mdiCamera" :loading="avatarBusy" @click="pickAvatar">{{ t('contacts.ui.avatar_change') }}</v-btn>
          <input ref="avatarInput" type="file" accept="image/*" class="d-none" @change="onAvatarPicked">
        </div>
        <v-select v-model="form.book_id" :items="bookItems" :label="t('contacts.ui.books')" variant="outlined" density="comfortable" />
        <v-row dense>
          <v-col cols="6"><v-text-field v-model="form.first_name" :label="t('contacts.ui.first_name')" variant="outlined" density="compact" /></v-col>
          <v-col cols="6"><v-text-field v-model="form.last_name" :label="t('contacts.ui.last_name')" variant="outlined" density="compact" /></v-col>
        </v-row>
        <v-text-field v-model="form.org" :label="t('contacts.ui.org')" variant="outlined" density="compact" />
        <v-text-field v-model="form.title" :label="t('contacts.ui.title')" variant="outlined" density="compact" />
        <div class="text-caption text-medium-emphasis mt-2">{{ t('contacts.ui.email') }}</div>
        <div v-for="(e, i) in form.emails" :key="'fe'+i" class="d-flex ga-2">
          <v-text-field v-model="e.value" type="email" variant="outlined" density="compact" class="flex-grow-1" />
          <v-btn variant="text" :icon="mdiClose" @click="form.emails.splice(i,1)" />
        </div>
        <v-btn size="small" variant="text" :prepend-icon="mdiPlus" @click="form.emails.push({ value: '', type: 'home' })">{{ t('common.add') }}</v-btn>
        <div class="text-caption text-medium-emphasis mt-2">{{ t('contacts.ui.phone') }}</div>
        <div v-for="(p, i) in form.phones" :key="'fp'+i" class="d-flex ga-2">
          <v-text-field v-model="p.value" variant="outlined" density="compact" class="flex-grow-1" />
          <v-btn variant="text" :icon="mdiClose" @click="form.phones.splice(i,1)" />
        </div>
        <v-btn size="small" variant="text" :prepend-icon="mdiPlus" @click="form.phones.push({ value: '', type: 'cell' })">{{ t('common.add') }}</v-btn>
        <v-textarea v-model="form.note" :label="t('contacts.ui.note')" rows="2" variant="outlined" density="compact" class="mt-2" />
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn variant="text" @click="editor = false">{{ t('common.cancel') }}</v-btn><v-btn color="primary" :loading="saving" @click="save">{{ t('common.save') }}</v-btn></v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Import -->
  <v-dialog v-model="importDialog" max-width="480">
    <v-card rounded="xl">
      <v-card-title>{{ t('contacts.ui.import') }}</v-card-title>
      <v-card-text>
        <v-select v-model="importBookId" :items="bookItems" :label="t('contacts.ui.books')" variant="outlined" density="comfortable" />
        <v-file-input v-model="importFile" accept=".vcf,text/vcard" :label="t('contacts.ui.import')" :prepend-icon="mdiFileUpload" variant="outlined" density="comfortable" hide-details />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="importDialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="importing" :disabled="!importFile || !importBookId" @click="runImport">{{ t('contacts.ui.import') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Duplicates -->
  <v-dialog v-model="dupDialog" max-width="720" scrollable>
    <v-card rounded="xl">
      <v-card-title class="d-flex align-center ga-2">
        <span class="msym" style="font-size:22px">content_copy</span>{{ t('contacts.ui.duplicates') }}
      </v-card-title>
      <v-card-text>
        <div v-if="dupLoading" class="d-flex justify-center pa-6"><v-progress-circular indeterminate /></div>
        <div v-else-if="!dupGroups.length" class="text-medium-emphasis text-center pa-6">{{ t('contacts.dup.empty') }}</div>
        <v-card v-for="g in dupGroups" :key="g.signature" rounded="lg" border flat class="mb-3">
          <div class="px-3 pt-2 d-flex align-center ga-2 flex-wrap">
            <span class="text-caption text-medium-emphasis">{{ t('contacts.dup.matched_by') }}:</span>
            <v-chip v-for="r in g.reasons" :key="r" size="x-small" variant="tonal" color="primary">{{ r }}</v-chip>
          </div>
          <v-radio-group :model-value="dupPrimary[g.signature]" hide-details density="compact" class="px-1" @update:model-value="(v: unknown) => (dupPrimary[g.signature] = String(v))">
            <v-list density="comfortable">
              <v-list-item v-for="m in g.contacts" :key="m.id">
                <template #prepend>
                  <v-radio :value="m.id" class="mr-1" />
                  <v-avatar size="36" :color="dupColor(m.id)">
                    <v-img v-if="m.avatar" :src="m.avatar" />
                    <span v-else class="text-body-2">{{ dupInitials(m) }}</span>
                  </v-avatar>
                </template>
                <v-list-item-title>{{ m.fn || [m.first_name, m.last_name].filter(Boolean).join(' ') || '—' }}</v-list-item-title>
                <v-list-item-subtitle>{{ [m.org, m.emails[0], m.phones[0]].filter(Boolean).join(' · ') }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-radio-group>
          <v-card-actions>
            <span class="text-caption text-medium-emphasis ml-2">{{ t('contacts.dup.keep_as_primary') }}</span>
            <v-spacer />
            <v-btn variant="text" size="small" :loading="dupBusy === g.signature" @click="dismissGroup(g)">{{ t('contacts.dup.dismiss') }}</v-btn>
            <v-btn variant="tonal" size="small" color="primary" :prepend-icon="mdiMerge" :loading="dupBusy === g.signature" @click="mergeGroup(g)">{{ t('contacts.dup.merge') }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn variant="text" @click="dupDialog = false">{{ t('common.close') }}</v-btn></v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import {
  mdiPlus, mdiAccountMultiple, mdiStar, mdiStarOutline, mdiBookOpenPageVariant, mdiFolderPlus, mdiMagnify,
  mdiEmail, mdiPhone, mdiWeb, mdiMapMarker, mdiPencil, mdiDelete, mdiClose, mdiAccountGroup, mdiAccountMultiplePlus,
  mdiUpload, mdiDownload, mdiContentDuplicate, mdiMerge, mdiCamera, mdiFileUpload, mdiMapSearch,
} from '@mdi/js';
import { useContactsStore, type ContactRow, type ContactDetail, type ContactGroup, type DuplicateGroup, type DuplicateContact } from '@spa/stores/contacts';
import { useToast } from '@spa/composables/useToast';

const c = useContactsStore();
const { success, error } = useToast();
const bookId = ref<string | null>(null);
const groupId = ref<number | null>(null);
const favOnly = ref(false);
const query = ref('');
const selected = ref<ContactRow | null>(null);
const detail = ref<ContactDetail | null>(null);
const editor = ref(false);
const editing = ref(false);
const saving = ref(false);
const avatarVersion = ref(0);

type Field = { value: string; type?: string };
const form = reactive<{ book_id: string; first_name: string; last_name: string; org: string; title: string; note: string; emails: Field[]; phones: Field[] }>(
  { book_id: '', first_name: '', last_name: '', org: '', title: '', note: '', emails: [], phones: [] },
);

// Selection (bulk)
const selected_ids = ref<string[]>([]);
const bulkBusy = ref(false);

// Import
const importDialog = ref(false);
const importFile = ref<File | File[] | null>(null);
const importBookId = ref('');
const importing = ref(false);

// Duplicates
const dupDialog = ref(false);
const dupLoading = ref(false);
const dupGroups = ref<DuplicateGroup[]>([]);
const dupPrimary = reactive<Record<string, string>>({});
const dupBusy = ref<string | null>(null);

// Avatar upload
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarBusy = ref(false);

const bookItems = computed(() => c.books.map((b) => ({ title: b.name, value: b.id })));

onMounted(() => c.load());

function str(v: unknown): string { return typeof v === 'string' ? v : ''; }
function arr(v: unknown): Field[] { return Array.isArray(v) ? (v as Field[]) : []; }
function initials(r: ContactRow): string { return ((r.first_name?.[0] ?? '') + (r.last_name?.[0] ?? '') || r.fn?.[0] || '?').toUpperCase(); }
function color(r: ContactRow): string { const p = ['primary', 'secondary', 'success', 'warning', 'error', 'info']; let h = 0; for (const ch of r.id) h = (h + ch.charCodeAt(0)) % p.length; return p[h]; }
function bust(url: string | null): string | null {
  if (!url) return null;
  if (!avatarVersion.value) return url;
  return url + (url.includes('?') ? '&' : '?') + '_a=' + avatarVersion.value;
}
function addressList(d: ContactDetail): { text: string; type: string }[] {
  const list = Array.isArray(d.addresses) ? d.addresses as Record<string, string>[] : [];
  return list
    .map((a) => ({ text: [a.street, a.zip, a.city, a.country].filter(Boolean).join(', '), type: str(a.type) }))
    .filter((a) => a.text !== '');
}
function mapUrl(text: string): string { return `https://www.openstreetmap.org/search?query=${encodeURIComponent(text)}`; }

let debTimer: ReturnType<typeof setTimeout> | undefined;
function debouncedLoad() { clearTimeout(debTimer); debTimer = setTimeout(reload, 300); }
function reload() {
  return c.load({
    book_id: bookId.value ?? undefined,
    group_id: groupId.value ?? undefined,
    favorites: favOnly.value,
    q: query.value || undefined,
  });
}

function pick(b: string | null, fav: boolean) { bookId.value = b; favOnly.value = fav; groupId.value = null; reload(); }
function pickGroup(id: number) { groupId.value = id; bookId.value = null; favOnly.value = false; reload(); }
async function openDetail(row: ContactRow) { selected.value = row; detail.value = await c.show(row.id); }

async function toggleFav() {
  if (!selected.value) return;
  const next = !selected.value.favorite;
  await c.favorite(selected.value.id, next);
  selected.value.favorite = next;
}
async function onDelete() {
  if (!selected.value || !confirm(t('contacts.ui.delete_confirm'))) return;
  await c.destroy(selected.value.id);
  detail.value = null; selected.value = null; await reload();
}

// --- Selection / bulk delete ---
function toggleSelect(id: string) {
  const i = selected_ids.value.indexOf(id);
  if (i >= 0) selected_ids.value.splice(i, 1);
  else selected_ids.value.push(id);
}
function selectAll() { selected_ids.value = c.contacts.map((r) => r.id); }
function clearSelection() { selected_ids.value = []; }
async function deleteSelected() {
  if (!selected_ids.value.length || !confirm(t('contacts.ui.delete_selected_confirm', { count: String(selected_ids.value.length) }))) return;
  bulkBusy.value = true;
  try {
    await c.bulkDestroy([...selected_ids.value]);
    if (selected.value && selected_ids.value.includes(selected.value.id)) { detail.value = null; selected.value = null; }
    clearSelection();
    await reload();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { bulkBusy.value = false; }
}

function openNew() {
  editing.value = false;
  Object.assign(form, { book_id: c.books[0]?.id ?? '', first_name: '', last_name: '', org: '', title: '', note: '', emails: [{ value: '', type: 'home' }], phones: [{ value: '', type: 'cell' }] });
  editor.value = true;
}
function openEdit() {
  if (!detail.value) return;
  editing.value = true;
  const d = detail.value;
  Object.assign(form, {
    book_id: d.book, first_name: str(d.first_name), last_name: str(d.last_name), org: str(d.org), title: str(d.title), note: str(d.note),
    emails: arr(d.emails).map((e) => ({ ...e })), phones: arr(d.phones).map((p) => ({ ...p })),
  });
  editor.value = true;
}
async function save() {
  saving.value = true;
  try {
    const body: Record<string, unknown> = {
      book_id: form.book_id, first_name: form.first_name, last_name: form.last_name, org: form.org, title: form.title, note: form.note,
      fn: [form.first_name, form.last_name].filter(Boolean).join(' ') || form.org,
      emails: form.emails.filter((e) => e.value), phones: form.phones.filter((p) => p.value),
    };
    if (editing.value && selected.value) await c.update(selected.value.id, body);
    else await c.create(body);
    editor.value = false;
    await reload();
    if (editing.value && selected.value) detail.value = await c.show(selected.value.id);
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}

async function newBook() { const name = prompt(t('contacts.ui.new_book')); if (name) { await c.createBook(name); await c.load(); } }

// --- Groups ---
async function newGroup() {
  const name = prompt(t('contacts.ui.new_group'));
  if (!name) return;
  try { await c.createGroup(name); await reload(); success(t('contacts.ui.saved')); }
  catch { error(t('common.error')); }
}
async function removeGroup(g: ContactGroup) {
  if (!confirm(t('contacts.ui.delete_group_confirm'))) return;
  try {
    await c.deleteGroup(g.id);
    if (groupId.value === g.id) { groupId.value = null; }
    await reload();
  } catch { error(t('common.error')); }
}

// --- Import / export ---
function openImport() {
  importFile.value = null;
  importBookId.value = bookId.value ?? c.books[0]?.id ?? '';
  importDialog.value = true;
}
async function runImport() {
  const file = Array.isArray(importFile.value) ? importFile.value[0] : importFile.value;
  if (!file || !importBookId.value) return;
  importing.value = true;
  try {
    const r = await c.importVcf(file, importBookId.value);
    importDialog.value = false;
    await reload();
    success(t('contacts.ui.import_result', { created: String(r.created), updated: String(r.updated), skipped: String(r.skipped) }));
  } catch { error(t('common.error')); } finally { importing.value = false; }
}

// --- Duplicates ---
function dupInitials(m: DuplicateContact): string { return ((m.first_name?.[0] ?? '') + (m.last_name?.[0] ?? '') || m.fn?.[0] || '?').toUpperCase(); }
function dupColor(id: string): string { const p = ['primary', 'secondary', 'success', 'warning', 'error', 'info']; let h = 0; for (const ch of id) h = (h + ch.charCodeAt(0)) % p.length; return p[h]; }
async function openDuplicates() {
  dupDialog.value = true;
  dupLoading.value = true;
  try {
    dupGroups.value = await c.loadDuplicates();
    for (const g of dupGroups.value) dupPrimary[g.signature] = g.contacts[0]?.id ?? '';
  } catch { error(t('common.error')); } finally { dupLoading.value = false; }
}
async function mergeGroup(g: DuplicateGroup) {
  const primary = dupPrimary[g.signature] || g.contacts[0]?.id;
  if (!primary || !confirm(t('contacts.dup.merge_confirm'))) return;
  dupBusy.value = g.signature;
  try {
    await c.mergeDuplicates({ primary_id: primary, ids: g.contacts.map((m) => m.id) });
    dupGroups.value = dupGroups.value.filter((x) => x.signature !== g.signature);
    await reload();
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); } finally { dupBusy.value = null; }
}
async function dismissGroup(g: DuplicateGroup) {
  dupBusy.value = g.signature;
  try {
    await c.dismissDuplicate({ ids: g.contacts.map((m) => m.id) });
    dupGroups.value = dupGroups.value.filter((x) => x.signature !== g.signature);
  } catch { error(t('common.error')); } finally { dupBusy.value = null; }
}

// --- Avatar upload ---
function pickAvatar() { avatarInput.value?.click(); }
async function onAvatarPicked(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file || !selected.value) return;
  avatarBusy.value = true;
  try {
    await c.uploadAvatar(selected.value.id, file);
    avatarVersion.value = Date.now();
    selected.value.has_photo = true;
    await reload();
    detail.value = await c.show(selected.value.id);
    success(t('contacts.ui.saved'));
  } catch { error(t('common.error')); } finally { avatarBusy.value = false; input.value = ''; }
}
</script>
