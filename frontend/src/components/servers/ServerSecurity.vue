<template>
  <div class="space-y-6">
    <Card :body-class="'p-4'">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold">{{ t('servers.sec_firewalls') }}</h2>
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="secBusy" @click="loadSecurity">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="secBusy && !sec" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <p v-else-if="secError" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ secError }}</p>

      <template v-else-if="sec">
        <p v-if="!sec.firewalls.length" class="text-sm text-[var(--ll-muted)]">{{ t('servers.sec_no_firewall') }}</p>

        <!-- Several layers on purpose: nftables underneath, iptables-nft on
             top, ufw or firewalld driving either. Naming only the first would
             hide the one actually deciding. -->
        <div v-for="f in sec.firewalls" :key="f.name" class="mb-2 rounded-lg border border-[var(--ll-border)] p-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium">{{ f.name }}</span>
            <Badge v-if="!f.readable" tone="warning">{{ t('servers.sec_unreadable') }}</Badge>
            <Badge v-else-if="f.active === true" tone="success">{{ t('servers.sec_active') }}</Badge>
            <Badge v-else-if="f.active === false" tone="gray">{{ t('servers.sec_inactive') }}</Badge>
            <span v-if="f.readable" class="text-xs text-[var(--ll-muted)]">{{ f.summary }}</span>
          </div>
          <p v-if="!f.readable" class="mt-1 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_unreadable_hint') }}</p>
          <details v-else-if="f.detail" class="mt-2">
            <summary class="cursor-pointer text-xs text-[var(--ll-muted)]">{{ t('servers.sec_rules') }}</summary>
            <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-black/[0.05] p-2 font-mono text-[0.7rem] dark:bg-white/5">{{ f.detail }}</pre>
          </details>
        </div>
      </template>
    </Card>

    <Card v-if="sec && sec.bans.length" :body-class="'p-4'">
      <h2 class="mb-3 text-sm font-semibold">{{ t('servers.sec_bans') }}</h2>
      <div v-for="b in sec.bans" :key="b.name" class="mb-2 rounded-lg border border-[var(--ll-border)] p-3">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-medium">{{ b.name }}</span>
          <Badge v-if="!b.readable" tone="warning">{{ t('servers.sec_unreadable') }}</Badge>
          <span v-else class="text-xs text-[var(--ll-muted)]">{{ b.summary }}</span>
        </div>
        <details v-if="b.readable && b.detail" class="mt-2">
          <summary class="cursor-pointer text-xs text-[var(--ll-muted)]">{{ t('common.details') }}</summary>
          <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-black/[0.05] p-2 font-mono text-[0.7rem] dark:bg-white/5">{{ b.detail }}</pre>
        </details>
      </div>
    </Card>

    <!-- Bans. Both daemons side by side rather than one treated as "the"
         ban list: a host often runs fail2ban for sshd and CrowdSec for the
         web tier, and an address banned by one is unknown to the other. -->
    <Card :body-class="'p-4'">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold">{{ t('servers.tab_bans') }}</h2>
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="banBusy" @click="loadBans">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="banBusy && !bans" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <p v-else-if="banError" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ banError }}</p>

      <template v-else-if="bans">
        <p v-if="!bans.fail2ban.length && !bans.crowdsec.length" class="text-sm text-[var(--ll-muted)]">{{ t('servers.bans_none') }}</p>

        <div v-for="jail in bans.fail2ban" :key="jail.jail" class="mb-3">
          <div class="mb-1 flex items-center gap-2 text-xs">
            <span class="font-semibold">fail2ban</span>
            <span class="font-mono text-[var(--ll-muted)]">{{ jail.jail }}</span>
            <span class="text-[var(--ll-muted)]">({{ jail.ips.length }})</span>
          </div>
          <div v-for="ip in jail.ips" :key="ip" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1.5 last:border-0">
            <span class="font-mono text-xs">{{ ip }}</span>
            <div class="ml-auto flex gap-1">
              <Btn variant="ghost" size="sm" :disabled="banActing" @click="doBan('fail2ban', 'unban', ip, jail.jail)">{{ t('servers.ban_unban') }}</Btn>
              <Btn variant="ghost" size="sm" :disabled="banActing" @click="doBan('fail2ban', 'allow', ip, jail.jail)">{{ t('servers.ban_allow') }}</Btn>
            </div>
          </div>
        </div>

        <div v-if="bans.crowdsec.length" class="mb-3">
          <div class="mb-1 text-xs font-semibold">CrowdSec</div>
          <div v-for="(d, i) in bans.crowdsec" :key="`${d.ip}-${i}`" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1.5 last:border-0">
            <span class="font-mono text-xs">{{ d.ip }}</span>
            <span v-if="d.reason" class="truncate text-[0.7rem] text-[var(--ll-muted)]">{{ d.reason }}</span>
            <span v-if="d.expires" class="text-[0.7rem] text-[var(--ll-muted)]">{{ d.expires }}</span>
            <div class="ml-auto flex gap-1">
              <Btn variant="ghost" size="sm" :disabled="banActing" @click="doBan('crowdsec', 'unban', d.ip)">{{ t('servers.ban_unban') }}</Btn>
              <Btn variant="ghost" size="sm" :disabled="banActing" @click="doBan('crowdsec', 'allow', d.ip)">{{ t('servers.ban_allow') }}</Btn>
            </div>
          </div>
        </div>

        <!-- Banning by hand. The jail matters for fail2ban and does not exist
             for CrowdSec, so the field appears only where it applies. -->
        <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-[var(--ll-border)] pt-3">
          <Select v-model="banDaemon" class="w-36" :label="t('servers.tab_bans')" :options="banDaemonOptions" />
          <Select
            v-if="banDaemon === 'fail2ban'"
            v-model="banJail"
            class="w-40"
            :label="t('servers.ban_jail')"
            :options="jailOptions"
          />
          <label class="w-48">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.ban_ip') }}</span>
            <input v-model="banIp" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 font-mono text-sm">
          </label>
          <Btn variant="solid" size="sm" :disabled="banActing || !banIp" class="mb-0.5" @click="doBan(banDaemon, 'ban', banIp, banJail)">{{ t('servers.ban_add') }}</Btn>
          <Btn variant="ghost" size="sm" :disabled="banActing || !banIp" class="mb-0.5" @click="doBan(banDaemon, 'allow', banIp, banJail)">{{ t('servers.ban_allow') }}</Btn>
        </div>

        <div v-if="banNote" class="mt-3 rounded-lg px-3 py-2 text-sm" :class="banNoteOk ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'">
          {{ banNote }}
        </div>
      </template>
    </Card>

    <Card v-if="sec && Object.keys(sec.ssh).length" :body-class="'p-4'">
      <h2 class="mb-1 text-sm font-semibold">{{ t('servers.sec_ssh') }}</h2>
      <!-- From sshd's own resolved configuration, not sshd_config: an Include
           or a Match block makes the file and the running server disagree. -->
      <p class="mb-3 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_ssh_hint') }}</p>
      <div class="space-y-1 text-sm">
        <div v-for="(v, k) in sec.ssh" :key="k" class="flex justify-between gap-3">
          <span class="font-mono text-xs text-[var(--ll-muted)]">{{ k }}</span>
          <span class="font-mono text-xs" :class="sshTone(String(k), v)">{{ v }}</span>
        </div>
      </div>
    </Card>

    <Card v-if="sec" :body-class="'p-4'">
      <h2 class="mb-3 text-sm font-semibold">{{ t('servers.sec_updates') }}</h2>
      <div class="space-y-1 text-sm">
        <div class="flex justify-between gap-3">
          <span class="text-[var(--ll-muted)]">{{ t('servers.sec_unattended') }}</span>
          <Badge :tone="sec.updates.unattended ? 'success' : 'gray'">{{ sec.updates.unattended ? t('common.yes') : t('common.none') }}</Badge>
        </div>
        <div class="flex justify-between gap-3">
          <span class="text-[var(--ll-muted)]">{{ t('servers.reboot_required') }}</span>
          <!-- Amber on a grey row reads as decoration. A pending reboot is
               the one line here somebody has to act on, so it carries the
               weight. -->
          <span
            v-if="sec.updates.reboot_required"
            class="rounded-md bg-amber-500/15 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300"
          >{{ t('servers.reboot_required_badge') }}</span>
          <Badge v-else tone="gray">{{ t('common.none') }}</Badge>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Select } from '@spa/ui';
