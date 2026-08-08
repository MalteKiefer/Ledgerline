<template>
  <div class="d-flex flex-column flex-md-row ga-4" style="min-height:calc(100vh - 120px)">
    <!-- Books / groups rail -->
    <v-card rounded="xl" border flat width="220" class="flex-shrink-0" style="align-self:flex-start">
      <div class="pa-3">
        <v-btn color="primary" block :prepend-icon="mdiPlus" @click="openNew">{{ t('contacts.ui.new_contact') }}</v-btn>
      </div>
      <v-list density="compact" nav>
        <v-list-subheader>{{ t('contacts.ui.books') }}</v-list-subheader>
        <v-list-item :active="!bookId && !favOnly" :prepend-icon="mdiAccountMultiple" :title="t('contacts.ui.all_books')" @click="pick(null, false)" />
        <v-list-item :active="favOnly" :prepend-icon="mdiStar" :title="t('contacts.ui.favorites')" @click="pick(null, true)" />
        <v-list-item v-for="b in c.books" :key="b.id" :active="bookId === b.id" :prepend-icon="mdiBookOpenPageVariant" :title="b.name" @click="pick(b.id, false)" />
        <v-list-item :prepend-icon="mdiFolderPlus" :title="t('contacts.ui.new_book')" @click="newBook" />
      </v-list>
    </v-card>

    <!-- List -->
    <v-card rounded="xl" border flat width="340" class="flex-shrink-0 d-flex flex-column" style="align-self:stretch">
      <div class="pa-3 border-b">
        <v-text-field v-model="query" :placeholder="t('common.search')" :prepend-inner-icon="mdiMagnify" variant="solo-filled" flat density="compact" hide-details single-line @update:model-value="debouncedLoad" />
      </div>
      <div class="flex-grow-1 overflow-y-auto">
        <v-list density="comfortable">
          <v-list-item v-for="row in c.contacts" :key="row.id" :active="selected?.id === row.id" @click="openDetail(row)">
            <template #prepend>
              <v-avatar size="40" :color="color(row)">
                <v-img v-if="c.avatarUrl(row)" :src="c.avatarUrl(row)!" />
                <span v-else class="text-body-2">{{ initials(row) }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ row.fn || (row.first_name + ' ' + row.last_name) }}</v-list-item-title>
            <v-list-item-subtitle>{{ row.org || row.emails[0]?.value || '' }}</v-list-item-subtitle>
            <template #append><v-icon v-if="row.favorite" :icon="mdiStar" color="amber" size="small" /></template>
          </v-list-item>
          <v-list-item v-if="!c.contacts.length" :title="t('common.none')" class="text-medium-emphasis" />
        </v-list>
      </div>
    </v-card>

    <!-- Detail -->
    <v-card rounded="xl" border flat class="flex-grow-1" style="min-width:0">
      <template v-if="detail">
        <v-toolbar flat color="surface">
          <v-avatar size="48" :color="'primary'" class="ml-2">
            <v-img v-if="selected && c.avatarUrl(selected)" :src="c.avatarUrl(selected)!" />
            <span v-else>{{ selected ? initials(selected) : '' }}</span>
          </v-avatar>
          <v-toolbar-title class="ml-3">{{ str(detail.fn) }}</v-toolbar-title>
          <v-spacer />
          <v-btn variant="text" :icon="selected?.favorite ? mdiStar : mdiStarOutline" @click="toggleFav" />
          <v-btn variant="text" :icon="mdiPencil" @click="openEdit" />
          <v-btn variant="text" color="error" :icon="mdiDelete" @click="onDelete" />
        </v-toolbar>
        <v-divider />
        <v-card-text>
          <div v-if="str(detail.org)" class="mb-4 text-medium-emphasis">{{ str(detail.org) }}<span v-if="str(detail.title)"> · {{ str(detail.title) }}</span></div>
          <v-list density="comfortable">
            <v-list-item v-for="(e, i) in arr(detail.emails)" :key="'e'+i" :prepend-icon="mdiEmail" :title="e.value" :subtitle="e.type" :href="'mailto:' + e.value" />
            <v-list-item v-for="(p, i) in arr(detail.phones)" :key="'p'+i" :prepend-icon="mdiPhone" :title="p.value" :subtitle="p.type" :href="'tel:' + p.value" />
            <v-list-item v-for="(u, i) in arr(detail.urls)" :key="'u'+i" :prepend-icon="mdiWeb" :title="u.value" :href="u.value" target="_blank" />
            <v-list-item v-for="(a, i) in addresses(detail)" :key="'a'+i" :prepend-icon="mdiMapMarker" :title="a" />
          </v-list>
          <div v-if="str(detail.note)" class="mt-4"><div class="text-caption text-medium-emphasis">{{ t('contacts.ui.note') }}</div>{{ str(detail.note) }}</div>
        </v-card-text>
      </template>
      <div v-else class="d-flex align-center justify-center fill-height text-medium-emphasis" style="min-height:300px">{{ t('contacts.ui.empty') }}</div>
    </v-card>
  </div>

  <!-- Editor -->
  <v-dialog v-model="editor" max-width="640" scrollable>
    <v-card rounded="xl">
      <v-card-title>{{ editing ? t('common.edit') : t('contacts.ui.new_contact') }}</v-card-title>
      <v-card-text>
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
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlus, mdiAccountMultiple, mdiStar, mdiStarOutline, mdiBookOpenPageVariant, mdiFolderPlus, mdiMagnify, mdiEmail, mdiPhone, mdiWeb, mdiMapMarker, mdiPencil, mdiDelete, mdiClose } from '@mdi/js';
import { useContactsStore, type ContactRow, type ContactDetail } from '@spa/stores/contacts';
import { useToast } from '@spa/composables/useToast';

