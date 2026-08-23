<template>
  <div class="space-y-6">
    <!-- An engine that is not installed and an engine we are not allowed to
         talk to are opposite answers. Saying "no containers" for the second
         would be a lie, so each gets its own message. -->
    <Card v-if="!busy && !state?.available" :body-class="'p-6 text-center'">
      <Icon name="deployed_code" :size="32" class="mx-auto mb-2 text-[var(--ll-muted)]" />
      <p class="text-sm font-medium">{{ state?.error === 'no_access' ? t('servers.docker_no_access') : t('servers.docker_absent') }}</p>
      <p class="mt-1 text-xs text-[var(--ll-muted)]">
        {{ state?.error === 'no_access' ? t('servers.docker_no_access_hint') : t('servers.docker_absent_hint') }}
      </p>
    </Card>

    <template v-else>
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile :label="t('servers.docker_running')" :value="String(running.length)" :note="t('servers.docker_of_total', { n: String(containers.length) })" icon="play_circle" />
        <StatTile :label="t('servers.docker_images')" :value="storageLoaded ? String(images.length) : '—'" :note="diskFor('Images')" icon="layers" />
        <StatTile :label="t('servers.docker_volumes')" :value="String(state?.volumes.length ?? 0)" :note="diskFor('Local Volumes')" icon="database" />
        <StatTile :label="t('servers.docker_reclaimable')" :value="reclaimable" :note="state?.version ?? ''" icon="cleaning_services" />
      </div>

      <Card :body-class="'p-4'">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-2">
            <input
              v-model="query"
              :placeholder="t('servers.filter')"
              class="w-64 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
            >
            <Select v-model="stateFilter" class="w-40" :options="stateOptions" />
            <span class="text-xs text-[var(--ll-muted)]">{{ filtered.length }} / {{ containers.length }}</span>
          </div>
          <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" @click="load">{{ t('servers.refresh') }}</Btn>
        </div>

        <div v-if="note" class="mb-3 rounded-lg px-3 py-2 text-sm" :class="noteOk ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'">
          <pre class="whitespace-pre-wrap font-mono text-[0.7rem]">{{ note }}</pre>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[var(--ll-border)] text-left text-[0.7rem] uppercase tracking-wide text-[var(--ll-muted)]">
                <th class="py-2 pr-3 font-semibold">{{ t('servers.docker_container') }}</th>
                <th class="w-28 py-2 pr-3 font-semibold">{{ t('common.status') }}</th>
                <th class="w-24 py-2 pr-3 text-right font-semibold">CPU</th>
                <th class="w-40 py-2 pr-3 text-right font-semibold">{{ t('servers.memory') }}</th>
                <th class="w-40 py-2 text-right font-semibold">{{ t('servers.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in filtered" :key="c.id" class="border-b border-[var(--ll-border)] last:border-0">
                <td class="py-2 pr-3">
                  <div class="flex items-center gap-2">
                    <span class="font-medium">{{ c.name }}</span>
                    <Badge v-if="c.health && c.health !== 'none'" :tone="c.health === 'healthy' ? 'success' : 'warning'">{{ c.health }}</Badge>
                    <Badge v-if="c.compose" tone="gray">{{ c.compose }}</Badge>
                  </div>
                  <div class="truncate font-mono text-[0.7rem] text-[var(--ll-muted)]" :title="`${c.image} ${c.ports}`">{{ c.image }}</div>
                  <div v-if="c.ports" class="truncate font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ c.ports }}</div>
                </td>
                <td class="py-2 pr-3">
                  <Badge :tone="c.state === 'running' ? 'success' : c.state === 'exited' ? 'error' : 'gray'">{{ c.state }}</Badge>
                  <div class="mt-0.5 truncate text-[0.7rem] text-[var(--ll-muted)]" :title="c.status">{{ c.status }}</div>
                </td>
                <td class="py-2 pr-3 text-right font-mono text-xs tabular-nums">{{ c.cpu || '—' }}</td>
                <td class="py-2 pr-3 text-right font-mono text-xs tabular-nums">
                  {{ c.mem || '—' }}
                  <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ c.mem_pct }}</div>
                </td>
                <td class="py-2 text-right">
                  <div class="flex justify-end gap-1">
                    <Btn v-if="c.state !== 'running'" variant="ghost" size="sm" :disabled="acting" :title="t('servers.svc_start')" icon="play_arrow" @click="act(c, 'start')" />
                    <Btn variant="ghost" size="sm" :disabled="acting" :title="t('servers.svc_restart')" icon="restart_alt" @click="act(c, 'restart')" />
                    <Btn v-if="c.state === 'running'" variant="ghost" size="sm" :disabled="acting" :title="t('servers.svc_stop')" icon="stop" @click="act(c, 'stop')" />
                    <Btn variant="ghost" size="sm" :disabled="acting" :title="t('common.delete')" icon="delete" @click="act(c, 'remove')" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!busy && !filtered.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
        </div>
      </Card>

      <div class="grid gap-4 lg:grid-cols-2">
        <Card :body-class="'p-4'">
          <div class="mb-2 flex items-center justify-between gap-2">
            <SectionHead icon="layers" :label="t('servers.docker_images')" level="h3" />
            <Btn variant="ghost" size="xs" icon="refresh" :disabled="storageBusy" @click="loadStorage">
              {{ storageLoaded ? t('servers.refresh') : t('servers.docker_storage_load') }}
            </Btn>
          </div>
          <!-- Asked for separately: on a host with a real image collection the
               engine takes the better part of a minute to add these up, and
               waiting for it kept the running containers off the screen. -->
          <p v-if="!storageLoaded && !storageBusy" class="py-3 text-center text-xs text-[var(--ll-muted)]">{{ t('servers.docker_storage_hint') }}</p>
          <p v-else-if="storageBusy" class="py-3 text-center text-xs text-[var(--ll-muted)]">{{ t('servers.docker_storage_loading') }}</p>
          <p v-else-if="storageError" class="py-3 text-center text-xs text-amber-600 dark:text-amber-400">{{ storageError }}</p>
          <div v-else class="max-h-72 space-y-1 overflow-y-auto">
            <div v-for="i in images" :key="i.id" class="flex items-center justify-between gap-2 border-b border-[var(--ll-border)] py-1.5 text-xs last:border-0">
              <span class="truncate font-mono" :title="`${i.repo}:${i.tag}`">{{ i.repo }}<span class="text-[var(--ll-muted)]">:{{ i.tag }}</span></span>
              <span class="shrink-0 tabular-nums text-[var(--ll-muted)]">{{ i.size }}</span>
            </div>
            <p v-if="!images.length" class="py-3 text-center text-xs text-[var(--ll-muted)]">{{ t('common.none') }}</p>
          </div>
        </Card>

        <Card :body-class="'p-4'">
          <SectionHead icon="database" :label="`${t('servers.docker_volumes')} & ${t('servers.docker_networks')}`" level="h3" class="mb-2" />
          <div class="max-h-72 space-y-1 overflow-y-auto">
            <div v-for="v in state?.volumes ?? []" :key="v.name" class="flex items-center justify-between gap-2 border-b border-[var(--ll-border)] py-1.5 text-xs last:border-0">
              <span class="truncate font-mono" :title="v.mount">{{ v.name }}</span>
              <Badge tone="gray">{{ v.driver }}</Badge>
            </div>
            <div v-for="n in state?.networks ?? []" :key="n.name" class="flex items-center justify-between gap-2 border-b border-[var(--ll-border)] py-1.5 text-xs last:border-0">
              <span class="truncate font-mono">{{ n.name }}</span>
              <span class="text-[var(--ll-muted)]">{{ n.driver }} · {{ n.scope }}</span>
            </div>
          </div>
        </Card>
      </div>

      <!-- One button per target, not one sweep: pruning volumes throws data
           away and pruning dangling images does not, and hiding that
           difference behind a single button hides it exactly where it
           matters. -->
      <Card :body-class="'p-4'">
        <SectionHead icon="cleaning_services" :label="t('servers.docker_prune')" level="h3" />
        <p class="mt-1 text-xs text-[var(--ll-muted)]">{{ t('servers.docker_prune_hint') }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <Btn v-for="p in pruneTargets" :key="p" variant="soft" size="sm" :disabled="acting" @click="prune(p)">
            {{ t(`servers.docker_prune_${p}`) }}
          </Btn>
        </div>
      </Card>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Icon, Select } from '@spa/ui';
import SectionHead from '@spa/components/servers/SectionHead.vue';
import { useServersStore, type DockerContainer, type DockerState, type DockerImage, type DockerDisk } from '@spa/stores/servers';
import { confirmAsk } from '@spa/composables/useConfirm';
import StatTile from './StatTile.vue';

const props = defineProps<{ serverId: number }>();

const store = useServersStore();

const state = ref<DockerState | null>(null);
const busy = ref(false);
const acting = ref(false);
const note = ref('');
const noteOk = ref(true);
const query = ref('');
const stateFilter = ref('');

const pruneTargets = ['images', 'containers', 'volumes', 'networks', 'builder'];

// Images and reclaimable space live behind their own request.
const images = ref<DockerImage[]>([]);
const disk = ref<DockerDisk[]>([]);
const storageBusy = ref(false);
const storageLoaded = ref(false);
const storageError = ref('');

async function loadStorage(): Promise<void> {
  storageBusy.value = true;
  storageError.value = '';
  try {
    const res = await store.dockerStorage(props.serverId);
    if (!res.ok) {
      // A call that ran out of time is not an unreachable host, and the
      // difference decides whether somebody goes looking for a network fault.
      storageError.value = t(res.error === 'timeout' ? 'servers.docker_storage_slow' : 'servers.status_fail');

      return;
    }
    images.value = res.images;
    disk.value = res.disk;
    storageLoaded.value = true;
  } catch {
    storageError.value = t('servers.status_fail');
  } finally {
    storageBusy.value = false;
  }
}

const containers = computed(() => state.value?.containers ?? []);
const running = computed(() => containers.value.filter((c) => c.state === 'running'));

const stateOptions = computed(() => [
  { value: '', title: t('servers.docker_state_all') },
  { value: 'running', title: t('servers.docker_state_running') },
  { value: 'exited', title: t('servers.docker_state_stopped') },
]);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();

  return containers.value.filter((c) => {
    if (stateFilter.value && c.state !== stateFilter.value) return false;
    if (!q) return true;

    return `${c.name} ${c.image} ${c.compose} ${c.ports}`.toLowerCase().includes(q);
  });
});

