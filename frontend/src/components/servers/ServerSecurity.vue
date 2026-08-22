<template>
  <div class="space-y-6">
    <!-- What somebody scanning this page needs first: how much of this machine
         answers from outside, and whether anything here needs acting on. The
         detail below explains each figure; this band is the summary. -->
    <div v-if="sec" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <StatTile
        :label="t('servers.sec_exposed')"
        :value="String(sec.exposed.length)"
        :note="t('servers.sec_exposed_note', { n: String(sec.listening.length) })"
        icon="lan"
        :pct="sec.listening.length ? (sec.exposed.length / sec.listening.length) * 100 : 0"
        :warn-at="30"
        :danger-at="60"
      />
      <StatTile
        :label="t('servers.sec_findings')"
        :value="String(sshProblems.length)"
        :note="t('servers.sec_findings_note')"
        icon="key"
        :pct="sshProblems.length ? 100 : 0"
        :warn-at="1"
        :danger-at="100"
      />
      <StatTile
        :label="t('servers.sec_firewall_short')"
        :value="firewallVerdict"
        :note="t('servers.sec_firewall_note', { n: String(sec.firewalls.length) })"
        icon="shield"
      />
      <StatTile
        :label="t('servers.sec_hygiene')"
        :value="hygieneVerdict"
        :note="t('servers.sec_hygiene_note')"
        icon="verified_user"
      />
    </div>

    <!-- The attack surface, listed. A port bound to 127.0.0.1 is not reachable
         from anywhere else, so it is separated out rather than counted the
         same: conflating the two would turn every developer database into a
         finding. -->
    <Card v-if="sec && sec.listening.length" :body-class="'p-4'">
      <h2 class="mb-1 text-sm font-semibold">{{ t('servers.sec_surface') }}</h2>
      <p class="mb-3 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_surface_hint') }}</p>

      <div v-if="sec.addresses.length" class="mb-3 flex flex-wrap gap-1.5">
        <Badge v-for="a in sec.addresses" :key="a" tone="gray">{{ a }}</Badge>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-[var(--ll-border)] text-left text-[0.7rem] uppercase tracking-wide text-[var(--ll-muted)]">
              <th class="w-20 py-2 pr-3 font-semibold">{{ t('servers.sec_port') }}</th>
              <th class="w-24 py-2 pr-3 font-semibold">{{ t('servers.sec_proto') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('servers.sec_bound') }}</th>
              <th class="py-2 font-semibold">{{ t('servers.sec_process') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(l, i) in sortedPorts" :key="`${l.proto}-${l.address}-${l.port}-${i}`" class="border-b border-[var(--ll-border)] last:border-0">
              <td class="py-1.5 pr-3 font-mono text-xs font-semibold tabular-nums">{{ l.port }}</td>
              <td class="py-1.5 pr-3"><Badge tone="gray">{{ l.proto }}</Badge></td>
              <td class="py-1.5 pr-3">
                <span class="font-mono text-xs">{{ l.address }}</span>
                <Badge v-if="l.exposed" tone="warning" class="ml-2">{{ t('servers.sec_public') }}</Badge>
                <Badge v-else tone="success" class="ml-2">{{ t('servers.sec_local_only') }}</Badge>
              </td>
              <td class="truncate py-1.5 font-mono text-xs text-[var(--ll-muted)]">{{ l.process || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

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

    <!-- The judgements, not the raw settings: "PermitRootLogin yes" only means
         something to a reader who already knows it is a problem. -->
    <Card v-if="sec && Object.keys(sec.ssh).length" :body-class="'p-4'">
      <h2 class="mb-3 text-sm font-semibold">{{ t('servers.sec_ssh_findings') }}</h2>

      <!-- Nothing to report is a result, not an empty panel: hiding the card
           when a host is clean makes a working check look like a missing one. -->
      <p v-if="!sec.ssh_findings.length" class="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400">
        <Icon name="check_circle" :size="16" />
        {{ t('servers.sec_ssh_clean', { n: String(Object.keys(sec.ssh).length) }) }}
      </p>

      <div v-for="f in sec.ssh_findings" :key="f.key" class="flex items-start gap-2 border-b border-[var(--ll-border)] py-2 last:border-0">
        <Icon :name="f.level === 'danger' ? 'error' : f.level === 'warn' ? 'warning' : 'check_circle'" :size="16" class="mt-0.5 shrink-0" :class="levelClass(f.level)" />
        <div class="min-w-0">
          <div class="font-mono text-xs">{{ f.key }}</div>
          <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ f.note }}</div>
        </div>
      </div>
    </Card>

    <div v-if="sec && (sec.ssh_host_keys.length || sec.ssh_authorized.length)" class="grid gap-4 lg:grid-cols-2">
      <Card :body-class="'p-4'">
        <h2 class="mb-1 text-sm font-semibold">{{ t('servers.sec_host_keys') }}</h2>
        <p class="mb-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_host_keys_hint') }}</p>
        <div v-for="k in sec.ssh_host_keys" :key="k.fingerprint" class="border-b border-[var(--ll-border)] py-1.5 last:border-0">
          <div class="flex items-center gap-2">
            <Badge :tone="k.type === 'RSA' && k.bits < 3072 ? 'warning' : 'gray'">{{ k.type }}</Badge>
            <span class="text-xs tabular-nums text-[var(--ll-muted)]">{{ k.bits }} bit</span>
          </div>
          <div class="truncate font-mono text-[0.7rem] text-[var(--ll-muted)]" :title="k.fingerprint">{{ k.fingerprint }}</div>
        </div>
      </Card>

      <Card :body-class="'p-4'">
        <h2 class="mb-1 text-sm font-semibold">{{ t('servers.sec_authorized') }}</h2>
        <p class="mb-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_authorized_hint') }}</p>
        <div v-for="a in sec.ssh_authorized" :key="a.path" class="flex items-center justify-between gap-2 border-b border-[var(--ll-border)] py-1.5 last:border-0">
          <span class="truncate font-mono text-xs" :title="a.path">{{ a.path }}</span>
          <Badge :tone="a.keys > 4 ? 'warning' : 'gray'">{{ t('servers.sec_keys_n', { n: String(a.keys) }) }}</Badge>
        </div>
        <p v-if="!sec.ssh_authorized.length" class="py-2 text-xs text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </Card>
    </div>

    <div v-if="sec && (sec.web.length || sec.certificates.length)" class="grid gap-4 lg:grid-cols-2">
      <Card v-if="sec.web.length" :body-class="'p-4'">
        <h2 class="mb-3 text-sm font-semibold">{{ t('servers.sec_web') }}</h2>
        <div v-for="w in sec.web" :key="w.name" class="flex items-center justify-between gap-2 border-b border-[var(--ll-border)] py-1.5 last:border-0">
          <div>
            <div class="text-sm font-medium">{{ w.name }}</div>
            <div class="font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ w.version || '—' }}</div>
          </div>
          <Badge :tone="w.active === 'active' ? 'success' : 'gray'">{{ w.active || '—' }}</Badge>
        </div>
      </Card>

      <Card v-if="sec.certificates.length" :body-class="'p-4'">
        <h2 class="mb-1 text-sm font-semibold">{{ t('servers.sec_certs') }}</h2>
        <p class="mb-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_certs_hint') }}</p>
        <div v-for="c in sec.certificates" :key="c.path" class="border-b border-[var(--ll-border)] py-1.5 last:border-0">
          <div class="truncate font-mono text-xs" :title="c.path">{{ c.path }}</div>
          <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ c.expires }}</div>
        </div>
      </Card>
    </div>

    <div v-if="sec && (Object.keys(sec.sysctl).length || sec.sudoers_nopasswd.length || sec.accounts.uid_zero.length || sec.accounts.empty_password.length)" class="grid gap-4 lg:grid-cols-2">
      <Card v-if="Object.keys(sec.sysctl).length" :body-class="'p-4'">
        <h2 class="mb-1 text-sm font-semibold">{{ t('servers.sec_kernel') }}</h2>
        <p class="mb-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.sec_kernel_hint') }}</p>
        <div v-for="(v, k) in sec.sysctl" :key="k" class="flex justify-between gap-3 border-b border-[var(--ll-border)] py-1 last:border-0">
          <span class="truncate font-mono text-[0.7rem] text-[var(--ll-muted)]" :title="String(k)">{{ k }}</span>
          <span class="font-mono text-xs" :class="sysctlTone(String(k), v)">{{ v }}</span>
        </div>
      </Card>

      <Card :body-class="'p-4'">
        <h2 class="mb-3 text-sm font-semibold">{{ t('servers.sec_accounts') }}</h2>

        <!-- A second uid 0 account is a full root login under another name;
             an empty password is one anybody can use. Both are listed
             plainly because either is a finding on its own. -->
        <div v-if="sec.accounts.uid_zero.filter((u) => u !== 'root').length" class="mb-2">
          <div class="text-xs font-semibold text-red-600 dark:text-red-400">{{ t('servers.sec_uid_zero') }}</div>
          <div class="font-mono text-xs">{{ sec.accounts.uid_zero.join(', ') }}</div>
        </div>
        <div v-if="sec.accounts.empty_password.length" class="mb-2">
          <div class="text-xs font-semibold text-red-600 dark:text-red-400">{{ t('servers.sec_empty_pw') }}</div>
          <div class="font-mono text-xs">{{ sec.accounts.empty_password.join(', ') }}</div>
        </div>
        <div v-if="sec.sudoers_nopasswd.length">
          <div class="text-xs font-semibold text-amber-600 dark:text-amber-400">{{ t('servers.sec_nopasswd') }}</div>
          <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-black/[0.05] p-2 font-mono text-[0.7rem] dark:bg-white/5">{{ sec.sudoers_nopasswd.join('\n') }}</pre>
        </div>
        <p
          v-if="!sec.accounts.uid_zero.filter((u) => u !== 'root').length && !sec.accounts.empty_password.length && !sec.sudoers_nopasswd.length"
          class="text-sm text-[var(--ll-muted)]"
        >{{ t('servers.sec_accounts_clean') }}</p>
      </Card>
    </div>

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
import { Badge, Btn, Card, Icon, Select } from '@spa/ui';
import { ApiError } from '@spa/api/client';
import { useServersStore, type BanList, type SecurityAudit } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import StatTile from './StatTile.vue';

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

