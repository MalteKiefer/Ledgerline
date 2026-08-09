<template>
  <!-- DESTINATIONS -->
  <Card class="mb-4" :body-class="'p-0'">
    <template #header>
      <Icon name="backup" :size="18" class="text-[var(--ll-muted)]" />
      <h2 class="text-sm font-semibold">{{ t('settings.backup_destinations_heading') }}</h2>
    </template>
    <template #actions><Btn variant="soft" size="sm" icon="add" @click="newDest">{{ t('settings.backup_add_destination') }}</Btn></template>
    <div class="divide-y divide-[var(--ll-border)]">
      <div v-for="d in s.destinations" :key="d.id" class="flex items-center gap-3 px-5 py-3">
        <Badge tone="gray">{{ d.driver }}</Badge>
        <span class="flex-1 text-sm font-medium">{{ d.name }}</span>
        <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.04] dark:hover:bg-white/5" :title="t('common.edit')" @click="editDest(d)"><Icon name="edit" :size="17" class="text-[var(--ll-muted)]" /></button>
        <button class="grid h-8 w-8 place-items-center rounded-lg text-red-500 hover:bg-red-500/10" :title="t('common.delete')" @click="delDest(d)"><Icon name="delete" :size="17" /></button>
      </div>
      <div v-if="!s.destinations.length" class="px-5 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('settings.backup_no_destinations') }}</div>
    </div>
  </Card>

  <!-- JOBS -->
  <Card class="mb-4" :body-class="'p-0'">
    <template #header>
      <Icon name="backup" :size="18" class="text-[var(--ll-muted)]" />
      <h2 class="text-sm font-semibold">{{ t('settings.backup_jobs_heading') }}</h2>
    </template>
    <template #actions><Btn variant="soft" size="sm" icon="add" :disabled="!s.destinations.length" @click="newJob">{{ t('settings.backup_add_job') }}</Btn></template>
    <div class="divide-y divide-[var(--ll-border)]">
      <div v-for="j in s.jobs" :key="j.id" class="flex items-center gap-3 px-5 py-3">
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium">{{ j.name }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ jobSubtitle(j) }}</div>
        </div>
        <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.04] dark:hover:bg-white/5 disabled:opacity-50" :disabled="running === j.id" :title="t('settings.backup_run_now')" @click="onRun(j)">
          <Icon :name="running === j.id ? 'refresh' : 'play_arrow'" :size="17" class="text-[var(--ll-muted)]" :class="running === j.id ? 'animate-spin' : ''" />
        </button>
        <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.04] dark:hover:bg-white/5" :title="t('common.edit')" @click="editJob(j)"><Icon name="edit" :size="17" class="text-[var(--ll-muted)]" /></button>
        <button class="grid h-8 w-8 place-items-center rounded-lg text-red-500 hover:bg-red-500/10" :title="t('common.delete')" @click="delJob(j)"><Icon name="delete" :size="17" /></button>
      </div>
      <div v-if="!s.jobs.length" class="px-5 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('settings.backup_no_jobs') }}</div>
    </div>
  </Card>

  <!-- RUNS -->
  <Card :body-class="'p-0'">
    <template #header>
      <Icon name="backup" :size="18" class="text-[var(--ll-muted)]" />
      <h2 class="text-sm font-semibold">{{ t('settings.backup_runs_heading') }}</h2>
    </template>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
          <tr class="border-b border-[var(--ll-border)]">
            <th class="px-4 py-2.5 font-medium">{{ t('common.date') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('common.status') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('common.size') }}</th>
            <th class="px-4 py-2.5 text-right font-medium"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in s.runs" :key="item.id" class="border-b border-[var(--ll-border)] last:border-0 align-top">
            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ item.startedHuman || fmtIso(item.startedIso) || '—' }}</td>
            <td class="px-4 py-2.5"><Badge :tone="statusColor(item.status)">{{ item.status }}</Badge></td>
            <td class="px-4 py-2.5">{{ item.size || '—' }}</td>
            <td class="px-4 py-2.5">
              <div class="flex flex-col items-end gap-1.5">
                <Btn v-if="item.cancellable" variant="soft" size="xs" icon="close" @click="onCancel(item)">{{ t('settings.backup_cancel') }}</Btn>
                <span v-else-if="item.cancelling" class="text-xs text-[var(--ll-muted)]">{{ t('settings.backup_cancelling') }}</span>
                <div v-for="a in (item.archives || [])" :key="a.source" class="flex items-center gap-1">
                  <Badge tone="gray">{{ sourceLabel(a.source) }}</Badge>
                  <Btn tag="a" variant="ghost" size="xs" icon="download" :href="s.downloadRunUrl(item.id, a.source)" :title="t('settings.backup_download')" />
                  <Btn variant="ghost" size="xs" icon="verified" :title="t('settings.backup_verify')" @click="onVerify(item, a)" />
                  <Btn v-if="a.encrypted" variant="ghost" size="xs" icon="security" :title="t('settings.backup_decrypt')" @click="onDecrypt(item, a)" />
                  <Btn v-if="a.restorable" variant="ghost" size="xs" icon="refresh" :title="t('settings.backup_restore')" @click="onRestore(item, a)" />
                </div>
              </div>
            </td>
          </tr>
          <tr v-if="!s.runs.length"><td colspan="4" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('settings.backup_no_runs') }}</td></tr>
        </tbody>
      </table>
    </div>
  </Card>

  <!-- Destination dialog -->
  <Modal v-model="destDialog" :title="editingDest ? t('common.edit') : t('settings.backup_add_destination')" width="620px">
    <div class="space-y-4">
      <TextField v-model="destForm.name" :label="t('settings.backup_name')" :error="destErr.name?.[0]" />
      <Select v-model="destForm.driver" :label="t('settings.backup_driver')" :options="driverItems" />

      <template v-if="destForm.driver === 's3' || destForm.driver === 'b2'">
        <TextField v-model="destForm.bucket" :label="t('settings.backup_bucket')" />
        <TextField v-model="destForm.region" :label="t('settings.backup_region')" />
        <TextField v-model="destForm.key" :label="t('settings.backup_key')" autocomplete="off" />
        <TextField v-model="destForm.secret" :label="t('settings.backup_secret')" type="password" autocomplete="off" :placeholder="editingDest ? '••••••••' : ''" :hint="editingDest ? t('settings.notify_secret_keep_hint') : undefined" />
        <TextField v-model="destForm.endpoint" :label="t('settings.backup_endpoint')" />
        <label class="flex items-center gap-3">
          <span class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
            <input v-model="destForm.use_path_style" type="checkbox" class="peer sr-only">
            <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
            <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
          </span>
          <span class="text-sm font-medium">{{ t('settings.backup_use_path_style') }}</span>
        </label>
        <TextField v-model="destForm.path" :label="t('settings.backup_path')" :hint="t('settings.backup_path_hint')" />
      </template>

      <template v-else-if="destForm.driver === 'sftp'">
        <TextField v-model="destForm.host" :label="t('settings.backup_host')" />
        <TextField v-model="destForm.port" :label="t('settings.backup_port')" type="number" />
        <TextField v-model="destForm.username" :label="t('settings.backup_username')" autocomplete="off" />
        <TextField v-model="destForm.password" :label="t('settings.backup_password')" type="password" autocomplete="off" :placeholder="editingDest ? '••••••••' : ''" :hint="editingDest ? t('settings.notify_secret_keep_hint') : undefined" />
        <TextField v-model="destForm.path" :label="t('settings.backup_path')" :hint="t('settings.backup_path_hint')" />
      </template>

      <template v-else-if="destForm.driver === 'webdav'">
        <TextField v-model="destForm.base_uri" :label="t('settings.backup_base_uri')" />
        <TextField v-model="destForm.username" :label="t('settings.backup_username')" autocomplete="off" />
        <TextField v-model="destForm.password" :label="t('settings.backup_password')" type="password" autocomplete="off" :placeholder="editingDest ? '••••••••' : ''" :hint="editingDest ? t('settings.notify_secret_keep_hint') : undefined" />
        <TextField v-model="destForm.path" :label="t('settings.backup_path')" :hint="t('settings.backup_path_hint')" />
      </template>
    </div>
    <template #footer>
      <Btn variant="ghost" icon="refresh" :loading="testing" @click="testConn">{{ t('settings.backup_test') }}</Btn>
      <div class="flex-1" />
      <Btn variant="ghost" @click="destDialog = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="savingDest" @click="saveDest">{{ t('settings.backup_save') }}</Btn>
    </template>
  </Modal>

  <!-- Job dialog -->
  <Modal v-model="jobDialog" :title="editingJob ? t('common.edit') : t('settings.backup_add_job')" width="620px">
    <div class="space-y-4">
      <TextField v-model="jobForm.name" :label="t('settings.backup_name')" :error="jobErr.name?.[0]" />
      <Select v-model="jobForm.backup_destination_id" :label="t('settings.backup_destination')" :options="destItems" />

      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.backup_sources') }}</span>
        <div class="flex flex-wrap gap-1.5">
          <label
            v-for="src in sourceItems" :key="src.value"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
            :class="jobForm.sources.includes(src.value) ? 'border-primary-500/30 bg-primary-500/10 text-primary-600 dark:text-primary-300' : 'border-[var(--ll-border)] text-[var(--ll-muted)] hover:bg-black/[0.03] dark:hover:bg-white/5'"
          >
            <input v-model="jobForm.sources" type="checkbox" :value="src.value" class="hidden">
            {{ src.title }}
          </label>
        </div>
        <span v-if="jobErr.sources?.[0]" class="mt-1 block text-xs text-red-500">{{ jobErr.sources[0] }}</span>
      </div>

      <Select v-model="jobForm.mode" :label="t('settings.backup_mode')" :options="modeItems" />
      <p class="text-xs text-[var(--ll-muted)]">{{ t('settings.backup_mode_hint') }}</p>
      <TextField v-model="jobForm.cron" :label="t('settings.backup_cron')" :hint="t('settings.backup_cron_hint')" :error="jobErr.cron?.[0]" />

      <div>
        <div class="mb-1 mt-2 text-xs text-[var(--ll-muted)]">{{ t('settings.backup_retention_gfs') }}</div>
        <div class="flex gap-3">
          <TextField v-model="jobForm.keep_daily" :label="t('settings.backup_keep_daily')" type="number" :error="jobErr.keep_daily?.[0]" />
          <TextField v-model="jobForm.keep_weekly" :label="t('settings.backup_keep_weekly')" type="number" />
          <TextField v-model="jobForm.keep_monthly" :label="t('settings.backup_keep_monthly')" type="number" />
        </div>
      </div>

      <label class="flex items-center gap-3">
        <span class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
          <input v-model="jobForm.encrypt" type="checkbox" class="peer sr-only">
          <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
        </span>
        <span class="text-sm font-medium">{{ t('settings.backup_encrypt') }}</span>
      </label>
      <TextField
        v-if="jobForm.encrypt" v-model="jobForm.passphrase" :label="t('settings.backup_passphrase')" type="password" autocomplete="new-password"
        :placeholder="editingJob ? '••••••••' : ''" :hint="editingJob ? t('settings.notify_secret_keep_hint') : undefined" :error="jobErr.passphrase?.[0]"
      />
      <label class="flex items-center gap-3">
        <span class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
          <input v-model="jobForm.notify" type="checkbox" class="peer sr-only">
          <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
        </span>
        <span class="text-sm font-medium">{{ t('settings.backup_notify') }}</span>
      </label>
    </div>
    <template #footer>
      <div class="flex-1" />
      <Btn variant="ghost" @click="jobDialog = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="savingJob" @click="saveJob">{{ t('settings.backup_save') }}</Btn>
    </template>
  </Modal>

  <!-- Passphrase dialog (verify / decrypt) -->
  <Modal v-model="ppDialog" :title="ppMode === 'decrypt' ? t('settings.backup_decrypt') : t('settings.backup_verify')" width="440px">
    <TextField v-model="ppValue" :label="t('settings.backup_passphrase')" type="password" autocomplete="off" />
    <template #footer>
      <div class="flex-1" />
      <Btn variant="ghost" @click="ppDialog = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="ppBusy" @click="ppConfirm">{{ ppMode === 'decrypt' ? t('settings.backup_decrypt') : t('settings.backup_verify') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useSettingsStore, type BackupJob, type BackupDestination, type BackupRun, type BackupArchive } from '@spa/stores/settings';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';

