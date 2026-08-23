<template>
  <div class="space-y-4">
    <Card :body-class="'p-4'">
      <div class="mb-3 flex items-center justify-between gap-2">
        <SectionHead icon="admin_panel_settings" :label="t('servers.panel_title')" level="h2" />
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" @click="load">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="busy && !data" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

      <div v-else-if="data" class="space-y-4">
        <!-- No panel is an answer worth stating: it means every service on this
             host was put there by hand and stays where it was put. -->
        <p v-if="!data.panels.length" class="text-sm text-[var(--ll-muted)]">{{ t('servers.panel_none') }}</p>

        <div v-for="p in data.panels" :key="p.id" class="rounded-lg border border-[var(--ll-border)] p-3">
          <div class="flex flex-wrap items-center gap-2">
            <Icon name="admin_panel_settings" :size="20" :class="p.running === false ? 'text-amber-600 dark:text-amber-400' : 'text-[var(--ll-muted)]'" />
            <span class="font-medium">{{ p.name }}</span>
            <span v-if="p.version" class="ll-mono text-xs text-[var(--ll-muted)]">{{ p.version }}</span>

            <!-- Three states, not two: no unit to ask is its own answer. -->
            <Badge v-if="p.running === true" tone="success">{{ p.unit_state || t('servers.svc_running') }}</Badge>
            <Badge v-else-if="p.running === false" tone="warning">{{ p.unit_state || t('servers.svc_stopped') }}</Badge>
            <Badge v-else-if="p.container" tone="info">{{ t('servers.panel_container') }}</Badge>

            <div v-if="p.unit" class="ml-auto flex items-center gap-1">
              <Btn variant="ghost" size="xs" icon="restart_alt" :disabled="acting" @click="act(p, 'restart')">{{ t('servers.svc_restart') }}</Btn>
              <Btn v-if="p.running === false" variant="ghost" size="xs" icon="play_arrow" :disabled="acting" @click="act(p, 'start')">{{ t('servers.svc_start') }}</Btn>
              <Btn v-else variant="ghost" size="xs" icon="stop" class="text-red-600" :disabled="acting" @click="act(p, 'stop')">{{ t('servers.svc_stop') }}</Btn>
            </div>
          </div>

          <!-- Where it answers. A port only appears here when something is
               actually listening on it, so an empty list is informative. -->
          <div v-if="p.ports.length || p.path || p.image" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <span v-if="p.ports.length">
              <span class="text-[var(--ll-muted)]">{{ t('servers.panel_ports') }}</span>
              <span class="ll-mono">{{ p.ports.join(', ') }}</span>
            </span>
            <span v-if="p.path"><span class="text-[var(--ll-muted)]">{{ t('servers.panel_path') }}</span> <span class="ll-mono">{{ p.path }}</span></span>
            <span v-if="p.unit"><span class="text-[var(--ll-muted)]">{{ t('servers.panel_unit') }}</span> <span class="ll-mono">{{ p.unit }}</span></span>
            <span v-if="p.image"><span class="text-[var(--ll-muted)]">{{ t('servers.panel_image') }}</span> <span class="ll-mono">{{ p.image }}</span></span>
          </div>

          <div v-if="Object.keys(p.counts).length" class="mt-2 flex flex-wrap gap-3">
            <div v-for="(v, k) in p.counts" :key="k" class="rounded-md bg-black/[0.04] px-2.5 py-1 dark:bg-white/[0.06]">
              <div class="text-sm font-semibold">{{ v }}</div>
              <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t(`servers.panel_count_${k}`) }}</div>
            </div>
          </div>

          <div v-if="Object.keys(p.facts).length" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="(v, k) in p.facts" :key="k" class="rounded-full bg-black/[0.04] px-2 py-0.5 text-[0.7rem] dark:bg-white/[0.06]">
              <span class="text-[var(--ll-muted)]">{{ k }}</span> {{ v }}
            </span>
          </div>

          <p v-if="p.note" class="mt-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t(`servers.panel_note_${p.note}`) }}</p>

          <!-- What the panel itself knows. The PHP breakdown leads because it
               is the one figure that turns into work: a handler out of support
               is not a detail, and the count says whether that is one forgotten
               site or a third of the machine. -->
          <div v-if="p.details?.php?.length" class="mt-3">
            <SectionHead icon="php" :label="t('servers.panel_php')" level="h3" class="mb-1.5" />
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="h in p.details.php" :key="h.handler"
                class="rounded-full px-2 py-0.5 text-[0.7rem]"
                :class="phpTone(h.version)"
                :title="h.handler"
              >
                PHP {{ h.version ?? '?' }} · {{ h.count }}
                <span v-if="phpState(h.version)" class="opacity-80">· {{ t(`servers.panel_php_${phpState(h.version)}`) }}</span>
              </span>
            </div>
          </div>

          <div v-if="p.details?.domains?.length" class="mt-3">
            <SectionHead icon="language" :label="t('servers.panel_domains')" level="h3" class="mb-1.5" />
            <div class="max-h-72 overflow-y-auto">
              <div v-for="d in p.details.domains" :key="d.name" class="group flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
                <Icon :name="d.ssl ? 'lock' : 'lock_open'" :size="14" :class="d.ssl ? 'text-[var(--ll-muted)]' : 'text-amber-600 dark:text-amber-400'" :title="d.ssl ? 'TLS' : t('servers.panel_no_tls')" />
                <span class="truncate font-mono" :class="d.active ? '' : 'text-[var(--ll-muted)] line-through'">{{ d.name }}</span>
                <span v-if="d.php" class="shrink-0 rounded px-1.5 text-[0.65rem]" :class="phpTone(d.php)">{{ d.php }}</span>
                <span v-if="!d.active" class="shrink-0 text-[0.65rem] text-[var(--ll-muted)]">{{ t('servers.panel_inactive') }}</span>
                <span v-if="d.size_mb !== null" class="ml-auto shrink-0 tabular-nums text-[var(--ll-muted)]">{{ fmtSize(d.size_mb) }}</span>
                <span class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                  <Btn v-if="!d.active" variant="ghost" size="xs" icon="play_arrow" :disabled="acting" :title="t('servers.panel_site_on')" @click="site(d.name, 'on')" />
                  <template v-else>
                    <Btn variant="ghost" size="xs" icon="pause" :disabled="acting" :title="t('servers.panel_site_suspend')" @click="site(d.name, 'suspend')" />
                    <Btn variant="ghost" size="xs" icon="power_settings_new" class="text-red-600" :disabled="acting" :title="t('servers.panel_site_off')" @click="site(d.name, 'off')" />
                  </template>
                </span>
              </div>
            </div>
          </div>

          <div v-if="p.details?.ips?.length" class="mt-3">
            <SectionHead icon="lan" :label="t('servers.panel_ips')" level="h3" class="mb-1.5" />
            <div v-for="ip in p.details.ips" :key="ip.address" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
              <span class="ll-mono">{{ ip.address }}</span>
              <span class="text-[var(--ll-muted)]">{{ ip.interface }}</span>
              <!-- A machine behind NAT answers on an address it cannot see on
                   any interface of its own; both are worth showing. -->
              <span v-if="ip.public" class="ll-mono text-[var(--ll-muted)]">→ {{ ip.public }}</span>
              <Badge v-if="ip.main" class="ml-auto" tone="info">{{ t('servers.panel_ip_main') }}</Badge>
            </div>
          </div>

          <div v-if="p.details?.databases?.length" class="mt-3">
            <SectionHead icon="database" :label="t('servers.panel_databases')" level="h3" class="mb-1.5" />
            <div class="max-h-56 overflow-y-auto">
              <div v-for="db in p.details.databases" :key="db.name" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
                <span class="truncate font-mono">{{ db.name }}</span>
                <Badge tone="gray">{{ db.type }}</Badge>
                <span v-if="db.domain" class="ml-auto truncate text-[var(--ll-muted)]">{{ db.domain }}</span>
              </div>
            </div>
          </div>

          <div v-if="p.details?.settings && Object.keys(p.details.settings).length" class="mt-3">
            <SectionHead icon="tune" :label="t('servers.panel_settings')" level="h3" class="mb-1.5" />
            <div class="flex flex-wrap gap-1.5">
              <span v-for="(v, k) in p.details.settings" :key="k" class="rounded-full bg-black/[0.04] px-2 py-0.5 text-[0.7rem] dark:bg-white/[0.06]">
                <span class="text-[var(--ll-muted)]">{{ k }}</span> {{ v }}
              </span>
            </div>
          </div>

          <div v-if="p.details?.clients?.length" class="mt-3">
            <SectionHead icon="group" :label="t('servers.panel_customers')" level="h3" class="mb-1.5" />
            <div class="flex flex-wrap gap-1.5">
              <span v-for="c in p.details.clients" :key="c.name" class="rounded-full bg-black/[0.04] px-2 py-0.5 text-[0.7rem] dark:bg-white/[0.06]">
                {{ c.name }} <span class="text-[var(--ll-muted)]">{{ c.domains }}</span>
              </span>
            </div>
          </div>

          <div v-if="p.details?.extensions?.length" class="mt-3">
            <SectionHead icon="extension" :label="t('servers.panel_extensions')" level="h3" class="mb-1.5" />
            <p class="text-[0.7rem] text-[var(--ll-muted)]">{{ p.details.extensions.join(' · ') }}</p>
          </div>
        </div>
      </div>
    </Card>

    <!-- Kept apart from the detections on purpose. These are ports panels tend
         to use with nothing claiming them -- a lead to follow, not a finding. -->
    <Card v-if="data?.candidates.length" :body-class="'p-4'">
      <SectionHead icon="help" :label="t('servers.panel_candidates')" level="h2" class="mb-1" />
      <p class="mb-2 text-xs text-[var(--ll-muted)]">{{ t('servers.panel_candidates_hint') }}</p>

      <div v-for="c in data.candidates" :key="c.port" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
        <span class="ll-mono">{{ c.address }}:{{ c.port }}</span>
        <span v-if="c.process" class="ll-mono text-[var(--ll-muted)]">{{ c.process }}</span>
        <span class="ml-auto text-[var(--ll-muted)]">{{ c.hint }}</span>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, Badge } from '@spa/ui';
