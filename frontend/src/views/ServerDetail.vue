<template>
  <div v-if="server" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <button class="text-xs text-[var(--ll-muted)] hover:underline" @click="$router.push('/servers')">
          ← {{ t('servers.title') }}
        </button>
        <div class="mt-1 flex items-center gap-2">
          <DistroLogo :id="facts?.os.id" :id-like="facts?.os.id_like" :size="34" :title="facts?.os.name" />
          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dotClass(server)" />
          <h1 class="truncate text-xl font-bold">{{ server.name }}</h1>
          <Badge :tone="server.status?.ok ? 'success' : server.status ? 'error' : 'gray'">{{ statusLabel(server) }}</Badge>
          <Badge v-if="server.restricted_key" tone="gray">{{ t('servers.restricted_key_short') }}</Badge>
        </div>
        <p class="mt-0.5 font-mono text-xs text-[var(--ll-muted)]">{{ server.username }}@{{ server.host }}:{{ server.port }}</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- When the next collection is due, derived from the last one rather
             than counted from page load. -->
        <span v-if="nextRefresh" class="hidden font-mono text-xs tabular-nums text-[var(--ll-muted)] sm:inline">{{ nextRefresh }}</span>
        <Btn variant="ghost" icon="network_check" :disabled="testing" @click="retest">{{ testing ? t('servers.testing') : t('servers.test') }}</Btn>
        <Btn variant="ghost" icon="refresh" @click="doRefresh">{{ t('servers.refresh') }}</Btn>
        <Btn variant="ghost" icon="edit" @click="$router.push({ path: '/servers', query: { edit: String(server.id) } })">{{ t('servers.edit') }}</Btn>

        <!-- Power. Kept behind a menu and behind a confirmation because these
             are the only buttons here that can end the machine. -->
        <div class="relative">
          <Btn variant="ghost" icon="power_settings_new" :disabled="powerBusy" @click="powerOpen = !powerOpen">{{ t('servers.power') }}</Btn>
          <div v-if="powerOpen" class="fixed inset-0 z-20" @click="powerOpen = false" />
          <div
            v-if="powerOpen"
            class="absolute right-0 z-30 mt-1 w-56 overflow-hidden rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] py-1 shadow-lg"
          >
            <button class="block w-full px-3 py-2 text-left text-sm hover:bg-black/5 dark:hover:bg-white/5" @click="doPower('reboot')">{{ t('servers.power_reboot') }}</button>
            <button class="block w-full px-3 py-2 text-left text-sm text-amber-600 hover:bg-black/5 dark:text-amber-400 dark:hover:bg-white/5" @click="doPower('reboot_force')">{{ t('servers.power_reboot_force') }}</button>
            <button class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-black/5 dark:text-red-400 dark:hover:bg-white/5" @click="doPower('poweroff')">{{ t('servers.power_poweroff') }}</button>
            <div class="my-1 border-t border-[var(--ll-border)]" />
            <button class="block w-full px-3 py-2 text-left text-sm hover:bg-black/5 dark:hover:bg-white/5" @click="doPower('cancel')">{{ t('servers.power_cancel') }}</button>
          </div>
        </div>
      </div>
    </div>

    <p v-if="retestResult" class="rounded-lg px-3 py-2 text-sm" :class="retestResult.ok ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'">
      {{ retestResult.ok ? t('servers.test_ok') : errorText(retestResult.error) }}
    </p>
    <p v-else-if="server.status && !server.status.ok" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">
      {{ errorText(server.status.error) }}
    </p>