const s = useSettingsStore();
const { success, error } = useToast();

const SOURCES = ['database', 'invoices', 'files'];
const running = ref<number | null>(null);

const headers = [
  { title: t('common.date'), key: 'startedHuman' },
  { title: t('common.status'), key: 'status' },
  { title: t('common.size'), key: 'size' },
  { title: '', key: 'act', sortable: false, align: 'end' as const },
];

const driverItems = [
  { title: 'S3', value: 's3' },
  { title: 'Backblaze B2', value: 'b2' },
  { title: 'SFTP', value: 'sftp' },
  { title: 'WebDAV', value: 'webdav' },
];
const sourceItems = computed(() => SOURCES.map((v) => ({ title: t('settings.backup_source_' + v), value: v })));
const modeItems = computed(() => [
  { title: t('settings.backup_mode_full'), value: 'full' },
  { title: t('settings.backup_mode_incremental'), value: 'incremental' },
]);
const destItems = computed(() => s.destinations.map((d) => ({ title: d.name, value: d.id })));

onMounted(() => s.loadBackup());

function statusColor(st: string) { return st === 'success' ? 'success' : st === 'failed' ? 'error' : st === 'running' ? 'info' : undefined; }
function fmtIso(v: string | null | undefined): string { if (!v) return ''; const d = new Date(v); return isNaN(d.getTime()) ? '' : d.toLocaleString(document.documentElement.lang || 'de'); }
function sourceLabel(src: string) { return t('settings.backup_source_' + src); }
function jobSubtitle(j: BackupJob) { return (j.sources || []).map(sourceLabel).join(', ') + (j.encrypt ? ' · 🔒' : ''); }
function saveBlob(blob: Blob, name: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}