import SectionHead from '@spa/components/servers/SectionHead.vue';
import { useServersStore, type PanelStatus, type HostingPanel } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const props = defineProps<{ serverId: number }>();

const store = useServersStore();
const { success, error: toastError } = useToast();

const data = ref<PanelStatus | null>(null);
const busy = ref(false);
const acting = ref(false);
const error = ref('');

/**
 * Where a PHP version stands today.
 *
 * Only what is settled is claimed: anything below 8.2 has no support of any
 * kind left, 8.2 still gets security fixes into December 2026, and newer is
 * simply current. An unrecognised handler is left alone rather than guessed at.
 */
function phpState(version: string | null): string {
  if (!version) return '';
  const [major, minor] = version.split('.').map(Number);
  if (major < 8 || (major === 8 && minor < 2)) return 'eol';
  if (major === 8 && minor === 2) return 'security';

  return '';
}

function phpTone(version: string | null): string {
  const state = phpState(version);
  if (state === 'eol') return 'bg-red-500/10 text-red-600 dark:text-red-400';
  if (state === 'security') return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';

  return 'bg-black/[0.04] text-[var(--ll-muted)] dark:bg-white/[0.06]';
}

function fmtSize(mb: number): string {
  return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${mb} MB`;
}

async function load(): Promise<void> {
  busy.value = true;
  error.value = '';
  try {
    const res = await store.panels(props.serverId);
    if (!res.ok) {
      error.value = t('servers.status_fail');
      return;
    }
    data.value = res;
  } catch {
    error.value = t('servers.status_fail');
  } finally {
    busy.value = false;
  }
}

/**
 * Acting on a panel goes through the ordinary service action -- it is a systemd
 * unit like any other, and inventing a second command path for it would mean a
 * second set of verbs to keep honest.
 */
/**
 * Turn a website on, suspend it or disable it.
 *
 * Both ways of taking a site down are asked about: one of them is how a
 * customer finds out their site is gone.
 */
async function site(domain: string, action: 'on' | 'suspend' | 'off'): Promise<void> {
  if (acting.value) return;
  if (action !== 'on' && !await confirmAsk(t(`servers.panel_site_confirm_${action}`, { domain }), { danger: true })) return;

  acting.value = true;
  try {
    const res = await store.panelSiteAction(props.serverId, domain, action);
    if (res.ok) success(t('servers.files_done'));
    else toastError(res.output || t('common.error'));
    await load();
  } catch {
    toastError(t('common.error'));
  } finally {
    acting.value = false;
  }
}

async function act(panel: HostingPanel, action: 'start' | 'stop' | 'restart'): Promise<void> {
  if (!panel.unit || acting.value) return;
  // Stopping the panel takes its web interface with it, which is how most
  // people would get back in.
  if (action !== 'restart' && !await confirmAsk(t('servers.panel_confirm', { name: panel.name }), { danger: true })) return;

  acting.value = true;
  try {
    const res = await store.serviceAction(props.serverId, panel.unit, action);
    if (res.ok) success(t('servers.files_done'));
    else toastError(res.output || t('common.error'));
    await load();
  } catch {
    toastError(t('common.error'));
  } finally {
    acting.value = false;
  }
}

onMounted(load);
</script>
