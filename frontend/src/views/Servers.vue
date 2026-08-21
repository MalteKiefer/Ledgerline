<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold">{{ t('servers.title') }}</h1>
        <p class="mt-0.5 text-sm text-[var(--ll-muted)]">{{ t('servers.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Btn variant="ghost" icon="refresh" :disabled="!s.servers.length || busy" @click="doRefreshAll">{{ t('servers.refresh_all') }}</Btn>
        <Btn variant="solid" icon="add" @click="openCreate">{{ t('servers.add') }}</Btn>
      </div>
    </div>

    <!-- List, grouped. A server with no group falls into one bucket at the end. -->
    <div v-if="!s.servers.length" class="rounded-xl border border-[var(--ll-border)] p-10 text-center text-[var(--ll-muted)]">
      {{ t('servers.none') }}
    </div>

    <div v-for="grp in grouped" :key="grp.name" class="space-y-3">
      <h2 v-if="grouped.length > 1 || grp.name" class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">
        {{ grp.name || t('servers.group_other') }}
      </h2>
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <Card v-for="srv in grp.items" :key="srv.id" :body-class="'p-4'">
          <!-- Title row: status dot, name, host -->
          <div class="flex items-start justify-between gap-2">
            <button class="min-w-0 flex-1 text-left" @click="openDetail(srv)">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dotClass(srv)" :title="statusLabel(srv)" />
                <span class="truncate font-semibold">{{ srv.name }}</span>
                <Icon v-if="srv.restricted_key" name="lock" :size="14" class="shrink-0 text-[var(--ll-muted)]" :title="t('servers.restricted_key')" />
              </div>
              <div class="mt-0.5 truncate font-mono text-xs text-[var(--ll-muted)]">{{ srv.username }}@{{ srv.host }}<span v-if="srv.port !== 22">:{{ srv.port }}</span></div>
            </button>
            <div class="flex shrink-0 items-center gap-1">
              <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" :title="t('servers.refresh')" @click="doRefresh(srv)" />
              <Btn variant="ghost" size="sm" icon="edit" :title="t('servers.edit')" @click="openEdit(srv)" />
            </div>
          </div>

          <!-- Unreachable: say why instead of showing a stale snapshot as if current -->
          <p v-if="srv.status && !srv.status.ok" class="mt-3 rounded-lg bg-red-500/10 px-2.5 py-2 text-xs text-red-600 dark:text-red-400">
            {{ errorText(srv.status.error) }}
          </p>
          <p v-else-if="!srv.status" class="mt-3 text-xs text-[var(--ll-muted)]">{{ t('servers.status_unknown') }}</p>

          <template v-if="srv.facts">
            <div class="mt-3 space-y-1 text-xs">
              <div class="flex justify-between gap-2">
                <span class="text-[var(--ll-muted)]">{{ t('servers.os') }}</span>
                <span class="truncate text-right">{{ srv.facts.os.name || '—' }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-[var(--ll-muted)]">{{ t('servers.kernel') }}</span>
                <span class="truncate text-right font-mono">{{ srv.facts.kernel || '—' }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-[var(--ll-muted)]">{{ t('servers.uptime') }}</span>
                <span>{{ formatUptime(srv.facts.uptime_s) }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-[var(--ll-muted)]">{{ t('servers.load') }}</span>
                <span class="font-mono tabular-nums">{{ srv.facts.load.map((l) => l.toFixed(2)).join('  ') || '—' }}</span>
              </div>
            </div>

            <!-- Memory + fullest filesystem as meters: a number alone does not
                 convey "nearly full" at a glance. -->
            <Meter class="mt-3" :label="t('servers.memory')" :pct="srv.facts.mem.used_pct" :note="memoryNote(srv.facts)" />
            <Meter v-if="fullestDisk(srv.facts)" class="mt-2" :label="fullestDisk(srv.facts)!.mount" :pct="fullestDisk(srv.facts)!.used_pct" :note="diskNote(fullestDisk(srv.facts)!)" />

            <div class="mt-3 flex flex-wrap gap-1.5">
              <Badge v-if="srv.facts.reboot_required" tone="warning">{{ t('servers.reboot_required') }}</Badge>
              <Badge v-if="srv.facts.failed_units.length" tone="error">{{ srv.facts.failed_units.length }} × {{ t('servers.failed_units') }}</Badge>
              <Badge v-if="srv.facts.updates" tone="info">{{ srv.facts.updates }} {{ t('servers.updates') }}</Badge>
              <Badge v-if="srv.facts.containers.length" tone="gray">{{ srv.facts.containers.length }} × {{ t('servers.containers') }}</Badge>
            </div>

            <p class="mt-3 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.checked') }}: {{ fmtDateTime(srv.status?.collected_at ?? '') }}</p>
          </template>
        </Card>
      </div>
    </div>

    <!-- Detail -->
    <Modal v-model="detailOpen" :title="detail?.name ?? ''" width="820px">
      <div v-if="detail" class="space-y-5">
        <div class="flex flex-wrap items-center gap-2">
          <Badge :tone="detail.status?.ok ? 'success' : detail.status ? 'error' : 'gray'">{{ statusLabel(detail) }}</Badge>
          <span class="font-mono text-xs text-[var(--ll-muted)]">{{ detail.username }}@{{ detail.host }}:{{ detail.port }}</span>
          <span class="flex-1" />
          <Btn variant="ghost" size="sm" icon="network_check" :disabled="testing" @click="retest">{{ testing ? t('servers.testing') : t('servers.test') }}</Btn>
          <Btn variant="ghost" size="sm" icon="refresh" @click="doRefresh(detail)">{{ t('servers.refresh') }}</Btn>
        </div>

        <p v-if="retestResult" class="rounded-lg px-2.5 py-2 text-xs" :class="retestResult.ok ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'">
          {{ retestResult.ok ? t('servers.test_ok') : errorText(retestResult.error) }}
        </p>

        <div v-if="detail.facts" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Card :body-class="'p-3'">
            <dl class="space-y-1 text-xs">
              <Row :label="t('servers.os')" :value="detail.facts.os.name" />
              <Row :label="t('servers.kernel')" :value="detail.facts.kernel" />
              <Row :label="t('servers.cpu')" :value="cpuText(detail.facts)" />
              <Row :label="t('servers.uptime')" :value="formatUptime(detail.facts.uptime_s)" />
              <Row :label="t('servers.load')" :value="detail.facts.load.map((l) => l.toFixed(2)).join('  ')" />
              <Row :label="t('servers.updates')" :value="detail.facts.updates === null ? t('servers.updates_unknown') : String(detail.facts.updates)" />
            </dl>
          </Card>
          <Card :body-class="'p-3'">
            <Meter :label="t('servers.memory')" :pct="detail.facts.mem.used_pct" :note="memoryNote(detail.facts)" />
            <Meter v-if="detail.facts.mem.swap_total_kb" class="mt-2" :label="t('servers.swap')" :pct="swapPct(detail.facts)" :note="swapNote(detail.facts)" />
            <div v-for="d in detail.facts.disks" :key="d.mount" class="mt-2">
              <Meter :label="d.mount" :pct="d.used_pct" :note="diskNote(d)" />
            </div>
          </Card>
        </div>

        <div v-if="detail.facts?.failed_units.length" class="space-y-1">
          <h3 class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.failed_units') }}</h3>
          <div class="flex flex-wrap gap-1.5">
            <Badge v-for="u in detail.facts.failed_units" :key="u" tone="error">{{ u }}</Badge>
          </div>
        </div>

        <div v-if="detail.facts?.containers.length" class="space-y-1">
          <h3 class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.containers') }}</h3>
          <div class="space-y-0.5 font-mono text-xs">
            <div v-for="c in detail.facts.containers" :key="c.name" class="flex justify-between gap-3">
              <span class="truncate">{{ c.name }}</span>
              <span class="shrink-0 text-[var(--ll-muted)]">{{ c.status }}</span>
            </div>
          </div>
        </div>

        <div v-if="detail.facts?.ports.length" class="space-y-1">
          <h3 class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.ports') }}</h3>
          <div class="flex flex-wrap gap-1.5">
            <Badge v-for="p in detail.facts.ports" :key="p" tone="gray">{{ p }}</Badge>
          </div>
        </div>

        <!-- History: only the successful runs carry a series worth drawing. -->
        <div v-if="trend.length > 1" class="space-y-1">
          <h3 class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.history') }}</h3>
          <div class="-ml-1"><Chart :data="chartData" :options="chartOptions" :height="150" /></div>
        </div>

        <p v-if="detail.note" class="whitespace-pre-line text-xs text-[var(--ll-muted)]">{{ detail.note }}</p>
        <p v-if="detail.host_fingerprint" class="break-all font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.fingerprint') }}: {{ detail.host_fingerprint }}</p>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="detailOpen = false">{{ t('common.close') }}</Btn>
      </template>
    </Modal>

    <!-- Create / edit -->
    <Modal v-model="formOpen" :title="editing ? t('servers.edit') : t('servers.add')" width="640px">
      <div class="space-y-3">
        <TextField v-model="form.name" :label="t('servers.name')" />
        <div class="grid grid-cols-3 gap-3">
          <TextField v-model="form.host" class="col-span-2" :label="t('servers.host')" />
          <TextField v-model="form.port" :label="t('servers.port')" type="number" />
        </div>
        <TextField v-model="form.username" :label="t('servers.username')" />

        <Select v-model="form.auth_type" :label="t('servers.auth_type')" :options="authOptions" />
        <template v-if="form.auth_type === 'key'">
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.private_key') }}</span>
            <textarea
              v-model="form.private_key" rows="5" spellcheck="false"
              class="w-full resize-y rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-2 font-mono text-xs"
              :placeholder="editing ? t('servers.secret_kept') : '-----BEGIN OPENSSH PRIVATE KEY-----'"
            />
          </label>
          <p class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.private_key_hint') }}</p>
          <TextField v-model="form.passphrase" :label="t('servers.passphrase')" type="password" :placeholder="editing ? t('servers.secret_kept') : ''" />
        </template>
        <TextField v-else v-model="form.password" :label="t('servers.password')" type="password" :placeholder="editing ? t('servers.secret_kept') : ''" />

        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="form.group" :label="t('servers.group')" />
        </div>
        <label class="block">
          <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.note') }}</span>
          <textarea v-model="form.note" rows="2" class="w-full resize-y rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-2 text-sm" />
        </label>

        <label class="flex items-center gap-2 text-sm"><input v-model="form.enabled" type="checkbox" class="accent-primary-500">{{ t('servers.enabled') }}</label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.restricted_key" type="checkbox" class="accent-primary-500">{{ t('servers.restricted_key') }}</label>
        <p class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.restricted_key_hint') }}</p>
        <button class="text-xs text-primary-600 underline dark:text-primary-400" @click="openScript">{{ t('servers.script_title') }}</button>

        <!-- Host key confirmation. Saving stays blocked until this is answered:
             the pin is what protects the credential-carrying connection. -->
        <div class="rounded-lg border border-[var(--ll-border)] p-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium">{{ t('servers.fingerprint') }}</span>
            <Btn variant="ghost" size="sm" icon="network_check" :disabled="testing" @click="doTest">{{ testing ? t('servers.testing') : t('servers.test') }}</Btn>
          </div>
          <p v-if="!probe" class="mt-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.test_first') }}</p>
          <template v-else>
            <p class="mt-2 break-all font-mono text-xs">{{ probe.fingerprint || '—' }}</p>
            <p class="mt-1 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.fingerprint_confirm') }}</p>
            <p class="mt-1 font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.fingerprint_hint') }}</p>
            <p v-if="!probe.ok" class="mt-2 rounded bg-red-500/10 px-2 py-1.5 text-[0.7rem] text-red-600 dark:text-red-400">{{ errorText(probe.error) }}</p>
            <p v-else class="mt-2 text-[0.7rem] text-emerald-700 dark:text-emerald-400">{{ t('servers.test_ok') }}</p>
          </template>
        </div>
      </div>
      <template #footer>
        <Btn v-if="editing" variant="ghost" class="mr-auto text-red-600" icon="delete" @click="doDelete">{{ t('common.delete') }}</Btn>
        <Btn variant="ghost" @click="formOpen = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :disabled="!canSave" @click="save">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Forced-command setup -->
    <Modal v-model="scriptOpen" :title="t('servers.script_title')" width="760px">
      <div class="space-y-3 text-sm">
        <p>{{ t('servers.script_intro') }}</p>
        <pre class="overflow-x-auto rounded-lg bg-black/[0.05] p-3 font-mono text-xs dark:bg-white/5">{{ t('servers.script_authorized') }}</pre>
        <pre class="max-h-72 overflow-auto rounded-lg bg-black/[0.05] p-3 font-mono text-xs dark:bg-white/5">{{ script }}</pre>
        <p class="text-[var(--ll-muted)]">{{ t('servers.script_outro') }}</p>
      </div>
      <template #footer>
        <Btn variant="ghost" icon="content_copy" @click="copyScript">{{ t('common.copy') }}</Btn>
        <Btn variant="ghost" @click="scriptOpen = false">{{ t('common.close') }}</Btn>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, h, type PropType } from 'vue';
import type { AlignedData, Options } from 'uplot';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, Btn, Badge, Modal, TextField, Select, Chart } from '@spa/ui';
import { useServersStore, type Server, type ServerFacts, type ProbeResult, type TrendPoint } from '@spa/stores/servers';
import { severity, formatUptime, memoryNote, swapPct, swapNote, diskNote, fullestDisk } from '@spa/lib/server-facts';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { fmtDateTime } from '@spa/lib/datetime';

const CHART_INK = '#6d4aff'; // --color-primary-500
const CHART_WARN = '#e0a11b';
const AXIS_INK = '#625d69';
const AXIS_FONT = '600 12px ui-monospace, SFMono-Regular, Menlo, monospace';

const s = useServersStore();
const { success, error: toastError } = useToast();

const busy = ref(false);
const testing = ref(false);

// ---- small presentational helpers ----

/** A labelled bar. Inline because it is only ever used on this page. */
const Meter = (props: { label: string; pct: number | null; note?: string }) => h('div', {}, [
  h('div', { class: 'flex items-baseline justify-between gap-2 text-xs' }, [
    h('span', { class: 'truncate text-[var(--ll-muted)]' }, props.label),
    h('span', { class: 'shrink-0 font-mono tabular-nums' }, props.note ?? (props.pct === null ? '—' : `${props.pct}%`)),
  ]),
  h('div', { class: 'mt-1 h-1.5 overflow-hidden rounded-full bg-black/[0.07] dark:bg-white/10' }, [
    h('div', {
      class: ['h-full rounded-full', props.pct === null ? '' : props.pct >= 90 ? 'bg-red-500' : props.pct >= 75 ? 'bg-amber-500' : 'bg-primary-500'],
      style: { width: `${Math.min(100, Math.max(0, props.pct ?? 0))}%` },
    }),
  ]),
]);
Meter.props = { label: String, pct: { type: Number as unknown as PropType<number | null>, default: null }, note: String };

/** One definition-list row that hides itself when there is nothing to show. */
const Row = (props: { label: string; value?: string | null }) => h('div', { class: 'flex justify-between gap-3' }, [
  h('dt', { class: 'text-[var(--ll-muted)]' }, props.label),
  h('dd', { class: 'truncate text-right' }, props.value || '—'),
]);
Row.props = { label: String, value: String };

function statusLabel(srv: Server): string {
  if (!srv.status) return t('servers.status_unknown');
  return srv.status.ok ? t('servers.status_ok') : t('servers.status_fail');
}

/** The dot carries the card's health; the severity rules live in lib. */
const DOT: Record<string, string> = {
  unknown: 'bg-black/20 dark:bg-white/25',
  down: 'bg-red-500',
  warn: 'bg-amber-500',
  ok: 'bg-emerald-500',
};

function dotClass(srv: Server): string {
  return DOT[severity(srv)];
}

/** A machine-readable probe reason maps to a sentence; anything else is a transport message. */
function errorText(code: string | null): string {
  if (!code) return t('servers.status_fail');
  const key = `servers.error.${code}`;
  const translated = t(key);
  return translated === key ? code : translated;
}

function cpuText(f: ServerFacts): string {
  const cores = f.cpu.cores === null ? '' : t('servers.cores', { n: String(f.cpu.cores) });
  return [f.cpu.model, cores].filter(Boolean).join(' · ');
}

const grouped = computed(() => {
  const buckets = new Map<string, Server[]>();
  for (const srv of s.servers) {
    const key = srv.group ?? '';
    (buckets.get(key) ?? buckets.set(key, []).get(key)!).push(srv);
  }
  // Named groups first, alphabetically; the unnamed bucket last.
  return [...buckets.entries()]
    .sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : a.localeCompare(b)))
    .map(([name, items]) => ({ name, items }));
});

// ---- actions ----

async function doRefresh(srv: Server) {
  busy.value = true;
  try {
    await s.refresh(srv.id);
    success(t('servers.refresh_queued'));
    // The probe runs in the worker; re-read shortly so the card updates itself.
    scheduleReload();
  } finally { busy.value = false; }
}

async function doRefreshAll() {
  busy.value = true;
  try {
    await s.refreshAll();
    success(t('servers.refresh_queued'));
    scheduleReload();
  } finally { busy.value = false; }
}

let reloadTimer: number | undefined;
function scheduleReload() {
  window.clearTimeout(reloadTimer);
  reloadTimer = window.setTimeout(() => { void s.load(); }, 6000);
}

// ---- detail ----

const detailOpen = ref(false);
const detail = ref<Server | null>(null);
const history = ref<TrendPoint[]>([]);
const retestResult = ref<ProbeResult | null>(null);

async function openDetail(srv: Server) {
  detail.value = srv;
  history.value = [];
  retestResult.value = null;
  detailOpen.value = true;
  const r = await s.show(srv.id);
  detail.value = r.server;
  history.value = r.history;
}

// Oldest first for a left-to-right axis; a failed run has no series to draw.
const trend = computed(() => [...history.value].reverse().filter((p) => p.ok));

const chartData = computed<AlignedData>(() => [
  // uPlot needs a numeric x — the index, labelled with the timestamp below.
  trend.value.map((_, i) => i),
  trend.value.map((p) => p.mem_used_pct ?? 0),
  trend.value.map((p) => p.disk_max_pct ?? 0),
]);

const chartOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [
    {},
    { label: t('servers.memory'), stroke: CHART_INK, fill: CHART_INK + '26' },
    { label: t('servers.disks'), stroke: CHART_WARN },
  ],
  axes: [
    {
      stroke: AXIS_INK,
      font: AXIS_FONT,
      grid: { show: false },
      // Index back to its timestamp; uPlot may ask for fractional ticks.
      values: (_u, vals) => vals.map((v) => {
        const point = trend.value[Math.round(v)];
        return point ? fmtDateTime(point.collected_at) : '';
      }),
    },
    { stroke: AXIS_INK, font: AXIS_FONT, grid: { stroke: 'rgba(128,128,128,.24)' }, size: 48, values: (_u, vals) => vals.map((v) => `${v}%`) },
  ],
  scales: { x: { time: false }, y: { range: [0, 100] } },
}));

