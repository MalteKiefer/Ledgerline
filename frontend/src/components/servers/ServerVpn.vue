<template>
  <div class="space-y-4">
    <Card :body-class="'p-4'">
      <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="text-sm font-semibold">{{ t('servers.vpn_title') }}</h2>
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" @click="load">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="busy && !data" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <!-- Nothing found is an answer, not an empty screen. -->
      <p v-else-if="data && !data.providers.length" class="text-sm text-[var(--ll-muted)]">{{ t('servers.vpn_none') }}</p>

      <div v-else-if="data" class="space-y-4">
        <div v-for="p in data.providers" :key="p.id" class="rounded-lg border border-[var(--ll-border)] p-3">
          <div class="flex flex-wrap items-center gap-2">
            <Icon :name="p.connected ? 'vpn_lock' : 'vpn_key_off'" :size="20" :class="p.connected ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--ll-muted)]'" />
            <span class="font-medium">{{ p.name }}</span>
            <Badge :tone="p.connected ? 'success' : 'gray'">{{ p.connected ? t('servers.vpn_connected') : t('servers.vpn_disconnected') }}</Badge>
            <Badge v-if="p.unit" :tone="p.unit.active === 'active' ? 'success' : 'warning'">{{ p.unit.active }}</Badge>
            <span v-if="p.version" class="ll-mono text-xs text-[var(--ll-muted)]">{{ p.version }}</span>

            <div class="ml-auto flex items-center gap-1">
              <Btn variant="ghost" size="xs" icon="play_arrow" :disabled="acting" @click="act(p, 'up')">{{ t('servers.vpn_up') }}</Btn>
              <Btn variant="ghost" size="xs" icon="restart_alt" :disabled="acting" @click="act(p, 'restart')">{{ t('servers.vpn_restart') }}</Btn>
              <Btn variant="ghost" size="xs" icon="stop" class="text-red-600" :disabled="acting" @click="act(p, 'down')">{{ t('servers.vpn_down') }}</Btn>
            </div>
          </div>

          <!-- Address and hostname first: the two things somebody came here for. -->
          <div v-if="p.address || p.hostname" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <span v-if="p.address"><span class="text-[var(--ll-muted)]">{{ t('servers.vpn_address') }}</span> <span class="ll-mono">{{ p.address }}</span></span>
            <span v-if="p.hostname"><span class="text-[var(--ll-muted)]">{{ t('servers.vpn_hostname') }}</span> <span class="ll-mono">{{ p.hostname }}</span></span>
          </div>

          <div v-if="Object.keys(p.facts).length" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="(v, k) in p.facts" :key="k" class="rounded-full bg-black/[0.04] px-2 py-0.5 text-[0.7rem] dark:bg-white/[0.06]">
              <span class="text-[var(--ll-muted)]">{{ k }}</span> {{ v }}
            </span>
          </div>

          <!-- ZeroTier joins networks; the others do not have the concept. -->
          <div v-if="p.networks?.length" class="mt-3">
            <h3 :class="sectionCls">{{ t('servers.vpn_networks') }}</h3>
            <div v-for="n in p.networks" :key="n.id ?? ''" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
              <span class="ll-mono text-[var(--ll-muted)]">{{ n.id }}</span>
              <span class="font-medium">{{ n.name }}</span>
              <span v-if="n.address" class="ll-mono">{{ n.address }}</span>
              <Badge class="ml-auto" :tone="n.status === 'OK' ? 'success' : 'gray'">{{ n.status }}</Badge>
            </div>
          </div>

          <div v-if="p.interfaces?.length" class="mt-3">
            <h3 :class="sectionCls">{{ t('servers.vpn_interfaces') }}</h3>
            <div v-for="i in p.interfaces" :key="i.name" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
              <span class="font-mono font-medium">{{ i.name }}</span>
              <span class="text-[var(--ll-muted)]">:{{ i.port }}</span>
              <span class="ml-auto truncate font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ i.public_key }}</span>
            </div>
          </div>

          <div v-if="p.units?.length" class="mt-3">
            <h3 :class="sectionCls">{{ t('servers.vpn_tunnels') }}</h3>
            <div v-for="u in p.units" :key="u.name" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
              <span class="font-mono">{{ u.name }}</span>
              <Badge class="ml-auto" :tone="u.active === 'active' ? 'success' : 'gray'">{{ u.sub }}</Badge>
              <Btn variant="ghost" size="xs" icon="restart_alt" :disabled="acting" @click="act(p, 'restart', u.name)">{{ t('servers.vpn_restart') }}</Btn>
            </div>
          </div>

          <div v-if="p.peers.length" class="mt-3">
            <h3 :class="sectionCls">
              {{ t('servers.vpn_peers') }}
              <span class="font-normal text-[var(--ll-muted)]">{{ p.peers_connected }} / {{ p.peers_total }}</span>
            </h3>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <tbody>
                  <tr v-for="peer in p.peers" :key="peer.name ?? ''" class="border-b border-[var(--ll-border)] last:border-0">
                    <td class="py-1 pr-2">
                      <span class="inline-block h-2 w-2 rounded-full" :class="peer.status === 'Connected' ? 'bg-emerald-500' : 'bg-[var(--ll-muted)]/40'" />
                    </td>
                    <td class="max-w-[16rem] truncate py-1 pr-3 font-medium" :title="peer.name ?? ''">{{ peer.name }}</td>
                    <td class="py-1 pr-3 ll-mono text-[var(--ll-muted)]">{{ peer.address }}</td>
                    <!-- Relayed against direct: the difference between a hop
                         through somebody else's server and a straight line. -->
                    <td class="py-1 pr-3">
                      <Badge v-if="peer.route && peer.route !== '-'" :tone="peer.route === 'P2P' ? 'success' : 'gray'">{{ peer.route }}</Badge>
                    </td>
                    <td class="py-1 pr-3 tabular-nums text-[var(--ll-muted)]">
                      <template v-if="peer.rx || peer.tx">↓{{ fmtBytes(peer.rx) }} ↑{{ fmtBytes(peer.tx) }}</template>
                    </td>
                    <td class="py-1 text-right tabular-nums text-[var(--ll-muted)]">
                      <template v-if="peer.latency_ns">{{ Math.round(peer.latency_ns / 1000000) }} ms</template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, Btn, Icon, Badge } from '@spa/ui';
