<template>
  <Card :title="t('mail.keys.title')">
    <template #actions>
      <Btn variant="solid" size="sm" icon="key" @click="openGenerate">{{ t('mail.keys.generate') }}</Btn>
      <Btn variant="soft" size="sm" icon="add" @click="openImport('pgp')">{{ t('mail.keys.import_pgp') }}</Btn>
      <Btn variant="soft" size="sm" icon="add" @click="openImport('smime')">{{ t('mail.keys.import_smime') }}</Btn>
    </template>

    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('mail.keys.subtitle') }}</p>

    <div v-if="loading" class="py-6 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!keys.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.none') }}</div>
    <div v-else class="divide-y divide-[var(--ll-border)]">
      <div
        v-for="k in keys" :key="k.id"
        class="flex cursor-pointer items-start gap-3 rounded-lg py-3 transition-colors hover:bg-black/[0.02] dark:hover:bg-white/5"
        @click="openDetail(k)"
      >
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-500/15 text-primary-600 dark:text-primary-300"><Icon name="key" :size="20" /></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate text-sm font-medium">{{ k.label }}</span>
            <Badge :tone="k.type === 'pgp' ? 'primary' : 'info'">{{ k.type.toUpperCase() }}</Badge>
            <Badge v-if="k.algorithm" tone="gray">{{ k.algorithm }}{{ k.key_length ? ' ' + k.key_length : (k.curve ? ' ' + k.curve : '') }}</Badge>
          </div>
          <div v-if="k.key_fingerprint || k.key_id" class="truncate font-mono text-xs text-[var(--ll-muted)]">{{ t('mail.keys.fingerprint') }}: {{ k.key_fingerprint || k.key_id }}</div>
          <div v-if="k.identities?.length" class="truncate text-xs text-[var(--ll-muted)]">{{ k.identities.map(formatIdentity).join(', ') }}</div>
          <div v-if="k.expires_at" class="text-xs text-[var(--ll-muted)]">{{ new Date(k.expires_at).toLocaleDateString() }}</div>
        </div>
        <Btn v-if="k.public_key" variant="ghost" size="sm" icon="content_copy" :title="t('mail.keys.copy_public_key')" @click.stop="copyText(k.public_key)" />
        <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" :title="t('mail.keys.delete')" @click.stop="remove(k)" />
      </div>
    </div>
  </Card>

  <!-- Detail modal: every field the key/certificate itself carries -->
  <Modal v-model="detail.show" :title="t('mail.keys.details')" width="620px">
    <div v-if="detail.key" class="space-y-4">
      <div class="flex items-center gap-2">
        <span class="text-base font-medium">{{ detail.key.label }}</span>
        <Badge :tone="detail.key.type === 'pgp' ? 'primary' : 'info'">{{ detail.key.type.toUpperCase() }}</Badge>
      </div>

      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.identities') }}</span>
        <div v-if="detail.key.identities?.length" class="space-y-1">
          <div v-for="(i, idx) in detail.key.identities" :key="idx" class="text-sm">{{ formatIdentity(i) }}</div>
        </div>
        <p v-else class="text-sm text-[var(--ll-muted)]">{{ t('mail.keys.identities_none') }}</p>
      </div>

      <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
        <template v-if="detail.key.key_fingerprint">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.fingerprint') }}</dt>
          <dd class="truncate font-mono text-xs" :title="detail.key.key_fingerprint">{{ detail.key.key_fingerprint }}</dd>
        </template>
        <template v-if="detail.key.key_id">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.key_id') }}</dt>
          <dd class="truncate font-mono text-xs">{{ detail.key.key_id }}</dd>
        </template>
        <template v-if="detail.key.algorithm">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.algorithm') }}</dt>
          <dd>{{ detail.key.algorithm }}</dd>
        </template>
        <template v-if="detail.key.key_length">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.key_length') }}</dt>
          <dd>{{ detail.key.key_length }} bit</dd>
        </template>
        <template v-if="detail.key.curve">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.curve') }}</dt>
          <dd>{{ detail.key.curve }}</dd>
        </template>
        <template v-if="detail.key.issuer">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.issuer') }}</dt>
          <dd class="truncate" :title="detail.key.issuer">{{ detail.key.issuer }}</dd>
        </template>
        <template v-if="detail.key.serial">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.serial') }}</dt>
          <dd class="truncate font-mono text-xs">{{ detail.key.serial }}</dd>
        </template>
        <template v-if="detail.key.valid_from">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.valid_from') }}</dt>
          <dd>{{ new Date(detail.key.valid_from).toLocaleDateString() }}</dd>
        </template>
        <template v-if="detail.key.expires_at">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.expiry') }}</dt>
          <dd>{{ new Date(detail.key.expires_at).toLocaleDateString() }}</dd>
        </template>
        <template v-if="detail.key.created_at">
          <dt class="text-[var(--ll-muted)]">{{ t('mail.keys.created') }}</dt>
          <dd>{{ new Date(detail.key.created_at).toLocaleDateString() }}</dd>
        </template>
      </dl>

      <div v-if="detail.key.public_key">
        <div class="mb-1.5 flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.public_key') }}</span>
          <Btn variant="ghost" size="xs" icon="content_copy" @click="copyText(detail.key.public_key)">{{ t('common.copy') }}</Btn>
        </div>
        <textarea readonly rows="6" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs" :value="detail.key.public_key"></textarea>
      </div>
      <div v-if="detail.key.cert_pem">
        <div class="mb-1.5 flex items-center justify-between">
          <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.certificate') }}</span>
          <Btn variant="ghost" size="xs" icon="content_copy" @click="copyText(detail.key.cert_pem)">{{ t('common.copy') }}</Btn>
        </div>
        <textarea readonly rows="6" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 font-mono text-xs" :value="detail.key.cert_pem"></textarea>
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="detail.show = false">{{ t('common.close') }}</Btn>
    </template>
  </Modal>

  <!-- Import modal -->
  <Modal v-model="dlg.show" :title="dlg.type === 'pgp' ? t('mail.keys.import_pgp') : t('mail.keys.import_smime')" width="560px">
    <div class="space-y-3">
      <TextField v-model="dlg.label" :label="t('mail.keys.label')" />

      <!-- Source: upload from computer | choose from Files -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.source') }}</span>
        <div class="inline-flex rounded-lg border border-[var(--ll-border)] p-0.5">
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="dlg.source === 'upload' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="dlg.source = 'upload'">{{ t('mail.keys.source_upload') }}</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="dlg.source === 'files' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="dlg.source = 'files'">{{ t('mail.keys.source_files') }}</button>
        </div>
      </div>

      <!-- Upload from computer -->
      <template v-if="dlg.source === 'upload'">
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
      </template>

      <!-- Choose from Files -->
      <div v-else class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.pick_file') }}</span>
        <div class="flex items-center gap-2">
          <Btn variant="outline" size="sm" icon="folder" @click="openPicker">{{ dlg.fileName || t('mail.keys.pick_file') }}</Btn>
          <Btn v-if="dlg.fileId != null" variant="ghost" size="sm" icon="close" @click="dlg.fileId = null; dlg.fileName = ''" />
        </div>
      </div>

      <TextField v-model="dlg.passphrase" :label="t('mail.keys.passphrase')" type="password" autocomplete="new-password" />
    </div>
    <template #footer>
      <Btn variant="ghost" @click="dlg.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="dlg.busy" :disabled="!canImport" @click="doImport">{{ t('mail.keys.add') }}</Btn>
    </template>
  </Modal>

  <!-- Files picker modal -->
  <Modal v-model="picker.show" :title="t('mail.keys.pick_file')" width="560px">
    <TextField v-model="picker.q" :placeholder="t('common.search')" icon="search" inputmode="search" class="mb-3" />
    <div v-if="picker.loading" class="py-6 text-center"><Icon name="progress_activity" :size="24" class="animate-spin text-[var(--ll-muted)]" /></div>
    <div v-else-if="!pickerFiles.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('mail.keys.none') }}</div>
    <div v-else class="max-h-[50vh] divide-y divide-[var(--ll-border)] overflow-y-auto">
      <button v-for="f in pickerFiles" :key="f.id" type="button" class="flex w-full items-center gap-3 py-2.5 text-left hover:bg-black/[0.03] dark:hover:bg-white/5" @click="chooseFile(f)">
        <Icon name="description" :size="20" class="shrink-0 text-[var(--ll-muted)]" />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm">{{ f.name }}</span>
          <span class="block truncate text-xs text-[var(--ll-muted)]">{{ f.mime }}</span>
        </span>
      </button>
    </div>
  </Modal>

  <!-- Generate modal -->
  <Modal v-model="gen.show" :title="gen.type === 'pgp' ? t('mail.keys.generate_pgp') : t('mail.keys.generate_smime')" width="600px">
    <div v-if="gen.unavailable" class="mb-3 flex items-start gap-2 rounded-lg border border-amber-400/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300">
      <Icon name="warning" :size="18" class="mt-0.5 shrink-0" />
      <span>{{ t('mail.keys.toolchain_unavailable') }}</span>
    </div>

    <div class="space-y-4">
      <!-- Type segmented -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.title') }}</span>
        <div class="inline-flex rounded-lg border border-[var(--ll-border)] p-0.5">
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="gen.type === 'pgp' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="gen.type = 'pgp'">PGP</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors" :class="gen.type === 'smime' ? 'bg-primary-500 text-white' : 'text-[var(--ll-muted)]'" @click="gen.type = 'smime'">S/MIME</button>
        </div>
      </div>

      <TextField v-model="gen.label" :label="t('mail.keys.label')" />

      <!-- Identities -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.identities') }}</span>
        <div class="space-y-2">
          <div v-for="(id, i) in gen.identities" :key="i" class="grid grid-cols-1 gap-2 rounded-lg border border-[var(--ll-border)] p-2.5 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <TextField v-model="id.name" :placeholder="t('mail.keys.identity_name')" />
            <TextField v-model="id.email" :placeholder="t('mail.keys.identity_email')" type="email" inputmode="email" :error="id.email.trim() !== '' && !isEmail(id.email) ? ' ' : undefined" />
            <TextField v-model="id.comment" :placeholder="t('mail.keys.identity_comment')" />
            <Btn v-if="gen.identities.length > 1" variant="ghost" size="sm" icon="delete" class="self-center text-red-600" @click="gen.identities.splice(i, 1)" />
          </div>
        </div>
        <Btn variant="ghost" size="sm" icon="add" class="mt-2" @click="gen.identities.push({ name: '', email: '', comment: '' })">{{ t('mail.keys.add_identity') }}</Btn>
      </div>

      <!-- PGP-only: algorithm + key length / curve + signing subkey -->
      <template v-if="gen.type === 'pgp'">
        <Select v-model="gen.algorithm" :label="t('mail.keys.algorithm')" :options="algoOptions" />
        <Select v-if="gen.algorithm === 'rsa'" v-model.number="gen.keyLength" :label="t('mail.keys.key_length')" :options="keyLengthOptions" />
        <Select v-else v-model="gen.curve" :label="t('mail.keys.curve')" :options="curveOptions" />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="gen.signingSubkey" type="checkbox" class="h-4 w-4 accent-primary-500">
          <span>{{ t('mail.keys.signing_subkey') }}</span>
        </label>
      </template>

      <!-- S/MIME: key length -->
      <Select v-else v-model.number="gen.keyLength" :label="t('mail.keys.key_length')" :options="keyLengthOptions" />

      <!-- Expiry -->
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('mail.keys.expiry') }}</span>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="gen.neverExpire" type="checkbox" class="h-4 w-4 accent-primary-500">
          <span>{{ t('mail.keys.expiry_never') }}</span>
        </label>
        <TextField v-if="!gen.neverExpire" v-model="gen.expireYears" :label="t('mail.keys.expire_years')" type="number" inputmode="numeric" class="mt-2" />
      </div>

      <!-- Passphrase -->
      <div>
        <TextField v-model="gen.passphrase" :label="t('mail.keys.passphrase_optional')" type="password" autocomplete="new-password" />
        <p v-if="!gen.passphrase" class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ t('mail.keys.passphrase_none_warn') }}</p>
      </div>
    </div>

    <template #footer>
      <Btn variant="ghost" @click="gen.show = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="gen.busy" :disabled="!canGenerate" @click="doGenerate">{{ t('mail.keys.generate') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useMailStore, type MailKey, type MailKeyParsedIdentity, type MailKeyGenerateBody, type MailKeyImportBody, type MailKeyCurve } from '@spa/stores/mail';
