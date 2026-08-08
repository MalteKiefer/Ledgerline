<template>
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('pages.settings.users') }}</v-toolbar-title>
      <v-spacer />
      <v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="openNew">{{ t('common.add') }}</v-btn>
    </v-toolbar>
    <v-divider />
    <v-data-table :headers="headers" :items="s.users" :loading="loading" density="comfortable">
      <template #[`item.role`]="{ item }">
        <v-chip size="small" :color="item.role === 'admin' ? 'primary' : undefined">{{ item.role }}</v-chip>
      </template>
      <template #[`item.two_factor`]="{ item }">
        <v-icon :icon="item.two_factor ? mdiCheck : mdiClose" :color="item.two_factor ? 'success' : undefined" size="small" />
      </template>
      <template #[`item.actions`]="{ item }">
        <v-menu>
          <template #activator="{ props }"><v-btn variant="text" size="small" :icon="mdiDotsVertical" v-bind="props" /></template>
          <v-list density="compact">
            <v-list-item :prepend-icon="mdiPencil" :title="t('common.edit')" @click="openEdit(item)" />
            <v-list-item :prepend-icon="mdiKey" :title="t('pages.settings.reset_password')" @click="onResetPw(item)" />
            <v-list-item v-if="item.two_factor" :prepend-icon="mdiShieldRefresh" title="Reset 2FA" @click="s.reset2fa(item.id)" />
            <v-list-item :prepend-icon="mdiLinkVariant" :title="t('pages.settings.invite_link')" @click="onInvite(item)" />
            <v-divider />
            <v-list-item :prepend-icon="mdiDelete" :title="t('common.delete')" base-color="error" @click="onDelete(item)" />
          </v-list>
        </v-menu>
      </template>
    </v-data-table>
  </v-card>

  <v-dialog v-model="dialog" max-width="520">
    <v-card rounded="xl">
      <v-card-title>{{ editing ? t('common.edit') : t('common.add') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.name" :label="t('account.name')" variant="outlined" density="comfortable" :error-messages="err.name" />
        <v-text-field v-model="form.email" :label="t('account.email')" type="email" variant="outlined" density="comfortable" :error-messages="err.email" />
        <v-text-field v-if="!editing" v-model="form.password" :label="t('account.password')" type="password" variant="outlined" density="comfortable" :error-messages="err.password" />
        <v-select v-model="form.role" :items="['user','admin']" :label="t('pages.settings.role')" variant="outlined" density="comfortable" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">{{ t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="linkDialog" max-width="560">
    <v-card rounded="xl">
      <v-card-title>{{ t('pages.settings.invite_link') }}</v-card-title>
      <v-card-text>
        <v-text-field :model-value="link" readonly variant="outlined" append-inner-icon="mdiContentCopy" @click:append-inner="copy" />
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn variant="text" @click="linkDialog = false">{{ t('common.close') }}</v-btn></v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlus, mdiPencil, mdiDelete, mdiKey, mdiShieldRefresh, mdiLinkVariant, mdiDotsVertical, mdiCheck, mdiClose } from '@mdi/js';
import { useSettingsStore, type AdminUser } from '@spa/stores/settings';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';

const s = useSettingsStore();
const { success, error } = useToast();
const loading = ref(false);
const dialog = ref(false);
const saving = ref(false);
const editing = ref<AdminUser | null>(null);
const linkDialog = ref(false);
const link = ref('');

const headers = [
  { title: t('account.name'), key: 'name' },
  { title: t('account.email'), key: 'email' },
  { title: t('pages.settings.role'), key: 'role' },
  { title: '2FA', key: 'two_factor' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
];
const form = reactive({ name: '', email: '', password: '', role: 'user' });
const err = reactive<Record<string, string[] | undefined>>({});

onMounted(load);
async function load() { loading.value = true; try { await s.loadUsers(); } finally { loading.value = false; } }

function openNew() { editing.value = null; Object.assign(form, { name: '', email: '', password: '', role: 'user' }); clearErr(); dialog.value = true; }
function openEdit(u: AdminUser) { editing.value = u; Object.assign(form, { name: u.name, email: u.email, password: '', role: u.role }); clearErr(); dialog.value = true; }
function clearErr() { Object.keys(err).forEach((k) => (err[k] = undefined)); }

async function save() {
  saving.value = true; clearErr();
  try {
    if (editing.value) await s.updateUser(editing.value.id, { name: form.name, email: form.email, role: form.role });
    else await s.createUser({ name: form.name, email: form.email, password: form.password, role: form.role });
    dialog.value = false;
    await load();
    success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.fields) Object.assign(err, e.fields);
    else error(t('common.error'));
  } finally { saving.value = false; }
}

async function onResetPw(u: AdminUser) {
  const r = await s.resetPassword(u.id);
  if (r?.link) { link.value = r.link; linkDialog.value = true; } else success(t('common.saved'));
}
async function onInvite(u: AdminUser) {
  const r = await s.inviteLink(u.id, '24h');
  link.value = r.url; linkDialog.value = true;
}
async function onDelete(u: AdminUser) {
  if (!confirm(t('common.confirm_delete'))) return;
  try { await s.deleteUser(u.id); await load(); } catch { error(t('common.error')); }
}
function copy() { navigator.clipboard?.writeText(link.value); success(t('common.copied')); }
</script>