import { ApiError } from '@spa/api/client';
import { useServersStore, type BanList, type SecurityAudit } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';

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

// ---- security ----

const sec = ref<SecurityAudit | null>(null);
const secBusy = ref(false);
const secError = ref('');

async function loadSecurity() {
  const id = props.serverId;
  secBusy.value = true;
  secError.value = '';
  try {
    const r = await s.security(id);
    sec.value = r;
    if (!r.ok) secError.value = errorText(r.error);
  } catch {
    secError.value = t('servers.status_fail');
  } finally {
    secBusy.value = false;
  }
}

/**
 * Colour only the settings where one value is plainly worse than the other.
 * Everything else stays neutral rather than implying a judgement we have not
 * earned — a non-standard port is not insecure, it is just non-standard.
 */
function sshTone(key: string, value: string): string {
  const bad =
    (key === 'permitrootlogin' && value === 'yes') ||
    (key === 'passwordauthentication' && value === 'yes') ||
    (key === 'permitemptypasswords' && value === 'yes');
  const good =
    (key === 'permitrootlogin' && (value === 'no' || value === 'prohibit-password')) ||
    (key === 'passwordauthentication' && value === 'no') ||
    (key === 'pubkeyauthentication' && value === 'yes');

  if (bad) return 'text-amber-600 dark:text-amber-400';

  return good ? 'text-emerald-600 dark:text-emerald-400' : '';
}

