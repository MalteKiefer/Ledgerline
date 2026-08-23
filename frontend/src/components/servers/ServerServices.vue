<template>
  <div class="space-y-6">
    <!-- What the machine is judged by. A mail server lives or dies by its
         queue, a Proxmox host by its guests — neither is visible in a list of
         systemd units, which is what everything below this is. -->
    <Card v-if="hasRoleDetail" :body-class="'p-4'">
      <div class="mb-3 flex items-center justify-between gap-2">
        <SectionHead icon="tune" :label="t('servers.role_details')" level="h2" />
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="roleBusy" @click="loadRoleDetails">{{ t('servers.refresh') }}</Btn>
      </div>

      <div v-if="detail?.mail" class="mb-4">
        <SectionHead icon="mail" :label="t('servers.role_mail')" level="h3" class="mb-1.5" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div>
            <div class="font-mono text-xl font-bold tabular-nums" :class="(detail.mail.queued ?? 0) > 50 ? 'text-amber-600 dark:text-amber-400' : ''">
              {{ detail.mail.queued === null ? '—' : detail.mail.queued }}
            </div>
            <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.mail_queued') }}</div>
          </div>
          <div v-if="detail.mail.rspamd">
            <div class="font-mono text-xl font-bold tabular-nums">{{ detail.mail.rspamd.scanned ?? 0 }}</div>
            <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.mail_scanned') }}</div>
          </div>
          <div v-if="detail.mail.rspamd">
            <div class="font-mono text-xl font-bold tabular-nums">{{ spamShare }}</div>
            <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.mail_spam') }}</div>
          </div>
          <div>
            <div class="font-mono text-xl font-bold tabular-nums">{{ detail.mail.sessions.length }}</div>
            <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.mail_sessions') }}</div>
          </div>
        </div>
        <div v-if="detail.mail.sessions.length" class="mt-2 flex flex-wrap gap-1.5">
          <span v-for="(sn, i) in detail.mail.sessions" :key="`${sn.user}-${i}`" class="rounded-full bg-black/[0.04] px-2 py-0.5 font-mono text-[0.7rem] dark:bg-white/[0.06]">
            {{ sn.user }}<span class="text-[var(--ll-muted)]"> · {{ sn.service }}</span>
          </span>
        </div>
      </div>

      <div v-if="detail?.guests?.length" class="mb-4">
        <SectionHead icon="computer" :label="t('servers.guests')" level="h3" class="mb-1.5" />
        <div v-for="g in detail.guests" :key="`${g.kind}-${g.id}`" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
          <Badge tone="gray">{{ g.kind }}</Badge>
          <span class="font-mono text-[var(--ll-muted)]">{{ g.id }}</span>
          <span class="font-medium">{{ g.name }}</span>
          <Badge class="ml-auto" :tone="g.status === 'running' ? 'success' : 'gray'">{{ g.status }}</Badge>
        </div>
      </div>

      <div v-if="detail?.databases?.length" class="mb-4">
        <SectionHead icon="database" :label="t('servers.role_database')" level="h3" class="mb-1.5" />
        <div v-for="(db, i) in detail.databases" :key="`${db.engine}-${db.name}-${i}`" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
          <Badge tone="gray">{{ db.engine }}</Badge>
          <span class="truncate font-mono">{{ db.name }}</span>
          <span class="ml-auto shrink-0 tabular-nums text-[var(--ll-muted)]">
            <template v-if="db.size_b !== null">{{ formatGib(db.size_b / 1024) }}</template>
            <template v-else-if="db.used">{{ db.used }}</template>
            <template v-if="db.connections !== null"> · {{ t('servers.db_connections', { n: String(db.connections) }) }}</template>
          </span>
        </div>
      </div>

      <div v-if="detail?.sites?.length">
        <SectionHead icon="language" :label="t('servers.sites')" level="h3" class="mb-1.5" />
        <div class="flex flex-wrap gap-1.5">
          <span v-for="site in detail.sites" :key="site" class="rounded-full bg-black/[0.04] px-2 py-0.5 font-mono text-[0.7rem] dark:bg-white/[0.06]">{{ site }}</span>
        </div>
      </div>

      <!-- The tools for these roles are not on the host: on a Docker host,
           Postgres and Caddy run in containers and psql is nowhere to be
           found. Showing an empty list would claim there is nothing there. -->
      <p v-if="detail?.unreadable?.length" class="mt-1 text-xs text-[var(--ll-muted)]">
        {{ t('servers.role_unreadable', { roles: detail.unreadable.map((r) => t(`servers.role_${r}`)).join(', ') }) }}
      </p>

      <p v-if="roleBusy && !detail" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    </Card>

    <Card :body-class="'p-4'">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="svcQuery"
            :placeholder="t('servers.filter')"
            class="w-64 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
          >
          <Select v-model="svcState" class="w-40" :options="svcStateOptions" />
          <span class="text-xs text-[var(--ll-muted)]">{{ filteredServices.length }} / {{ services.length }}</span>
        </div>
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="svcBusy" @click="loadServices">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="svcError" class="mb-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ svcError }}</p>
      <p v-else-if="svcBusy && !services.length" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>

      <div v-if="actionNote" class="mb-3 rounded-lg px-3 py-2 text-sm" :class="actionOk ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'">
        <pre class="whitespace-pre-wrap font-mono text-[0.7rem]">{{ actionNote }}</pre>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <tbody>
            <tr v-for="u in filteredServices" :key="u.name" class="border-b border-[var(--ll-border)] last:border-0">
              <td class="py-2 pr-3">
                <div class="font-mono text-xs">{{ u.name }}</div>
                <div class="truncate text-[0.7rem] text-[var(--ll-muted)]">{{ u.description }}</div>
              </td>
              <td class="w-32 py-2 pr-3">
                <Badge :tone="u.active === 'active' ? 'success' : u.active === 'failed' ? 'error' : 'gray'">{{ u.sub }}</Badge>
              </td>
              <td class="w-56 py-2 text-right">
                <div class="flex justify-end gap-1">
                  <Btn variant="ghost" size="sm" :disabled="acting" :title="t('servers.svc_start')" icon="play_arrow" @click="doService(u.name, 'start')" />
                  <Btn variant="ghost" size="sm" :disabled="acting" :title="t('servers.svc_restart')" icon="restart_alt" @click="doService(u.name, 'restart')" />
                  <Btn variant="ghost" size="sm" :disabled="acting" :title="t('servers.svc_stop')" icon="stop" @click="doService(u.name, 'stop')" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!svcBusy && !filteredServices.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Select } from '@spa/ui';