/** Only the settings worth acting on; the clean ones do not need a row. */
const sshProblems = computed(() => (sec.value?.ssh_findings ?? []).filter((f) => f.level !== 'ok'));

const sortedPorts = computed(() =>
  // Exposed first, then by port: the reader is looking for what answers from
  // outside, not for a numeric listing.
  [...(sec.value?.listening ?? [])].sort((a, b) => Number(b.exposed) - Number(a.exposed) || a.port - b.port),
);

const firewallVerdict = computed(() => {
  const fws = sec.value?.firewalls ?? [];
  if (!fws.length) return t('servers.sec_none_short');
  if (fws.some((f) => f.active === true)) return t('servers.sec_active');
  if (fws.every((f) => !f.readable)) return t('servers.sec_unreadable');

  return t('servers.sec_inactive');
});

const hygieneVerdict = computed(() => {
  const a = sec.value?.accounts;
  const extraRoot = (a?.uid_zero ?? []).filter((u) => u !== 'root').length;
  if (extraRoot || (a?.empty_password ?? []).length) return t('servers.sec_problem');
  if ((sec.value?.sudoers_nopasswd ?? []).length) return t('servers.sec_check');

  return t('servers.sec_clean');
});

const levelClass = (level: string) => ({
  danger: 'text-red-600 dark:text-red-400',
  warn: 'text-amber-600 dark:text-amber-400',
}[level] ?? 'text-emerald-600 dark:text-emerald-400');

/**
 * A handful of kernel switches where the safe value is not a matter of taste.
 * Everything else is shown without a judgement rather than guessed at.
 */
const sysctlTone = (key: string, value: string) => {
  const good: Record<string, string[]> = {
    'kernel.randomize_va_space': ['2'],
    'net.ipv4.conf.all.rp_filter': ['1', '2'],
    'net.ipv4.conf.all.accept_redirects': ['0'],
    'net.ipv4.conf.all.accept_source_route': ['0'],
    'net.ipv4.tcp_syncookies': ['1'],
    'kernel.kptr_restrict': ['1', '2'],
    'kernel.dmesg_restrict': ['1'],
  };
  const want = good[key];
  if (!want) return 'text-[var(--ll-text)]';

  return want.includes(value.trim())
    ? 'text-emerald-600 dark:text-emerald-400'
    : 'text-amber-600 dark:text-amber-400';
};

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