import { useFilesStore, type FileEntry } from '@spa/stores/files';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { ApiError } from '@spa/api/client';

const s = useMailStore();
const files = useFilesStore();
const { success, error } = useToast();
const keys = ref<MailKey[]>([]);
const loading = ref(false);

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const isEmail = (v: string) => EMAIL_RE.test(v.trim());

// --- Import ---------------------------------------------------------------
const dlg = reactive<{
  show: boolean; busy: boolean; type: 'pgp' | 'smime';
  label: string; source: 'upload' | 'files';
  armored: string; p12: string | null; fileId: number | null; fileName: string; passphrase: string;
}>({ show: false, busy: false, type: 'pgp', label: '', source: 'upload', armored: '', p12: null, fileId: null, fileName: '', passphrase: '' });

const canImport = computed(() => {
  if (dlg.label.trim() === '') return false;
  if (dlg.source === 'files') return dlg.fileId != null;
  return dlg.type === 'pgp' ? dlg.armored.trim() !== '' : dlg.p12 !== null;
});

function openImport(type: 'pgp' | 'smime') {
  Object.assign(dlg, { show: true, busy: false, type, label: '', source: 'upload', armored: '', p12: null, fileId: null, fileName: '', passphrase: '' });
}

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
    const body: MailKeyImportBody = {
      type: dlg.type,
      label: dlg.label.trim(),
      source: dlg.source,
      passphrase: dlg.passphrase || null,
    };
    if (dlg.source === 'files') {
      body.file_id = dlg.fileId;
    } else if (dlg.type === 'pgp') {
      body.armored_private_key = dlg.armored;
    } else {
      body.p12_base64 = dlg.p12;
    }
    await s.importKey(body);
    dlg.show = false;
    await load();
    success(t('mail.keys.imported'));
  } catch { error(t('mail.keys.import_failed')); } finally { dlg.busy = false; }
}