const c = useContactsStore();
const { success, error } = useToast();
const bookId = ref<string | null>(null);
const favOnly = ref(false);
const query = ref('');
const selected = ref<ContactRow | null>(null);
const detail = ref<ContactDetail | null>(null);
const editor = ref(false);
const editing = ref(false);
const saving = ref(false);

type Field = { value: string; type?: string };
const form = reactive<{ book_id: string; first_name: string; last_name: string; org: string; title: string; note: string; emails: Field[]; phones: Field[] }>(
  { book_id: '', first_name: '', last_name: '', org: '', title: '', note: '', emails: [], phones: [] },
);

const bookItems = computed(() => c.books.map((b) => ({ title: b.name, value: b.id })));

onMounted(() => c.load());

function str(v: unknown): string { return typeof v === 'string' ? v : ''; }
function arr(v: unknown): Field[] { return Array.isArray(v) ? (v as Field[]) : []; }
function initials(r: ContactRow): string { return ((r.first_name?.[0] ?? '') + (r.last_name?.[0] ?? '') || r.fn?.[0] || '?').toUpperCase(); }
function color(r: ContactRow): string { const p = ['primary', 'secondary', 'success', 'warning', 'error', 'info']; let h = 0; for (const ch of r.id) h = (h + ch.charCodeAt(0)) % p.length; return p[h]; }
function addresses(d: ContactDetail): string[] {
  const list = Array.isArray(d.addresses) ? d.addresses as Record<string, string>[] : [];
  return list.map((a) => [a.street, a.zip, a.city, a.country].filter(Boolean).join(', ')).filter(Boolean);
}

let debTimer: ReturnType<typeof setTimeout> | undefined;
function debouncedLoad() { clearTimeout(debTimer); debTimer = setTimeout(reload, 300); }
function reload() { return c.load({ book_id: bookId.value ?? undefined, favorites: favOnly.value, q: query.value || undefined }); }

function pick(b: string | null, fav: boolean) { bookId.value = b; favOnly.value = fav; reload(); }
async function openDetail(row: ContactRow) { selected.value = row; detail.value = await c.show(row.id); }
async function toggleFav() {
  if (!selected.value) return;
  const next = !selected.value.favorite;
  await c.favorite(selected.value.id, next);
  selected.value.favorite = next;
}
async function onDelete() {
  if (!selected.value || !confirm(t('common.confirm_delete'))) return;
  await c.destroy(selected.value.id);
  detail.value = null; selected.value = null; await reload();
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
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}
async function newBook() { const name = prompt(t('contacts.ui.new_book')); if (name) { await c.createBook(name); await c.load(); } }
</script>