<!-- Grouped by what you are doing rather than listed flat: eight equal
         words put "Remove" next to "Terminal" as though they were the same
         kind of thing. The destructive one sits apart, at the far end. -->
    <div class="flex items-center gap-1 overflow-x-auto border-b border-[var(--ll-border)]">
      <button
        v-for="tb in tabs.filter((x) => x.id !== 'removal')"
        :key="tb.id"
        class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="tab === tb.id ? 'border-[var(--ll-accent)] text-[var(--ll-accent)]' : 'border-transparent text-[var(--ll-muted)] hover:text-[var(--ll-text)]'"
        @click="setTab(tb.id)"
      >
        <Icon :name="tb.icon" :size="16" />
        {{ tb.label }}
      </button>

      <button
        class="-mb-px ml-auto flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="tab === 'removal' ? 'border-red-500 text-red-600 dark:text-red-400' : 'border-transparent text-[var(--ll-muted)] hover:text-red-600 dark:hover:text-red-400'"
        @click="setTab('removal')"
      >
        <Icon name="link_off" :size="16" />
        {{ t('servers.tab_removal') }}
      </button>
    </div>

    <template v-if="tab === 'overview'">
      <ServerOverview
        :facts="facts"
        :checks="checks"
        :note="server.note ?? ''"
        @go="setTab($event as Tab)"
        @kill-session="killSession"
      />

      <!-- Reachability and history stay on the page rather than in the
           component: both are about the record we keep, not about the snapshot,
           and they share the chart wiring with nothing else. -->
      <Card v-if="checks.length" :body-class="'p-4'">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold">{{ t('servers.reachability') }}</h2>
          <div class="flex items-center gap-1">
            <button
              v-for="h in [6, 24, 168]"
              :key="h"
              class="rounded-md px-2 py-1 text-xs"
              :class="checkHours === h ? 'bg-[var(--ll-accent)] text-white' : 'text-[var(--ll-muted)] hover:bg-[var(--ll-hover)]'"
              @click="setHours(h)"
            >
              {{ h < 24 ? t('servers.window_h', { n: String(h) }) : t('servers.window_d', { n: String(Math.round(h / 24)) }) }}
            </button>
          </div>
        </div>

        <div v-if="latencyPoints.length > 1" class="-ml-1 mb-3">
          <Chart :data="latencyData" :options="latencyOptions" :height="140" />
        </div>

        <div class="divide-y divide-[var(--ll-border)]">
          <div v-for="c in checks" :key="c.kind + ':' + (c.port ?? '-')" class="flex items-center gap-3 py-2">
            <span class="h-2 w-2 shrink-0 rounded-full" :class="c.last?.ok ? 'bg-emerald-500' : 'bg-red-500'" />
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">{{ checkTitle(c) }}</div>
              <div class="text-[0.7rem] text-[var(--ll-muted)]">
                <template v-if="c.last?.ok">{{ c.last.ms !== null ? `${c.last.ms} ms` : '' }}</template>
                <template v-else>{{ errorText(c.last?.error ?? null) }}</template>
                · {{ t('servers.samples_n', { n: String(c.samples) }) }}
              </div>
            </div>
            <div class="shrink-0 text-right">
              <div class="font-mono text-sm tabular-nums" :class="uptimeClass(c.uptime_pct)">{{ c.uptime_pct }}%</div>
              <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.uptime_window') }}</div>
            </div>
          </div>
        </div>
      </Card>

      <Card v-if="trend.length > 1" :title="t('servers.history')" :body-class="'p-4'">
        <div class="-ml-1"><Chart :data="chartData" :options="chartOptions" :height="180" /></div>
        <div class="mt-2 flex gap-4 text-[0.7rem] text-[var(--ll-muted)]">
          <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="{ background: CHART_INK }" />{{ t('servers.memory') }}</span>
          <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="{ background: CHART_WARN }" />{{ t('servers.disks') }}</span>
        </div>
      </Card>
    </template>

    <!-- Logs -->
    <template v-else-if="tab === 'logs'">
      <ServerLogs :server-id="server.id" />
    </template>


    <!-- Files. Mounted only while its tab is open, so leaving the tab gives
         the unlock grant back rather than leaving a filesystem open. -->
    <template v-else-if="tab === 'files'">
      <Card :body-class="'p-4'">
        <ServerFiles ref="filesRef" :key="server.id" :server-id="server.id" />
      </Card>
    </template>

    <!-- Terminal. Mounted only while its tab is open, so leaving the tab ends
         the session rather than leaving a shell waiting on the idle timeout. -->
    <template v-else-if="tab === 'terminal'">
      <Card :body-class="'p-4'">
        <ServerTerminal ref="terminalRef" :key="server.id" :server-id="server.id" />
      </Card>
    </template>

    <!-- Security -->
    <template v-else-if="tab === 'security'">
      <ServerSecurity :server-id="server.id" />
    </template>


    <!-- Services -->
    <template v-else-if="tab === 'services'">
      <ServerServices :server-id="server.id" />
    </template>


    <!-- Processes -->
    <template v-else-if="tab === 'processes'">
      <ServerProcesses :server-id="server.id" />
    </template>

    <!-- Removal: what to undo on the target, and removing it from here. -->
    <template v-else-if="tab === 'removal'">
    <!-- Removal. Exactly one path, chosen from what the setup recorded — the
         reader should not have to work out which case they are in. -->
    <Card :title="t('servers.removal_title')" :body-class="'p-4'">
      <p class="text-sm">{{ t('servers.removal_intro') }}</p>
      <p v-if="server.account_created === null" class="mt-2 rounded bg-amber-500/10 px-2.5 py-2 text-xs text-amber-700 dark:text-amber-400">
        {{ t('servers.removal_unknown_case') }}
      </p>
      <pre class="mt-3 overflow-x-auto rounded-lg bg-black/[0.05] p-3 font-mono text-xs dark:bg-white/5">{{ removalCommands }}</pre>
      <div class="mt-2 flex items-center gap-2">
        <Btn variant="ghost" size="sm" icon="content_copy" @click="copyRemoval">{{ t('common.copy') }}</Btn>
        <label class="flex items-center gap-2 text-xs"><input v-model="useSudo" type="checkbox" class="accent-primary-500">{{ t('servers.use_sudo') }}</label>
      </div>
      <p class="mt-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.removal_footprint') }}</p>
      <p v-if="server.host_fingerprint" class="mt-3 break-all font-mono text-[0.7rem] text-[var(--ll-muted)]">
        {{ t('servers.fingerprint') }}: {{ server.host_fingerprint }}
      </p>
    </Card>

      <Card :body-class="'p-4'">
        <h2 class="text-sm font-semibold text-red-600 dark:text-red-400">{{ t('servers.remove_from_app') }}</h2>
        <p class="mt-1 text-sm text-[var(--ll-muted)]">{{ t('servers.remove_from_app_intro') }}</p>
        <Btn class="mt-3" variant="ghost" icon="delete" :disabled="deleting" @click="doDelete">
          <span class="text-red-600 dark:text-red-400">{{ deleting ? t('common.loading') : t('servers.remove_from_app_action') }}</span>
        </Btn>
      </Card>
    </template>
  </div>

  <div v-else-if="loading" class="p-10 text-center text-[var(--ll-muted)]">{{ t('common.loading') }}</div>
  <div v-else class="p-10 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</div>
