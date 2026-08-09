<template>
  <Card :title="t('mail.keys.title')">
    <template #actions>
      <Btn variant="soft" size="sm" icon="add" @click="openImport('pgp')">{{ t('mail.keys.import_pgp') }}</Btn>
      <Btn variant="soft" size="sm" icon="add" @click="openImport('smime')">{{ t('mail.keys.import_smime') }}</Btn>
    </template>

    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('mail.keys.subtitle') }}</p>

    <div v-if="loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!keys.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.none') }}</div>
    <div v-else class="divide-y divide-[var(--ll-border)]">
      <div v-for="k in keys" :key="k.id" class="flex items-start gap-3 py-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-500/15 text-primary-600 dark:text-primary-300"><Icon name="key" :size="20" /></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate text-sm font-medium">{{ k.label }}</span>
            <Badge :tone="k.type === 'pgp' ? 'primary' : 'info'">{{ k.type.toUpperCase() }}</Badge>
          </div>
          <div v-if="k.key_fingerprint || k.key_id" class="truncate font-mono text-xs text-[var(--ll-muted)]">{{ t('mail.keys.fingerprint') }}: {{ k.key_fingerprint || k.key_id }}</div>
          <div v-if="k.identities?.length" class="truncate text-xs text-[var(--ll-muted)]">{{ k.identities.map((i) => i.email).join(', ') }}</div>
          <div v-if="k.expires_at" class="text-xs text-[var(--ll-muted)]">{{ new Date(k.expires_at).toLocaleDateString() }}</div>
        </div>
        <Btn v-if="k.public_key" variant="ghost" size="sm" icon="content_copy" @click="copyPublic(k.public_key)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" :title="t('mail.keys.delete')" @click="remove(k)" />
      </div>
    </div>
  </Card>

  <!-- Import modal -->
  <Modal v-model="dlg.show" :title="dlg.type === 'pgp' ? t('mail.keys.import_pgp') : t('mail.keys.import_smime')" width="560px">
    <div class="space-y-3">
      <TextField v-model="dlg.label" :label="t('mail.keys.label')" />
      <label v-if="dlg.type === 'pgp'" class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.armored_private_key') }}</span>
        <textarea
          v-model="dlg.armored" rows="6"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          placeholder="-----BEGIN PGP PRIVATE KEY BLOCK-----"
        ></textarea>
      </label>
      <label v-else class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.p12_file') }}</span>
        <input type="file" accept=".p12,.pfx" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 dark:file:text-primary-300" @change="onP12">
      </label>
      <TextField v-model="dlg.passphrase" :label="t('mail.keys.passphrase')" type="password" autocomplete="new-password" />
    </div>
    <template #footer>
      <Btn variant="ghost" @click="dlg.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="dlg.busy" :disabled="!canImport" @click="doImport">{{ t('mail.keys.add') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Badge, Modal } from '@spa/ui';
import { useMailStore, type MailKey } from '@spa/stores/mail';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const s = useMailStore();
const { success, error } = useToast();
const keys = ref<MailKey[]>([]);
const loading = ref(false);

const dlg = reactive<{ show: boolean; busy: boolean; type: 'pgp' | 'smime'; label: string; armored: string; p12: string | null; passphrase: string }>(
  { show: false, busy: false, type: 'pgp', label: '', armored: '', p12: null, passphrase: '' },
);
const canImport = computed(() => dlg.label.trim() !== '' && (dlg.type === 'pgp' ? dlg.armored.trim() !== '' : dlg.p12 !== null));

onMounted(load);
async function load() {
  loading.value = true;
  try { keys.value = (await s.loadKeys()).keys; } catch { error(t('common.error')); } finally { loading.value = false; }
}

function openImport(type: 'pgp' | 'smime') { Object.assign(dlg, { show: true, busy: false, type, label: '', armored: '', p12: null, passphrase: '' }); }

function onP12(ev: Event) {
  const file = (ev.target as HTMLInputElement).files?.[0];
  if (!file) { dlg.p12 = null; return; }
  const reader = new FileReader();
  reader.onload = () => { const r = String(reader.result); dlg.p12 = r.slice(r.indexOf(',') + 1); }; // strip data: prefix → base64
  reader.readAsDataURL(file);
}

async function doImport() {
  if (!canImport.value) return;
  dlg.busy = true;
  try {
    await s.importKey({
      type: dlg.type,
      label: dlg.label.trim(),
      passphrase: dlg.passphrase || null,
      armored_private_key: dlg.type === 'pgp' ? dlg.armored : null,
      p12_base64: dlg.type === 'smime' ? dlg.p12 : null,
    });
    dlg.show = false;
    await load();
    success(t('mail.keys.imported'));
  } catch { error(t('mail.keys.import_failed')); } finally { dlg.busy = false; }
}

async function copyPublic(pub: string) {
  try { await navigator.clipboard.writeText(pub); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function remove(k: MailKey) {
  if (!await confirmAsk(t('mail.keys.delete_confirm'), { danger: true })) return;
  try { await s.deleteKey(k.id); keys.value = keys.value.filter((x) => x.id !== k.id); } catch { error(t('common.error')); }
}
</script>