// --- Files picker ---------------------------------------------------------
const picker = reactive<{ show: boolean; loading: boolean; q: string }>({ show: false, loading: false, q: '' });

async function openPicker() {
  picker.show = true; picker.q = '';
  if (!files.files.length) {
    picker.loading = true;
    try { await files.load(); } catch { error(t('common.error')); } finally { picker.loading = false; }
  }
}

const pickerFiles = computed(() => {
  const q = picker.q.trim().toLowerCase();
  return q ? files.files.filter((f) => f.name.toLowerCase().includes(q)) : files.files;
});

function chooseFile(f: FileEntry) {
  dlg.fileId = f.id; dlg.fileName = f.name; picker.show = false;
}

// --- Generate -------------------------------------------------------------
const gen = reactive<{
  show: boolean; busy: boolean; unavailable: boolean; type: 'pgp' | 'smime';
  label: string; identities: { name: string; email: string; comment: string }[];
  passphrase: string; neverExpire: boolean; expireYears: string;
  algorithm: 'rsa' | 'ecc'; keyLength: number; curve: MailKeyCurve; signingSubkey: boolean;
}>({
  show: false, busy: false, unavailable: false, type: 'pgp', label: '',
  identities: [{ name: '', email: '', comment: '' }],
  passphrase: '', neverExpire: true, expireYears: '2',
  algorithm: 'ecc', keyLength: 3072, curve: 'ed25519', signingSubkey: false,
});