/* ---- Destinations ---- */
const destDialog = ref(false);
const savingDest = ref(false);
const testing = ref(false);
const editingDest = ref<BackupDestination | null>(null);
const destErr = reactive<Record<string, string[] | undefined>>({});
const destDefaults = () => ({
  name: '', driver: 's3',
  bucket: '', region: '', key: '', secret: '', endpoint: '', use_path_style: false,
  host: '', port: 22, username: '', password: '', base_uri: '', path: '',
});
const destForm = reactive(destDefaults());

function clearDestErr() { Object.keys(destErr).forEach((k) => (destErr[k] = undefined)); }
function newDest() { editingDest.value = null; Object.assign(destForm, destDefaults()); clearDestErr(); destDialog.value = true; }
// Credentials/config are never returned by the API — on edit only name + driver prefill; config must be re-entered.
function editDest(d: BackupDestination) { editingDest.value = d; Object.assign(destForm, destDefaults(), { name: d.name, driver: d.driver }); clearDestErr(); destDialog.value = true; }
function destBody(): Record<string, unknown> {
  const b: Record<string, unknown> = { name: destForm.name, driver: destForm.driver };
  if (destForm.driver === 's3' || destForm.driver === 'b2') {
    Object.assign(b, { bucket: destForm.bucket, region: destForm.region, key: destForm.key, secret: destForm.secret, endpoint: destForm.endpoint, use_path_style: destForm.use_path_style, path: destForm.path });
  } else if (destForm.driver === 'sftp') {
    Object.assign(b, { host: destForm.host, port: destForm.port, username: destForm.username, password: destForm.password, path: destForm.path });
  } else if (destForm.driver === 'webdav') {
    Object.assign(b, { base_uri: destForm.base_uri, username: destForm.username, password: destForm.password, path: destForm.path });
  }
  return b;
}
async function testConn() {
  testing.value = true;
  try {
    const body = destBody();
    if (editingDest.value) body.destination_id = editingDest.value.id;
    const r = await s.testDestination(body);
    if (r.ok) success(t('flash.backup_test_ok'));
    else error(t('flash.backup_test_failed', { error: r.detail || '' }));
  } catch (e) {
    const msg = e instanceof ApiError && e.fields ? Object.values(e.fields).flat().join(', ') : '';
    error(t('flash.backup_test_failed', { error: msg }));
  } finally { testing.value = false; }
}
async function saveDest() {
  savingDest.value = true; clearDestErr();
  try {
    await s.saveDestination(destBody(), editingDest.value?.id);
    destDialog.value = false; await s.loadBackup(); success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.fields) Object.assign(destErr, e.fields);
    else error(t('common.error'));
  } finally { savingDest.value = false; }
}
async function delDest(d: BackupDestination) {
  if (!confirm(t('settings.backup_delete_confirm'))) return;
  try { await s.deleteDestination(d.id); await s.loadBackup(); } catch { error(t('common.error')); }
}