// ---- bans ----

const bans = ref<BanList | null>(null);
const banBusy = ref(false);
const banError = ref('');
const banActing = ref(false);
const banNote = ref('');
const banNoteOk = ref(true);
const banDaemon = ref<'fail2ban' | 'crowdsec'>('fail2ban');
const banJail = ref('');
const banIp = ref('');

const banDaemonOptions = [
  { title: 'fail2ban', value: 'fail2ban' },
  { title: 'CrowdSec', value: 'crowdsec' },
];

/** Only jails the host reported — the browser never invents one. */
const jailOptions = computed(() => (bans.value?.fail2ban ?? []).map((j) => ({ title: j.jail, value: j.jail })));

async function loadBans() {
  const id = props.serverId;
  banBusy.value = true;
  banError.value = '';
  try {
    const r = await s.bans(id);
    bans.value = r;
    if (!r.ok) banError.value = errorText(r.error);
    if (!banJail.value && r.fail2ban.length) banJail.value = r.fail2ban[0]!.jail;
  } catch {
    banError.value = t('servers.status_fail');
  } finally {
    banBusy.value = false;
  }
}

async function doBan(daemon: 'fail2ban' | 'crowdsec', action: 'unban' | 'ban' | 'allow', ip: string, jail = '') {

  banActing.value = true;
  banNote.value = '';
  try {
    const r = await s.banAction(props.serverId, { daemon, action, ip, jail: jail || undefined });
    // fail2ban has no runtime allow-list, so "allow" only unbans. Saying so is
    // the difference between a working button and a lie the next restart shows.
    banNoteOk.value = r.ok && r.error !== 'f2b_allow_is_manual';
    banNote.value = r.error === 'f2b_allow_is_manual'
      ? t('servers.ban_f2b_allow_manual')
      : r.ok ? t('servers.ban_done') : (r.output || errorText(r.error));
    if (r.ok) await loadBans();
  } catch (e) {
    banNoteOk.value = false;
    banNote.value = e instanceof ApiError && typeof e.body === 'object' && e.body !== null && 'error' in e.body
      ? errorText(String((e.body as { error: unknown }).error))
      : t('servers.status_fail');
  } finally {
    banActing.value = false;
  }
}

onMounted(() => { void loadSecurity(); void loadBans(); });
</script>