</template>

<script setup lang="ts">
import { computed, h, onBeforeUnmount, onMounted, ref, watch, type PropType, type VNode } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import type { AlignedData, Options } from 'uplot';
import { Card, Btn, Badge, Chart, DistroLogo, Select } from '@spa/ui';
import { useServersStore, type Server, type ServerFacts, type ProbeResult, type TrendPoint, type ServerCheckSeries, type ServiceUnit, type ProcessRow, type SecurityAudit, type BanList } from '@spa/stores/servers';
import {
  severity, formatGib,
} from '@spa/lib/server-facts';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';
import { confirmAsk } from '@spa/composables/useConfirm';
import ServerFiles from '@spa/components/ServerFiles.vue';
import ServerLogs from '@spa/components/servers/ServerLogs.vue';
import ServerOverview from '@spa/components/servers/ServerOverview.vue';
import ServerProcesses from '@spa/components/servers/ServerProcesses.vue';
import ServerSecurity from '@spa/components/servers/ServerSecurity.vue';
import ServerServices from '@spa/components/servers/ServerServices.vue';
import ServerTerminal from '@spa/components/ServerTerminal.vue';
import { fmtDate, fmtDateTime, fmtTime } from '@spa/lib/datetime';

const CHART_INK = '#6d4aff';
const CHART_WARN = '#e0a11b';
const CHART_CPU = '#2f9e6e';
const AXIS_INK = '#625d69';
const AXIS_FONT = '600 11px ui-monospace, SFMono-Regular, Menlo, monospace';

const route = useRoute();
const router = useRouter();
const s = useServersStore();
const { success, error } = useToast();

const server = ref<Server | null>(null);
const history = ref<TrendPoint[]>([]);
const loading = ref(true);
const testing = ref(false);
const retestResult = ref<ProbeResult | null>(null);
/**
 * Off when the account is root: there is nothing to elevate to, and prefixing
 * sudo on a host that may not have it installed fails for no reason.
 */
const useSudo = ref(true);

watch(server, (srv) => {
  if (srv) useSudo.value = srv.username !== 'root';
}, { immediate: true });

const facts = computed(() => server.value?.facts ?? null);