/* ---- Jobs ---- */
const jobDialog = ref(false);
const savingJob = ref(false);
const editingJob = ref<BackupJob | null>(null);
const jobErr = reactive<Record<string, string[] | undefined>>({});
const jobChannels = ref<string[]>([]);
const jobDefaults = () => ({
  name: '', backup_destination_id: s.destinations[0]?.id ?? null as number | null,
  sources: ['database'] as string[], mode: 'full', cron: '0 3 * * *',
  keep_daily: 7, keep_weekly: 0, keep_monthly: 0,
  encrypt: false, passphrase: '', notify: false, enabled: true,
});
const jobForm = reactive(jobDefaults());

function clearJobErr() { Object.keys(jobErr).forEach((k) => (jobErr[k] = undefined)); }
function newJob() { editingJob.value = null; jobChannels.value = []; Object.assign(jobForm, jobDefaults()); clearJobErr(); jobDialog.value = true; }
function editJob(j: BackupJob) {
  editingJob.value = j;
  jobChannels.value = j.notify_channels || [];
  Object.assign(jobForm, {
    name: j.name,
    backup_destination_id: j.destination_id,
    sources: [...(j.sources || [])],
    mode: j.mode || 'full',
    cron: j.cron || '0 3 * * *',
    keep_daily: j.keep_daily ?? 7,
    keep_weekly: j.keep_weekly ?? 0,
    keep_monthly: j.keep_monthly ?? 0,
    encrypt: j.encrypt,
    passphrase: '',
    notify: jobChannels.value.length > 0,
    enabled: j.enabled ?? true,
  });
  clearJobErr(); jobDialog.value = true;
}
function jobBody(): Record<string, unknown> {
  const notify_channels = jobForm.notify ? (jobChannels.value.length ? jobChannels.value : ['desktop']) : [];
  const b: Record<string, unknown> = {
    name: jobForm.name,
    backup_destination_id: jobForm.backup_destination_id,
    sources: jobForm.sources,
    mode: jobForm.mode,
    cron: jobForm.cron,
    keep_daily: jobForm.keep_daily,
    keep_weekly: jobForm.keep_weekly,
    keep_monthly: jobForm.keep_monthly,
    encrypt: jobForm.encrypt,
    notify_channels,
    enabled: jobForm.enabled,
  };
  if (jobForm.passphrase) b.passphrase = jobForm.passphrase;
  return b;
}
async function saveJob() {
  savingJob.value = true; clearJobErr();
  try {
    await s.saveJob(jobBody(), editingJob.value?.id);
    jobDialog.value = false; await s.loadBackup(); success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.fields) Object.assign(jobErr, e.fields);
    else error(t('common.error'));
  } finally { savingJob.value = false; }
}
async function onRun(j: BackupJob) {
  running.value = j.id;
  try { await s.runJob(j.id); success(t('flash.backup_queued')); await s.loadBackup(); }
  catch { error(t('common.error')); }
  finally { running.value = null; }
}
async function delJob(j: BackupJob) {
  if (!confirm(t('settings.backup_delete_confirm'))) return;
  try { await s.deleteJob(j.id); await s.loadBackup(); } catch { error(t('common.error')); }
}