async function retest() {
  if (!detail.value) return;
  testing.value = true;
  try {
    retestResult.value = await s.testStored(detail.value.id);
  } finally { testing.value = false; }
}

// ---- form ----

interface Form {
  name: string; host: string; port: string; username: string;
  auth_type: 'password' | 'key'; password: string; private_key: string; passphrase: string;
  group: string; note: string; enabled: boolean; restricted_key: boolean;
}

const formOpen = ref(false);
const editing = ref<Server | null>(null);
const probe = ref<ProbeResult | null>(null);
const form = ref<Form>(blank());

function blank(): Form {
  return {
    name: '', host: '', port: '22', username: '', auth_type: 'key',
    password: '', private_key: '', passphrase: '', group: '', note: '',
    enabled: true, restricted_key: false,
  };
}

const authOptions = computed(() => [
  { value: 'key', title: t('servers.auth_key') },
  { value: 'password', title: t('servers.auth_password') },
]);

function openCreate() {
  editing.value = null;
  form.value = blank();
  probe.value = null;
  formOpen.value = true;
}

function openEdit(srv: Server) {
  editing.value = srv;
  form.value = {
    name: srv.name, host: srv.host, port: String(srv.port), username: srv.username,
    auth_type: srv.auth_type, password: '', private_key: '', passphrase: '',
    group: srv.group ?? '', note: srv.note ?? '',
    enabled: srv.enabled, restricted_key: srv.restricted_key,
  };
  probe.value = null;
  formOpen.value = true;
}