import { useServersStore, type VpnStatus, type VpnProvider } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const props = defineProps<{ serverId: number }>();
const { t } = useI18n();
const s = useServersStore();
const { success, error: fail } = useToast();

const sectionCls = 'mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]';

const data = ref<VpnStatus | null>(null);
const busy = ref(false);
const acting = ref(false);
const error = ref('');

async function load() {
  busy.value = true;
  error.value = '';
  try {
    const r = await s.vpn(props.serverId);
    data.value = r;
    if (!r.ok) error.value = r.error === 'unreachable' ? t('servers.status_fail') : t('servers.status_fail');
  } catch {
    error.value = t('servers.status_fail');
  } finally {
    busy.value = false;
  }
}

/**
 * Taking the overlay down usually takes this connection with it -- the app
 * reaches these hosts through one of these networks. Say so before, not after.
 */
async function act(provider: VpnProvider, action: 'up' | 'down' | 'restart', unit?: string) {
  if (action !== 'up') {
    const question = action === 'down' ? t('servers.vpn_confirm_down', { p: provider.name }) : t('servers.vpn_confirm_restart', { p: provider.name });
    if (!await confirmAsk(question)) return;
  }

  acting.value = true;
  try {
    const r = await s.vpnAction(props.serverId, { provider: provider.id, action, unit });
    if (r.ok) {
      success(t('servers.files_done'));
      // Straight after a restart the daemon is still settling, so a reload now
      // would show it as down and read like a failure.
      if (action !== 'down') setTimeout(() => void load(), 1500);
    } else {
      fail(r.output || t('servers.status_fail'));
    }
  } catch {
    fail(t('servers.status_fail'));
  } finally {
    acting.value = false;
  }
}

function fmtBytes(n: number | null): string {
  if (!n) return '0';
  const units = ['B', 'K', 'M', 'G', 'T'];
  let v = n;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }

  return `${v < 10 && i > 0 ? v.toFixed(1) : Math.round(v)}${units[i]}`;
}

onMounted(load);
</script>
