<template>
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('pages.settings.groups') }}</v-toolbar-title>
      <v-spacer />
      <v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="openNew">{{ t('common.add') }}</v-btn>
    </v-toolbar>
    <v-divider />
    <v-list>
      <v-list-item v-for="g in s.groups" :key="g.id" :title="g.name" :subtitle="sub(g)">
        <template #append>
          <v-btn variant="text" size="small" :icon="mdiPencil" @click="openEdit(g)" />
          <v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="onDelete(g)" />
        </template>
      </v-list-item>
      <v-list-item v-if="!s.groups.length" :title="t('common.none')" class="text-medium-emphasis" />
    </v-list>
  </v-card>

  <v-dialog v-model="dialog" max-width="520">
    <v-card rounded="xl">
      <v-card-title>{{ editing ? t('common.edit') : t('common.add') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="form.name" :label="t('account.name')" variant="outlined" density="comfortable" />
        <v-row dense>
          <v-col cols="6"><v-text-field v-model.number="form.files_quota_mb" label="Files MB" type="number" variant="outlined" density="compact" /></v-col>
          <v-col cols="6"><v-text-field v-model.number="form.max_connected_devices" label="Max devices" type="number" variant="outlined" density="compact" /></v-col>
        </v-row>
        <v-switch v-model="form.shareable" label="Shareable" color="primary" density="compact" />
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn variant="text" @click="dialog = false">{{ t('common.cancel') }}</v-btn><v-btn color="primary" :loading="saving" @click="save">{{ t('common.save') }}</v-btn></v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlus, mdiPencil, mdiDelete } from '@mdi/js';
import { useSettingsStore, type Group } from '@spa/stores/settings';
import { useToast } from '@spa/composables/useToast';

const s = useSettingsStore();
const { success, error } = useToast();
const dialog = ref(false);
const saving = ref(false);
const editing = ref<Group | null>(null);
const form = reactive<Record<string, unknown>>({ name: '', files_quota_mb: null, max_connected_devices: null, shareable: false });

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
async function onDelete(g: Group) { if (!confirm(t('common.confirm_delete'))) return; await s.deleteGroup(g.id); await s.loadGroups(); }
</script>