/**
 * A new server needs a confirmed host key before it can be saved — that pin is
 * what makes the first credential-carrying connection safe. An existing one
 * already has one, so a re-test is optional there.
 */
const canSave = computed(() => {
  if (!form.value.name || !form.value.host || !form.value.username) return false;
  return editing.value !== null || probe.value?.fingerprint != null;
});

function payload(): Record<string, unknown> {
  const f = form.value;
  return {
    name: f.name,
    host: f.host,
    port: Number(f.port) || 22,
    username: f.username,
    auth_type: f.auth_type,
    // Blank secrets are preserved server-side on update.
    ...(f.auth_type === 'key' ? { private_key: f.private_key, passphrase: f.passphrase } : { password: f.password }),
    group: f.group,
    note: f.note,
    enabled: f.enabled,
    restricted_key: f.restricted_key,
  };
}

async function doTest() {
  testing.value = true;
  try {
    // No fingerprint sent: this call is how we learn it.
    probe.value = await s.test(payload());
  } finally { testing.value = false; }
}

async function save() {
  const body = { ...payload(), host_fingerprint: probe.value?.fingerprint ?? editing.value?.host_fingerprint };
  try {
    if (editing.value) await s.update(editing.value.id, body);
    else await s.create(body);
    formOpen.value = false;
    await s.load();
    success(t('common.saved'));
  } catch {
    toastError(t('common.error'));
  }
}

async function doDelete() {
  const srv = editing.value;
  if (!srv) return;
  if (!(await confirmAsk(t('servers.delete_confirm', { name: srv.name })))) return;
  await s.remove(srv.id);
  formOpen.value = false;
  detailOpen.value = false;
  await s.load();
}

// ---- forced-command script ----

const scriptOpen = ref(false);
const script = ref('');

async function openScript() {
  if (!script.value) script.value = await s.probeScript();
  scriptOpen.value = true;
}

async function copyScript() {
  await navigator.clipboard.writeText(script.value);
  success(t('common.copied'));
}

onMounted(() => { void s.load(); });
onUnmounted(() => window.clearTimeout(reloadTimer));
</script>