const DOT: Record<string, string> = {
  unknown: 'bg-black/20 dark:bg-white/25',
  down: 'bg-red-500',
  warn: 'bg-amber-500',
  ok: 'bg-emerald-500',
};
function dotClass(srv: Server): string { return DOT[severity(srv)]; }

function statusLabel(srv: Server): string {
  if (!srv.status) return t('servers.status_unknown');
  return srv.status.ok ? t('servers.status_ok') : t('servers.status_fail');
}

function errorText(code: string | null | undefined): string {
  if (!code) return t('servers.status_fail');
  const key = `servers.error.${code}`;
  const translated = t(key);
  return translated === key ? code : translated;
}

// ---- refresh countdown ----

/** The scheduler polls every five minutes; the next run is due five minutes
 *  after the last one landed, not five minutes after this page opened. */
const POLL_SECONDS = 300;
const now = ref(Date.now());
let ticker: number | null = null;

const nextRefresh = computed(() => {
  const at = server.value?.status?.collected_at;
  if (!at) return null;
  const left = Math.round((new Date(at).getTime() + POLL_SECONDS * 1000 - now.value) / 1000);
  if (left <= 0) return t('servers.due_now');

  return t('servers.next_in', { time: `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}` });
});

/**
 * Pick up the new snapshot once the countdown runs out.
 *
 * Without this the page shows "due" forever: the timestamp is fetched once on
 * open and never again, so the counter reaches zero and stays there even though
 * the worker has long since collected. Re-checked at most once a minute, so a
 * worker that is genuinely behind does not turn this into a polling loop.
 */
let lastReload = 0;
watch(now, () => {
  const at = server.value?.status?.collected_at;
  if (!at) return;
  const due = new Date(at).getTime() + POLL_SECONDS * 1000;
  if (now.value < due || now.value - lastReload < 60_000) return;
  lastReload = now.value;
  void load();
});

// ---- power ----

const powerOpen = ref(false);
const powerBusy = ref(false);

/**
 * Reboot, force-reboot or shut down.
 *
 * Confirmed every time and worded per action, because "reboot" and "force
 * reboot" read alike and behave nothing alike: the forced one does not stop
 * units in order.
 */
async function doPower(action: 'reboot' | 'reboot_force' | 'poweroff' | 'cancel') {
  powerOpen.value = false;
  const srv = server.value;
  if (!srv) return;

  if (action !== 'cancel') {
    const key = action === 'reboot' ? 'power_confirm_reboot' : action === 'reboot_force' ? 'power_confirm_reboot_force' : 'power_confirm_poweroff';
    if (!(await confirmAsk(t(`servers.${key}`, { name: srv.name })))) return;
  }

  powerBusy.value = true;
  try {
    const r = await s.power(srv.id, action);
    if (r.ok) success(t('servers.power_sent'));
    else error(r.output || errorText(r.error));
  } catch {
    error(t('servers.status_fail'));
  } finally {
    powerBusy.value = false;
  }
}

// ---- sessions ----

async function killSession(ses: { user: string; tty: string }) {
  const srv = server.value;
  if (!srv) return;
  if (!(await confirmAsk(t('servers.session_kill_confirm', { user: ses.user, tty: ses.tty })))) return;

  try {
    const r = await s.killSession(srv.id, ses.tty);
    if (r.ok) {
      success(t('servers.session_ended'));
      await load();
    } else {
      error(r.output || errorText(r.error));
    }
  } catch {
    error(t('servers.status_fail'));
  }
}

// ---- removal ----

const deleting = ref(false);

/**
 * Remove the server from the app. Deliberately separate from the instructions
 * above it: deleting the row here changes nothing on the target, and the
 * confirmation says so rather than letting someone assume it cleaned up.
 */
async function doDelete() {
  const srv = server.value;
  if (!srv) return;
  if (!(await confirmAsk(t('servers.delete_confirm', { name: srv.name })))) return;
  deleting.value = true;
  try {
    await s.remove(srv.id);
    success(t('servers.removed'));
    await router.push('/servers');
  } catch {
    deleting.value = false;
  }
}

// ---- tabs ----

type Tab = 'overview' | 'logs' | 'security' | 'services' | 'processes' | 'files' | 'terminal' | 'removal';

const tab = ref<Tab>('overview');

/**
 * Ordered by how a host is actually worked on: look at it, look at what runs on
 * it, then reach into it. Removal is in the list so the URL and the guard know
 * about it, but it is rendered apart from the rest.
 */
