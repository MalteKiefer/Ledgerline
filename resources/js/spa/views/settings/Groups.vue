<template>
  <Card :title="t('settings.groups_section')" body-class="p-0">
    <template #actions>
      <Btn variant="solid" size="sm" icon="add" @click="openNew">{{ t('common.add') }}</Btn>
    </template>

    <div class="divide-y divide-[var(--ll-border)]">
      <div v-for="g in s.groups" :key="g.id" class="flex items-center gap-3 px-4 py-3">
        <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-300">
          <Icon name="badge" :size="20" />
        </span>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-medium">{{ g.name }}</div>
          <div v-if="sub(g)" class="text-xs text-[var(--ll-muted)]">{{ sub(g) }}</div>
        </div>
        <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('common.edit')" @click="openEdit(g)">
          <Icon name="edit" :size="18" />
        </button>
        <button class="grid h-8 w-8 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('common.delete')" @click="onDelete(g)">
          <Icon name="delete" :size="18" />
        </button>
      </div>
      <div v-if="!s.groups.length" class="px-4 py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
    </div>
  </Card>

  <Modal v-model="dialog" :title="editing ? t('common.edit') : t('common.add')">
    <div class="space-y-4">
      <TextField v-model="nameModel" :label="t('settings.groups_name')" />
      <div class="grid grid-cols-2 gap-3">
        <TextField v-model="filesQuotaStr" label="Files MB" type="number" inputmode="numeric" />
        <TextField v-model="maxDevicesStr" label="Max devices" type="number" inputmode="numeric" />
      </div>
      <label class="flex items-center justify-between gap-3 py-1">
        <span class="text-sm font-medium">Shareable</span>
        <button
          type="button" role="switch" :aria-checked="!!form.shareable"
          class="relative h-6 w-10 rounded-full transition-colors" :class="form.shareable ? 'bg-primary-500' : 'bg-black/10 dark:bg-white/15'"
          @click="form.shareable = !form.shareable"
        >
          <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="form.shareable ? 'translate-x-4' : ''" />
        </button>
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="dialog = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="saving" @click="save">{{ t('common.save') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Modal } from '@spa/ui';
import { useSettingsStore, type Group } from '@spa/stores/settings';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const s = useSettingsStore();
const { success, error } = useToast();
const dialog = ref(false);
const saving = ref(false);
const editing = ref<Group | null>(null);
const form = reactive<Record<string, unknown>>({ name: '', files_quota_mb: null, max_connected_devices: null, shareable: false });

// TextField only emits strings — bridge form's loosely-typed fields for the kit's input contract.
const nameModel = computed<string>({
  get: () => (typeof form.name === 'string' ? form.name : ''),
  set: (v: string) => { form.name = v; },
});
const filesQuotaStr = computed<string>({
  get: () => (form.files_quota_mb == null ? '' : String(form.files_quota_mb)),
  set: (v: string) => { form.files_quota_mb = v === '' ? null : Number(v); },
});
const maxDevicesStr = computed<string>({
  get: () => (form.max_connected_devices == null ? '' : String(form.max_connected_devices)),
  set: (v: string) => { form.max_connected_devices = v === '' ? null : Number(v); },
});

onMounted(() => s.loadGroups());
function sub(g: Group) { return [g.files_quota_mb ? `${g.files_quota_mb} MB` : null, g.max_connected_devices ? `${g.max_connected_devices} dev` : null, g.members?.length ? `${g.members.length} ✕` : null].filter(Boolean).join(' · '); }
function openNew() { editing.value = null; Object.assign(form, { name: '', files_quota_mb: null, max_connected_devices: null, shareable: false }); dialog.value = true; }
function openEdit(g: Group) { editing.value = g; Object.assign(form, { name: g.name, files_quota_mb: g.files_quota_mb, max_connected_devices: g.max_connected_devices, shareable: g.shareable ?? false }); dialog.value = true; }
async function save() {
  saving.value = true;
  try {
    if (editing.value) await s.updateGroup(editing.value.id, { ...form });
    else await s.createGroup({ ...form });
    dialog.value = false; await s.loadGroups(); success(t('common.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}
async function onDelete(g: Group) { if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return; await s.deleteGroup(g.id); await s.loadGroups(); }
</script>