const algoOptions = computed(() => [
  { title: t('mail.keys.algo_ecc'), value: 'ecc' },
  { title: t('mail.keys.algo_rsa'), value: 'rsa' },
]);
const keyLengthOptions = [
  { title: '2048', value: 2048 },
  { title: '3072', value: 3072 },
  { title: '4096', value: 4096 },
];
const curveOptions: { title: string; value: MailKeyCurve }[] = [
  { title: 'ed25519', value: 'ed25519' },
  { title: 'nistp256', value: 'nistp256' },
  { title: 'nistp384', value: 'nistp384' },
  { title: 'nistp521', value: 'nistp521' },
  { title: 'brainpoolP256r1', value: 'brainpoolP256r1' },
  { title: 'brainpoolP384r1', value: 'brainpoolP384r1' },
  { title: 'brainpoolP512r1', value: 'brainpoolP512r1' },
];

const canGenerate = computed(() => gen.label.trim() !== '' && gen.identities.some((i) => isEmail(i.email)));

function openGenerate() {
  Object.assign(gen, {
    show: true, busy: false, unavailable: false, type: 'pgp', label: '',
    identities: [{ name: '', email: '', comment: '' }],
    passphrase: '', neverExpire: true, expireYears: '2',
    algorithm: 'ecc', keyLength: 3072, curve: 'ed25519', signingSubkey: false,
  });
}