import SectionHead from '@spa/components/servers/SectionHead.vue';
import { useServersStore, type ProcessRow, type RoleDetails, type ServerFacts, type ServiceUnit } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { formatGib } from '@spa/lib/server-facts';

const props = defineProps<{ serverId: number; facts?: ServerFacts | null }>();

const s = useServersStore();
const { success, error } = useToast();

/** Translate a code from the host into something a reader can act on. */
function errorText(code: string | null): string {
  if (!code) return '';
  const key = `servers.err_${code}`;
  const text = t(key);

  return text === key ? code : text;
}

// ---- services and processes ----

const services = ref<ServiceUnit[]>([]);
const processes = ref<ProcessRow[]>([]);
const svcQuery = ref('');
const procQuery = ref('');
const detail = ref<RoleDetails | null>(null);
const roleBusy = ref(false);

/**
 * Only ask when there is something to ask about. A host with no recognised
 * role would spend an SSH round trip to learn nothing.
 */
const hasRoleDetail = computed(() => {
  const roles = props.facts?.role?.roles ?? [];

  return roles.some((r) => ['mail', 'virtualisation', 'database', 'web'].includes(r));
});

/** Share of scanned mail that was spam, which is the figure people quote. */
const spamShare = computed(() => {
  const st = detail.value?.mail?.rspamd;
  if (!st || !st.scanned) return '—';

  return `${Math.round(((st.treated_as_spam ?? 0) / st.scanned) * 1000) / 10}%`;
});

async function loadRoleDetails() {
  if (!hasRoleDetail.value) return;
  roleBusy.value = true;
  try {
    detail.value = await s.roleDetails(props.serverId);
  } catch {
    detail.value = null;
  } finally {
    roleBusy.value = false;
  }
}

const svcBusy = ref(false);
const procBusy = ref(false);
const svcError = ref('');
const procError = ref('');
const acting = ref(false);
const actionNote = ref('');
const actionOk = ref(true);

/** Name or state. A machine has hundreds of units and two of them matter. */
const svcState = ref<'all' | 'running' | 'stopped' | 'failed'>('all');