const tabs = computed<{ id: Tab; label: string; icon: string }[]>(() => [
  { id: 'overview', label: t('servers.tab_overview'), icon: 'dashboard' },
  { id: 'services', label: t('servers.tab_services'), icon: 'settings_applications' },
  { id: 'processes', label: t('servers.tab_processes'), icon: 'list_alt' },
  { id: 'logs', label: t('servers.tab_logs'), icon: 'article' },
  { id: 'security', label: t('servers.tab_security'), icon: 'shield' },
  { id: 'files', label: t('servers.tab_files'), icon: 'folder' },
  { id: 'terminal', label: t('servers.tab_terminal'), icon: 'terminal' },
  { id: 'removal', label: t('servers.tab_removal'), icon: 'link_off' },
]);

const terminalRef = ref<{ close: () => Promise<void> } | null>(null);
const filesRef = ref<{ close: () => Promise<void> } | null>(null);

function setTab(next: Tab) {
  // Leaving the terminal ends the shell here rather than relying on unmount:
  // unmount closes it too, but fire-and-forget, so a slow answer could leave a
  // session running on somebody's server after the tab looked closed.
  if (tab.value === 'terminal' && next !== 'terminal') void terminalRef.value?.close();
  // Same for the file browser: leaving hands the unlock grant back rather than
  // letting it sit until it expires.
  if (tab.value === 'files' && next !== 'files') void filesRef.value?.close();
  tab.value = next;
  // The page is already deep-linkable; the tab belongs in the URL for the same
  // reason the id does — so a link lands where the sender was looking.
  void router.replace({ query: { ...route.query, tab: next === 'overview' ? undefined : next } });
}

// ---- reachability ----

const checks = ref<ServerCheckSeries[]>([]);
const checkHours = ref(24);

async function loadChecks() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
  try {
    checks.value = (await s.checks(id, checkHours.value)).checks;
  } catch {
    checks.value = [];
  }
}

function setHours(h: number) {
  checkHours.value = h;
  void loadChecks();
}

/**
 * What to call a check. ICMP has no port; the SSH check is named after its role,
 * because "22" alone does not explain why it is always there.
 */
function checkTitle(c: ServerCheckSeries): string {
  if (c.kind === 'icmp') return t('servers.check_icmp');
  const label = c.label ? `${c.label} · ` : '';
  return `${label}${t('servers.check_port', { port: String(c.port ?? '') })}`;
}

function uptimeClass(pct: number): string {
  if (pct >= 99.5) return 'text-emerald-600 dark:text-emerald-400';
  if (pct >= 95) return 'text-amber-600 dark:text-amber-400';
  return 'text-red-600 dark:text-red-400';
}

/**
 * The latency chart draws one series: whichever check is the best measure of
 * "how far away is this host". ICMP if we have it — it is the closest thing to
 * pure round-trip — otherwise the SSH handshake.
 */
const latencySeries = computed<ServerCheckSeries | null>(
  () => checks.value.find((c) => c.kind === 'icmp') ?? checks.value[0] ?? null,
);

const latencyPoints = computed(() => (latencySeries.value?.points ?? []).filter((p) => p.ms !== null));

const latencyData = computed<AlignedData>(() => [
  latencyPoints.value.map((p) => Math.floor(new Date(p.t).getTime() / 1000)),
  latencyPoints.value.map((p) => p.ms as number),
]);

const latencyOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [{}, { label: 'ms', stroke: CHART_INK, fill: CHART_INK + '1f' }],
  axes: [
    {
      stroke: AXIS_INK,
      font: AXIS_FONT,
      grid: { show: false },
      space: 84,
      values: (_u, splits) => splits.map((ts) => (checkHours.value > 24 ? `${fmtDate(ts * 1000)} ${fmtTime(ts * 1000)}` : fmtTime(ts * 1000))),
    },
    { stroke: AXIS_INK, font: AXIS_FONT, grid: { stroke: 'rgba(128,128,128,.24)' }, size: 44, values: (_u, vals) => vals.map((v) => `${v}`) },
  ],
  scales: { x: { time: true } },
}));

// ---- history ----

const trend = computed(() => [...history.value].reverse().filter((p) => p.ok));

/**
 * A real time scale, not an index. With indices uPlot has no idea what the gaps
 * between points mean, so it spaces ticks evenly and we were left thinning the
 * labels by hand — which is why they still collided. Given seconds and
 * `time: true` it picks tick positions that fit the width itself.
 */