/** `docker system df` reports one row per kind; pick the one asked for. */
const diskFor = (type: string) => {
  const row = disk.value.find((d) => d.type.toLowerCase().startsWith(type.toLowerCase()));

  return row ? `${row.size} · ${row.reclaimable}` : '';
};

const reclaimable = computed(() => {
  const rows = disk.value;
  if (!rows.length) return '—';

  // The engine already sums this per kind; showing the images figure alone
  // would understate what a prune would actually free.
  return rows.map((r) => r.reclaimable).find((r) => r && r !== '0B') ?? '0B';
});

const load = async () => {
  busy.value = true;
  try {
    state.value = await store.docker(props.serverId);
  } catch {
    state.value = { available: false, error: 'unreachable', version: null, containers: [], images: [], volumes: [], networks: [], disk: [], compose: [] };
  } finally {
    busy.value = false;
  }
};

const act = async (c: DockerContainer, action: string) => {
  // Removing a container is not undoable and stopping one takes a service off
  // the air; both deserve to be asked about, starting one does not.
  if ((action === 'remove' || action === 'stop') && !(await confirmAsk(t(`servers.docker_confirm_${action}`, { name: c.name })))) return;

  acting.value = true;
  note.value = '';
  try {
    const res = await store.dockerAction(props.serverId, c.id || c.name, action);
    noteOk.value = res.ok;
    note.value = res.output || res.error || '';
    await load();
  } catch {
    noteOk.value = false;
    note.value = t('servers.action_failed');
  } finally {
    acting.value = false;
  }
};

const prune = async (target: string) => {
  if (!(await confirmAsk(t(`servers.docker_prune_confirm_${target}`)))) return;

  acting.value = true;
  note.value = '';
  try {
    const res = await store.dockerPrune(props.serverId, target);
    noteOk.value = res.ok;
    note.value = res.output || res.error || '';
    await load();
  } catch {
    noteOk.value = false;
    note.value = t('servers.action_failed');
  } finally {
    acting.value = false;
  }
};

onMounted(load);
</script>