const svcStateOptions = computed(() => [
  { title: t('servers.svc_state_all'), value: 'all' },
  { title: t('servers.svc_state_running'), value: 'running' },
  { title: t('servers.svc_state_stopped'), value: 'stopped' },
  { title: t('servers.svc_state_failed'), value: 'failed' },
]);

const filteredServices = computed(() => {
  const q = svcQuery.value.trim().toLowerCase();
  const state = svcState.value;

  return services.value.filter((u) => {
    if (q && !u.name.toLowerCase().includes(q) && !u.description.toLowerCase().includes(q)) return false;
    if (state === 'running') return u.active === 'active';
    if (state === 'failed') return u.active === 'failed';
    // "stopped" means anything not running, failed units included: a unit that
    // died is stopped, and hiding it here would be the wrong kind of tidy.
    if (state === 'stopped') return u.active !== 'active';

    return true;
  });
});

/** Which column the process table is ordered by, and in which direction. */
const procSort = ref<'pid' | 'user' | 'command' | 'cpu' | 'rss_kb'>('rss_kb');
const procDesc = ref(true);

function sortProc(key: typeof procSort.value) {
  if (procSort.value === key) procDesc.value = !procDesc.value;
  else {
    procSort.value = key;
    // Numbers are interesting from the top, names from the start.
    procDesc.value = key === 'rss_kb' || key === 'cpu' || key === 'pid';
  }
}

function procArrow(key: typeof procSort.value): string {
  return procSort.value === key ? (procDesc.value ? ' ↓' : ' ↑') : '';
}

const filteredProcesses = computed(() => {
  const q = procQuery.value.trim().toLowerCase();
  const rows = q
    ? processes.value.filter((p) => p.command.toLowerCase().includes(q) || p.user.toLowerCase().includes(q) || String(p.pid).includes(q))
    : processes.value.slice();
  const key = procSort.value;
  const dir = procDesc.value ? -1 : 1;

  // Copy before sorting: sorting the store array in place would reorder what
  // the next fetch merges into.
  return rows.slice().sort((a, b) => {
    const x = a[key];
    const y = b[key];
    if (typeof x === 'number' && typeof y === 'number') return (x - y) * dir;

    return String(x).localeCompare(String(y)) * dir;
  });
});

async function loadServices() {
  const id = props.serverId;
  svcBusy.value = true;
  svcError.value = '';
  try {
    const r = await s.services(id);
    services.value = r.units;
    if (!r.ok) svcError.value = errorText(r.error);
  } catch {
    svcError.value = t('servers.status_fail');
  } finally {
    svcBusy.value = false;
  }
}

async function loadProcesses() {
  const id = props.serverId;
  procBusy.value = true;
  procError.value = '';
  try {
    const r = await s.processes(id);
    processes.value = r.processes;
    if (!r.ok) procError.value = errorText(r.error);
  } catch {
    procError.value = t('servers.status_fail');
  } finally {
    procBusy.value = false;
  }
}

/**
 * Whatever the host says comes back verbatim. A monitoring account without
 * privilege will be refused, and showing that refusal is the point — a button
 * that quietly does nothing is worse than one that explains itself.
 */
async function doService(unit: string, action: string) {
  if (action !== 'start' && !(await confirmAsk(t('servers.svc_confirm', { action: t(`servers.svc_${action}`), unit })))) return;
  const id = props.serverId;
  acting.value = true;
  actionNote.value = '';
  try {
    const r = await s.serviceAction(id, unit, action);
    actionOk.value = r.ok;
    actionNote.value = r.output || (r.ok ? t('servers.action_ok') : t('servers.action_failed'));
    await loadServices();
  } catch {
    actionOk.value = false;
    actionNote.value = t('servers.action_failed');
  } finally {
    acting.value = false;
  }
}

async function doSignal(p: ProcessRow, signal: string) {
  if (!(await confirmAsk(t('servers.proc_confirm', { signal, pid: String(p.pid), command: p.command })))) return;
  const id = props.serverId;
  acting.value = true;
  actionNote.value = '';
  try {
    const r = await s.processSignal(id, p.pid, signal);
    actionOk.value = r.ok;
    actionNote.value = r.output || (r.ok ? t('servers.action_ok') : t('servers.action_failed'));
    await loadProcesses();
  } catch {
    actionOk.value = false;
    actionNote.value = t('servers.action_failed');
  } finally {
    acting.value = false;
  }
}

onMounted(() => {
  void loadServices();
  void loadRoleDetails();
});
</script>
