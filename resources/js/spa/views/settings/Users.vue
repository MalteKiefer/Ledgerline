<template>
  <Card :title="t('settings.users_registration')" class="mb-4">
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm text-[var(--ll-muted)]">{{ t('settings.users_registration_hint') }}</p>
      <button
        type="button" role="switch" :aria-checked="regAllow" :disabled="regBusy"
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50"
        :class="regAllow ? 'bg-primary-500' : 'bg-[var(--ll-border)]'"
        :title="regAllow ? t('settings.users_registration_on') : t('settings.users_registration_off')"
        @click="toggleReg"
      >
        <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="regAllow ? 'translate-x-5' : 'translate-x-0.5'" />
      </button>
    </div>
  </Card>

  <Card :title="t('settings.users_section')" body-class="p-0">
    <template #actions>
      <Btn variant="solid" size="sm" icon="add" @click="openNew">{{ t('common.add') }}</Btn>
    </template>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
          <tr class="border-b border-[var(--ll-border)]">
            <th class="px-4 py-2.5 font-medium">{{ t('settings.users_name') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.users_email') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.users_role') }}</th>
            <th class="px-4 py-2.5 font-medium">2FA</th>
            <th class="px-4 py-2.5 font-medium text-right"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="5" class="px-4 py-8 text-center text-[var(--ll-muted)]">…</td></tr>
          <template v-else>
            <tr
              v-for="u in s.users" :key="u.id"
              class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
            >
              <td class="px-4 py-2.5 font-medium">{{ u.name }}</td>
              <td class="px-4 py-2.5 text-[var(--ll-muted)]">{{ u.email }}</td>
              <td class="px-4 py-2.5"><Badge :tone="u.role === 'admin' ? 'primary' : 'gray'">{{ u.role }}</Badge></td>
              <td class="px-4 py-2.5">
                <Icon v-if="u.two_factor" name="check" :size="18" class="text-emerald-600 dark:text-emerald-400" />
                <Icon v-else name="close" :size="18" class="text-[var(--ll-muted)]" />
              </td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-1">
                  <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('common.edit')" @click="openEdit(u)">
                    <Icon name="edit" :size="18" />
                  </button>
                  <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('settings.users_reset')" @click="onResetPw(u)">
                    <Icon name="key" :size="18" />
                  </button>
                  <button v-if="u.two_factor" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" title="Reset 2FA" @click="s.reset2fa(u.id)">
                    <Icon name="lock_reset" :size="18" />
                  </button>
                  <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('settings.users_invite_create')" @click="onInvite(u)">
                    <Icon name="add" :size="18" />
                  </button>
                  <button class="grid h-8 w-8 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('common.delete')" @click="onDelete(u)">
                    <Icon name="delete" :size="18" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!s.users.length"><td colspan="5" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </template>
        </tbody>
      </table>
    </div>
  </Card>

  <Modal v-model="dialog" :title="editing ? t('common.edit') : t('common.add')">
    <div class="space-y-4">
      <TextField v-model="form.name" :label="t('settings.users_name')" :error="err.name?.[0]" />
      <TextField v-model="form.email" :label="t('settings.users_email')" type="email" :error="err.email?.[0]" />
      <TextField v-if="!editing" v-model="form.password" :label="t('settings.users_password')" type="password" :error="err.password?.[0]" />
      <Select v-model="form.role" :label="t('settings.users_role')" :options="roleOptions" />
    </div>
    <template #footer>
      <Btn variant="ghost" @click="dialog = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="saving" @click="save">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <Modal v-model="linkDialog" :title="t('settings.users_invite_create')" width="560px">
    <div class="flex items-center gap-2">
      <input
        :value="link" readonly
        class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40"
      >
      <Btn variant="soft" size="sm" @click="copy">Copy</Btn>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="linkDialog = false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useSettingsStore, type AdminUser } from '@spa/stores/settings';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { ApiError } from '@spa/api/client';

const s = useSettingsStore();
const { success, error } = useToast();
const loading = ref(false);
const dialog = ref(false);
const saving = ref(false);
const editing = ref<AdminUser | null>(null);
const linkDialog = ref(false);
const link = ref('');

const roleOptions = [
  { title: 'user', value: 'user' },
  { title: 'admin', value: 'admin' },
];

const form = reactive({ name: '', email: '', password: '', role: 'user' });
const err = reactive<Record<string, string[] | undefined>>({});

// Workspace self-registration toggle (admin only; this view is already admin-gated).
const regAllow = ref(false);
const regBusy = ref(false);

onMounted(() => { void load(); void loadReg(); });
async function load() { loading.value = true; try { await s.loadUsers(); } finally { loading.value = false; } }
async function loadReg() { try { regAllow.value = (await s.getRegistration()).allow_registration; } catch { /* non-admin / unavailable */ } }
async function toggleReg() {
  regBusy.value = true;
  try {
    regAllow.value = (await s.setRegistration(!regAllow.value)).allow_registration;
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { regBusy.value = false; }
}

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
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await s.deleteUser(u.id); await load(); } catch { error(t('common.error')); }
}
function copy() { navigator.clipboard?.writeText(link.value); success(t('common.copied')); }
</script>