async function doGenerate() {
  if (!canGenerate.value) return;
  gen.busy = true;
  gen.unavailable = false;
  try {
    const identities = gen.identities
      .filter((i) => isEmail(i.email))
      .map((i) => ({ email: i.email.trim(), name: i.name.trim() || null, comment: i.comment.trim() || null }));
    const years = Math.min(100, Math.max(1, Math.trunc(Number(gen.expireYears) || 1)));
    const body: MailKeyGenerateBody = {
      type: gen.type,
      label: gen.label.trim(),
      identities,
      passphrase: gen.passphrase || null,
      expire_years: gen.neverExpire ? null : years,
    };
    if (gen.type === 'pgp') {
      body.algorithm = gen.algorithm;
      if (gen.algorithm === 'rsa') body.key_length = gen.keyLength;
      else body.curve = gen.curve;
      body.signing_subkey = gen.signingSubkey;
    } else {
      body.key_length = gen.keyLength;
    }
    await s.generateKey(body);
    gen.show = false;
    await load();
    success(t('mail.keys.generated'));
  } catch (e) {
    if (e instanceof ApiError && e.status === 501) { gen.unavailable = true; }
    else { error(t('mail.keys.generate_failed')); }
  } finally { gen.busy = false; }
}

// --- List -----------------------------------------------------------------
onMounted(load);
async function load() {
  loading.value = true;
  try { keys.value = (await s.loadKeys()).keys; } catch { error(t('common.error')); } finally { loading.value = false; }
}

async function copyText(text: string) {
  try { await navigator.clipboard.writeText(text); success(t('mail.keys.copied')); } catch { error(t('common.error')); }
}
async function remove(k: MailKey) {
  if (!await confirmAsk(t('mail.keys.delete_confirm'), { danger: true })) return;
  try {
    await s.deleteKey(k.id);
    keys.value = keys.value.filter((x) => x.id !== k.id);
    if (detail.key?.id === k.id) detail.show = false;
  } catch { error(t('common.error')); }
}

// --- Detail modal -----------------------------------------------------------
const detail = reactive<{ show: boolean; key: MailKey | null }>({ show: false, key: null });
function openDetail(k: MailKey) { detail.key = k; detail.show = true; }

/** "Name (Comment) <email>" — whichever parts a parsed identity actually has. */
function formatIdentity(i: MailKeyParsedIdentity): string {
  const parts: string[] = [];
  if (i.name) parts.push(i.name);
  if (i.comment) parts.push(`(${i.comment})`);
  if (i.email) parts.push(`<${i.email}>`);
  return parts.length ? parts.join(' ') : t('mail.keys.identities_none');
}
</script>
