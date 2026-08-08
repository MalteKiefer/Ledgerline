<template>
  <!-- DESTINATIONS -->
  <v-card rounded="xl" border flat class="mb-4">
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('settings.backup_destinations_heading') }}</v-toolbar-title>
      <v-spacer />
      <v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="newDest">{{ t('settings.backup_add_destination') }}</v-btn>
    </v-toolbar>
    <v-divider />
    <v-list>
      <v-list-item v-for="d in s.destinations" :key="d.id" :title="d.name">
        <template #prepend><v-chip size="small" variant="tonal" class="me-3">{{ d.driver }}</v-chip></template>
        <template #append>
          <v-btn variant="text" size="small" :icon="mdiPencil" :title="t('common.edit')" @click="editDest(d)" />
          <v-btn variant="text" size="small" color="error" :icon="mdiDelete" :title="t('common.delete')" @click="delDest(d)" />
        </template>
      </v-list-item>
      <v-list-item v-if="!s.destinations.length" :title="t('settings.backup_no_destinations')" class="text-medium-emphasis" />
    </v-list>
  </v-card>

  <!-- JOBS -->
  <v-card rounded="xl" border flat class="mb-4">
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('settings.backup_jobs_heading') }}</v-toolbar-title>
      <v-spacer />
      <v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" :disabled="!s.destinations.length" @click="newJob">{{ t('settings.backup_add_job') }}</v-btn>
    </v-toolbar>
    <v-divider />
    <v-list>
      <v-list-item v-for="j in s.jobs" :key="j.id" :title="j.name" :subtitle="jobSubtitle(j)">
        <template #append>
          <v-btn variant="text" size="small" :icon="mdiPlay" :loading="running === j.id" :title="t('settings.backup_run_now')" @click="onRun(j)" />
          <v-btn variant="text" size="small" :icon="mdiPencil" :title="t('common.edit')" @click="editJob(j)" />
          <v-btn variant="text" size="small" color="error" :icon="mdiDelete" :title="t('common.delete')" @click="delJob(j)" />
        </template>
      </v-list-item>
      <v-list-item v-if="!s.jobs.length" :title="t('settings.backup_no_jobs')" class="text-medium-emphasis" />
    </v-list>
  </v-card>

  <!-- RUNS -->
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface"><v-toolbar-title>{{ t('settings.backup_runs_heading') }}</v-toolbar-title></v-toolbar>
    <v-divider />
    <v-data-table :headers="headers" :items="s.runs" density="compact" :items-per-page="10" :no-data-text="t('settings.backup_no_runs')">
      <template #[`item.startedHuman`]="{ item }"><span class="ll-mono text-caption">{{ item.startedHuman || fmtIso(item.startedIso) || '—' }}</span></template>
      <template #[`item.status`]="{ item }">
        <v-chip size="small" variant="tonal" :color="statusColor(item.status)">{{ item.status }}</v-chip>
      </template>
      <template #[`item.size`]="{ item }">{{ item.size || '—' }}</template>
      <template #[`item.act`]="{ item }">
        <div class="d-flex flex-column ga-1 py-1" style="align-items:flex-end">
          <v-btn v-if="item.cancellable" variant="tonal" size="x-small" color="warning" :prepend-icon="mdiCancel" @click="onCancel(item)">{{ t('settings.backup_cancel') }}</v-btn>
          <span v-else-if="item.cancelling" class="text-caption text-medium-emphasis">{{ t('settings.backup_cancelling') }}</span>
          <div v-for="a in (item.archives || [])" :key="a.source" class="d-flex align-center ga-1">
            <v-chip size="x-small" variant="tonal">{{ sourceLabel(a.source) }}</v-chip>
            <v-btn variant="text" size="x-small" :icon="mdiDownload" :href="s.downloadRunUrl(item.id, a.source)" :title="t('settings.backup_download')" />
            <v-btn variant="text" size="x-small" :icon="mdiCheckDecagram" :title="t('settings.backup_verify')" @click="onVerify(item, a)" />
            <v-btn v-if="a.encrypted" variant="text" size="x-small" :icon="mdiLockOpenVariant" :title="t('settings.backup_decrypt')" @click="onDecrypt(item, a)" />
            <v-btn v-if="a.restorable" variant="text" size="x-small" color="warning" :icon="mdiRestore" :title="t('settings.backup_restore')" @click="onRestore(item, a)" />
          </div>
        </div>
      </template>
    </v-data-table>
  </v-card>

  <!-- Destination dialog -->
  <v-dialog v-model="destDialog" max-width="620">
    <v-card rounded="xl">
      <v-card-title>{{ editingDest ? t('common.edit') : t('settings.backup_add_destination') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="destForm.name" :label="t('settings.backup_name')" variant="outlined" density="comfortable" :error-messages="destErr.name" />
        <v-select v-model="destForm.driver" :items="driverItems" :label="t('settings.backup_driver')" variant="outlined" density="comfortable" />

        <template v-if="destForm.driver === 's3' || destForm.driver === 'b2'">
          <v-text-field v-model="destForm.bucket" :label="t('settings.backup_bucket')" variant="outlined" density="comfortable" />
          <v-text-field v-model="destForm.region" :label="t('settings.backup_region')" variant="outlined" density="comfortable" />
          <v-text-field v-model="destForm.key" :label="t('settings.backup_key')" variant="outlined" density="comfortable" autocomplete="off" />
          <v-text-field v-model="destForm.secret" :label="t('settings.backup_secret')" type="password" variant="outlined" density="comfortable" autocomplete="off" :placeholder="editingDest ? '••••••••' : ''" :hint="editingDest ? t('settings.notify_secret_keep_hint') : ''" persistent-hint />
          <v-text-field v-model="destForm.endpoint" :label="t('settings.backup_endpoint')" variant="outlined" density="comfortable" />
          <v-switch v-model="destForm.use_path_style" :label="t('settings.backup_use_path_style')" color="primary" density="comfortable" hide-details />
          <v-text-field v-model="destForm.path" :label="t('settings.backup_path')" variant="outlined" density="comfortable" :hint="t('settings.backup_path_hint')" persistent-hint />
        </template>

        <template v-else-if="destForm.driver === 'sftp'">
          <v-text-field v-model="destForm.host" :label="t('settings.backup_host')" variant="outlined" density="comfortable" />
          <v-text-field v-model.number="destForm.port" :label="t('settings.backup_port')" type="number" variant="outlined" density="comfortable" />
          <v-text-field v-model="destForm.username" :label="t('settings.backup_username')" variant="outlined" density="comfortable" autocomplete="off" />
          <v-text-field v-model="destForm.password" :label="t('settings.backup_password')" type="password" variant="outlined" density="comfortable" autocomplete="off" :placeholder="editingDest ? '••••••••' : ''" :hint="editingDest ? t('settings.notify_secret_keep_hint') : ''" persistent-hint />
          <v-text-field v-model="destForm.path" :label="t('settings.backup_path')" variant="outlined" density="comfortable" :hint="t('settings.backup_path_hint')" persistent-hint />
        </template>

        <template v-else-if="destForm.driver === 'webdav'">
          <v-text-field v-model="destForm.base_uri" :label="t('settings.backup_base_uri')" variant="outlined" density="comfortable" />
          <v-text-field v-model="destForm.username" :label="t('settings.backup_username')" variant="outlined" density="comfortable" autocomplete="off" />
          <v-text-field v-model="destForm.password" :label="t('settings.backup_password')" type="password" variant="outlined" density="comfortable" autocomplete="off" :placeholder="editingDest ? '••••••••' : ''" :hint="editingDest ? t('settings.notify_secret_keep_hint') : ''" persistent-hint />
          <v-text-field v-model="destForm.path" :label="t('settings.backup_path')" variant="outlined" density="comfortable" :hint="t('settings.backup_path_hint')" persistent-hint />
        </template>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" :prepend-icon="mdiConnection" :loading="testing" @click="testConn">{{ t('settings.backup_test') }}</v-btn>
        <v-spacer />
        <v-btn variant="text" @click="destDialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="savingDest" @click="saveDest">{{ t('settings.backup_save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Job dialog -->
  <v-dialog v-model="jobDialog" max-width="620">
    <v-card rounded="xl">
      <v-card-title>{{ editingJob ? t('common.edit') : t('settings.backup_add_job') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="jobForm.name" :label="t('settings.backup_name')" variant="outlined" density="comfortable" :error-messages="jobErr.name" />
        <v-select v-model="jobForm.backup_destination_id" :items="destItems" :label="t('settings.backup_destination')" variant="outlined" density="comfortable" :error-messages="jobErr.backup_destination_id" />
        <v-select v-model="jobForm.sources" :items="sourceItems" :label="t('settings.backup_sources')" multiple chips variant="outlined" density="comfortable" :error-messages="jobErr.sources" />
        <v-select v-model="jobForm.mode" :items="modeItems" :label="t('settings.backup_mode')" variant="outlined" density="comfortable" :hint="t('settings.backup_mode_hint')" persistent-hint />
        <v-text-field v-model="jobForm.cron" :label="t('settings.backup_cron')" variant="outlined" density="comfortable" :hint="t('settings.backup_cron_hint')" persistent-hint :error-messages="jobErr.cron" />
        <div class="text-caption text-medium-emphasis mt-4 mb-1">{{ t('settings.backup_retention_gfs') }}</div>
        <div class="d-flex ga-3">
          <v-text-field v-model.number="jobForm.keep_daily" :label="t('settings.backup_keep_daily')" type="number" min="0" variant="outlined" density="comfortable" :error-messages="jobErr.keep_daily" />
          <v-text-field v-model.number="jobForm.keep_weekly" :label="t('settings.backup_keep_weekly')" type="number" min="0" variant="outlined" density="comfortable" />
          <v-text-field v-model.number="jobForm.keep_monthly" :label="t('settings.backup_keep_monthly')" type="number" min="0" variant="outlined" density="comfortable" />
        </div>
        <v-switch v-model="jobForm.encrypt" :label="t('settings.backup_encrypt')" color="primary" density="comfortable" hide-details />
        <v-text-field v-if="jobForm.encrypt" v-model="jobForm.passphrase" :label="t('settings.backup_passphrase')" type="password" variant="outlined" density="comfortable" autocomplete="new-password" :placeholder="editingJob ? '••••••••' : ''" :hint="editingJob ? t('settings.notify_secret_keep_hint') : ''" persistent-hint :error-messages="jobErr.passphrase" />
        <v-switch v-model="jobForm.notify" :label="t('settings.backup_notify')" color="primary" density="comfortable" hide-details />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="jobDialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="savingJob" @click="saveJob">{{ t('settings.backup_save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Passphrase dialog (verify / decrypt) -->
  <v-dialog v-model="ppDialog" max-width="440">
    <v-card rounded="xl">
      <v-card-title>{{ ppMode === 'decrypt' ? t('settings.backup_decrypt') : t('settings.backup_verify') }}</v-card-title>
      <v-card-text>
        <v-text-field v-model="ppValue" :label="t('settings.backup_passphrase')" type="password" variant="outlined" density="comfortable" autocomplete="off" autofocus />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="ppDialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="ppBusy" @click="ppConfirm">{{ ppMode === 'decrypt' ? t('settings.backup_decrypt') : t('settings.backup_verify') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlay, mdiDelete, mdiDownload, mdiCheckDecagram, mdiLockOpenVariant, mdiRestore, mdiCancel, mdiPencil, mdiConnection, mdiPlus } from '@mdi/js';
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