const chartData = computed<AlignedData>(() => [
  trend.value.map((p) => Math.floor(new Date(p.collected_at).getTime() / 1000)),
  trend.value.map((p) => p.cpu_used_pct ?? null),
  trend.value.map((p) => p.mem_used_pct ?? null),
  trend.value.map((p) => p.disk_max_pct ?? null),
]);

/** True once the window spans more than a day, when a bare clock time is ambiguous. */
const trendSpansDays = computed(() => {
  const xs = trend.value;
  if (xs.length < 2) return false;
  const a = new Date(xs[0].collected_at).getTime();
  const b = new Date(xs[xs.length - 1].collected_at).getTime();
  return b - a > 24 * 3600 * 1000;
});

const chartOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [
    {},
    { label: t('servers.cpu'), stroke: CHART_CPU, width: 1.5 },
    { label: t('servers.memory'), stroke: CHART_INK, fill: CHART_INK + '26' },
    { label: t('servers.disks'), stroke: CHART_WARN },
  ],
  axes: [
    {
      stroke: AXIS_INK,
      font: AXIS_FONT,
      grid: { show: false },
      // Minimum pixels between ticks. uPlot drops ticks that would not fit, so
      // the labels cannot collide however narrow the chart gets.
      space: 84,
      values: (_u, splits) =>
        splits.map((ts) =>
          trendSpansDays.value
            ? `${fmtDate(ts * 1000)} ${fmtTime(ts * 1000)}`
            : fmtTime(ts * 1000),
        ),
    },
    { stroke: AXIS_INK, font: AXIS_FONT, grid: { stroke: 'rgba(128,128,128,.24)' }, size: 44, values: (_u, vals) => vals.map((v) => `${v}%`) },
  ],
  scales: { x: { time: true }, y: { range: [0, 100] } },
}));

// ---- actions ----

async function load() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) { loading.value = false; return; }
  try {
    const r = await s.show(id);
    server.value = r.server;
    history.value = r.history;
    void loadChecks();
    // The tab comes from the URL if it names a real one; the component behind
    // it loads its own data when it mounts.
    const wanted = String(route.query.tab ?? '');
    if (tabs.value.some((tb) => tb.id === wanted)) tab.value = wanted as Tab;
  } catch {
    server.value = null;
  } finally {
    loading.value = false;
  }
}

async function doRefresh() {
  if (!server.value) return;
  await s.refresh(server.value.id);
  success(t('servers.refresh_queued'));
  window.setTimeout(() => { void load(); }, 6000);
}

async function retest() {
  if (!server.value) return;
  testing.value = true;
  try {
    retestResult.value = await s.testStored(server.value.id);
  } finally { testing.value = false; }
}

/**
 * One removal path, not two. Which one is not a guess: the setup recorded
 * whether it created the account. Offering `userdel` for an account the operator
 * already had — for root, the account that owns the machine — would be the worst
 * possible default.
 */
const removalCommands = computed(() => {
  const srv = server.value;
  if (!srv) return '';
  const sudo = useSudo.value ? 'sudo ' : '';
  const blob = (srv.public_key ?? '').split(' ')[1] ?? '';
  const match = blob || 'll-facts';
  const lines: string[] = [];

  if (srv.account_created === true) {
    lines.push(
      `# ${t('servers.removal_case_dedicated')}`,
      `# ${t('servers.removal_pkill_note')}`,
      `${sudo}sh -c 'pkill -u "$1" 2>/dev/null; userdel -r "$1"' _ ${srv.username}`,
    );
  } else {
    lines.push(
      `# ${t('servers.removal_case_shared')}`,
      `${sudo}sh -c 'K=$(getent passwd "$1" | cut -d: -f6)/.ssh/authorized_keys; grep -v "$2" "$K" > "$K.tmp"; mv "$K.tmp" "$K"; chmod 600 "$K"' _ ${srv.username} '${match}'`,
    );
  }

  if (srv.restricted_key) lines.push(`${sudo}rm -f /usr/local/bin/ll-facts`);

  return lines.join('\n');
});

async function copyRemoval() {
  await navigator.clipboard.writeText(removalCommands.value);
  success(t('common.copied'));
}

onMounted(() => {
  void load();
  ticker = window.setInterval(() => { now.value = Date.now(); }, 1000);
});

onBeforeUnmount(() => {
  if (ticker !== null) window.clearInterval(ticker);
});
void router;
</script>