/* ---- Runs ---- */
const ppDialog = ref(false);
const ppMode = ref<'verify' | 'decrypt'>('verify');
const ppValue = ref('');
const ppBusy = ref(false);
const ppTarget = ref<{ run: BackupRun; archive: BackupArchive } | null>(null);

async function onCancel(run: BackupRun) {
  try { await s.cancelRun(run.id); await s.loadBackup(); } catch { error(t('common.error')); }
}
function onVerify(run: BackupRun, archive: BackupArchive) {
  if (archive.encrypted) { ppMode.value = 'verify'; ppValue.value = ''; ppTarget.value = { run, archive }; ppDialog.value = true; }
  else doVerify(run, archive, '');
}
function onDecrypt(run: BackupRun, archive: BackupArchive) {
  ppMode.value = 'decrypt'; ppValue.value = ''; ppTarget.value = { run, archive }; ppDialog.value = true;
}
async function ppConfirm() {
  const target = ppTarget.value;
  if (!target) { ppDialog.value = false; return; }
  ppBusy.value = true;
  try {
    if (ppMode.value === 'verify') await doVerify(target.run, target.archive, ppValue.value);
    else await doDecrypt(target.run, target.archive, ppValue.value);
    ppDialog.value = false;
  } finally { ppBusy.value = false; }
}
async function doVerify(run: BackupRun, archive: BackupArchive, passphrase: string) {
  try {
    const r = await s.verifyRun(run.id, archive.source, passphrase);
    if (r.ok) success(t('settings.backup_verify_ok'));
    else error(r.message || t('common.error'));
  } catch { error(t('common.error')); }
}
async function doDecrypt(run: BackupRun, archive: BackupArchive, passphrase: string) {
  try {
    const blob = await s.decryptRun(run.id, archive.source, passphrase);
    saveBlob(blob, `${archive.source}-${run.id}`);
  } catch { error(t('settings.backup_decrypt_failed')); }
}
async function onRestore(run: BackupRun, archive: BackupArchive) {
  if (!confirm(t('settings.backup_restore_confirm', { source: sourceLabel(archive.source) }))) return;
  try {
    const r = await s.restoreRun(run.id, archive.source);
    success(t('settings.backup_restore_done', { count: String(r.files ?? 0) }));
    await s.loadBackup();
  } catch (e) {
    const msg = e instanceof ApiError && e.body && typeof e.body === 'object' ? String((e.body as { message?: string }).message ?? '') : '';
    error(t('settings.backup_restore_failed', { error: msg }));
  }
}
</script>
