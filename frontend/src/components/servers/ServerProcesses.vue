<template>
  <div class="space-y-6">
    <Card :body-class="'p-4'">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <input
          v-model="procQuery"
          :placeholder="t('servers.filter')"
          class="w-64 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
        >
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="procBusy" @click="loadProcesses">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="procError" class="mb-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ procError }}</p>
      <p v-else-if="procBusy && !processes.length" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>

      <div v-if="actionNote" class="mb-3 rounded-lg px-3 py-2 text-sm" :class="actionOk ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'">
        <pre class="whitespace-pre-wrap font-mono text-[0.7rem]">{{ actionNote }}</pre>
      </div>

      <p class="mb-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.processes_note') }}</p>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-[var(--ll-border)] text-left text-[0.7rem] uppercase tracking-wide text-[var(--ll-muted)]">
              <th class="cursor-pointer py-1.5 pr-3 font-medium select-none" @click="sortProc('pid')">PID{{ procArrow('pid') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 font-medium select-none" @click="sortProc('user')">{{ t('servers.proc_user') }}{{ procArrow('user') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 font-medium select-none" @click="sortProc('command')">{{ t('servers.proc_command') }}{{ procArrow('command') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 text-right font-medium select-none" @click="sortProc('cpu')">CPU{{ procArrow('cpu') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 text-right font-medium select-none" @click="sortProc('rss_kb')">{{ t('servers.memory') }}{{ procArrow('rss_kb') }}</th>
              <th class="py-1.5 text-right font-medium" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in filteredProcesses" :key="p.pid" class="border-b border-[var(--ll-border)] last:border-0">
              <td class="py-2 pr-3 font-mono text-xs tabular-nums">{{ p.pid }}</td>
              <td class="py-2 pr-3 text-xs">{{ p.user }}</td>
              <td class="py-2 pr-3 font-mono text-xs">{{ p.command }}</td>
              <td class="py-2 pr-3 text-right font-mono text-xs tabular-nums">{{ p.cpu.toFixed(1) }}%</td>
              <td class="py-2 pr-3 text-right font-mono text-xs tabular-nums">{{ formatGib(p.rss_kb) }}</td>
              <td class="w-32 py-2 text-right">
                <div class="flex justify-end gap-1">
                  <Btn variant="ghost" size="sm" :disabled="acting" :title="t('servers.proc_term')" icon="close" @click="doSignal(p, 'TERM')" />
                  <Btn variant="ghost" size="sm" :disabled="acting" :title="t('servers.proc_kill')" icon="dangerous" @click="doSignal(p, 'KILL')" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!procBusy && !filteredProcesses.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Select } from '@spa/ui';
import { useServersStore, type ProcessRow, type ServiceUnit } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { formatGib } from '@spa/lib/server-facts';

const props = defineProps<{ serverId: number }>();

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

onMounted(loadProcesses);
</script>
