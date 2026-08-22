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

    <!-- Tabs. The page had grown into one long scroll; logs and the terminal
         are separate concerns and do not belong stacked under the metrics. -->
    <div class="flex gap-1 border-b border-[var(--ll-border)]">
      <button
        v-for="tb in tabs"
        :key="tb.id"
        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="tab === tb.id ? 'border-[var(--ll-accent)] text-[var(--ll-accent)]' : 'border-transparent text-[var(--ll-muted)] hover:text-[var(--ll-text)]'"
        @click="setTab(tb.id)"
      >
        {{ tb.label }}
      </button>
    </div>

    <template v-if="tab === 'overview'">
    <template v-if="facts">
      <!-- Headline figures. These four answer "is anything wrong" at a glance. -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.cpu') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.cpu.used_pct ?? '—' }}%</div>
          <div class="mt-0.5 truncate text-[0.7rem] text-[var(--ll-muted)]">{{ facts.cpu.cores ? t('servers.cores', { n: String(facts.cpu.cores) }) : '—' }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.load') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.load[0]?.toFixed(2) ?? '—' }}</div>
          <div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ loadNote }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.memory') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.mem.used_pct ?? '—' }}%</div>
          <div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ memoryNote(facts) }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.disks') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.disk_max_pct ?? '—' }}%</div>
          <div class="mt-0.5 truncate text-[0.7rem] text-[var(--ll-muted)]">{{ fullestDisk(facts)?.mount ?? '—' }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.uptime') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ formatUptime(facts.uptime_s) }}</div>
          <div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ facts.boot_at ? fmtDateTime(facts.boot_at) : '' }}</div>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- System -->
        <Card :title="t('servers.section_system')" :body-class="'p-4'">
          <dl class="space-y-1.5 text-xs">
            <Row :label="t('servers.hostname')" :value="facts.hostname" />
            <Row :label="t('servers.os')" :value="facts.os.name" />
            <Row :label="t('servers.kernel')" :value="facts.kernel" />
            <Row :label="t('servers.arch')" :value="facts.arch" />
            <Row :label="t('servers.cpu')" :value="cpuText(facts)" />
            <Row v-if="facts.virt" :label="t('servers.virt')" :value="facts.virt" />
            <Row v-if="facts.temp_c != null" :label="t('servers.temperature')" :value="`${facts.temp_c} °C`" />
            <Row :label="t('servers.updates')" :value="facts.updates === null ? t('servers.updates_unknown') : String(facts.updates)" />
            <Row v-if="facts.reboot_required" :label="t('servers.reboot_required')" :value="t('common.yes')" />
          </dl>
        </Card>

        <!-- Storage + memory meters -->
        <Card class="lg:col-span-2" :title="t('servers.section_capacity')" :body-class="'p-4'">
          <Meter :label="t('servers.memory')" :pct="facts.mem.used_pct" :note="memoryNote(facts)" />
          <Meter v-if="facts.mem.swap_total_kb" class="mt-2.5" :label="t('servers.swap')" :pct="swapPct(facts)" :note="swapNote(facts)" />
          <div v-for="d in facts.disks" :key="d.mount" class="mt-2.5">
            <Meter :label="d.mount" :pct="d.used_pct" :note="diskNote(d)" />
            <p class="mt-0.5 font-mono text-[0.65rem] text-[var(--ll-muted)]">{{ d.fs }}</p>
          </div>
          <p v-if="!facts.disks.length" class="text-xs text-[var(--ll-muted)]">{{ t('common.none') }}</p>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Network -->
        <Card v-if="facts.addresses.length || facts.ports.length" :title="t('servers.section_network')" :body-class="'p-4'">
          <template v-if="facts.addresses.length">
            <h3 class="mb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.addresses') }}</h3>
            <div class="mb-3 space-y-0.5 font-mono text-xs">
              <div v-for="a in facts.addresses" :key="a">{{ a }}</div>
            </div>
          </template>

          <!-- Routing and resolution: the two things you check first when a host
               is up but cannot reach anything. -->
          <template v-if="facts.network?.gateway || facts.network?.dns?.length">
            <h3 class="mb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.routing') }}</h3>
            <div class="mb-3 space-y-0.5 text-xs">
              <div v-if="facts.network.gateway" class="flex justify-between gap-3">
                <span class="text-[var(--ll-muted)]">{{ t('servers.gateway') }}</span>
                <span class="font-mono">{{ facts.network.gateway }}</span>
              </div>
              <div v-if="facts.network.dns.length" class="flex justify-between gap-3">
                <span class="text-[var(--ll-muted)]">{{ t('servers.dns') }}</span>
                <span class="text-right font-mono">{{ facts.network.dns.join(', ') }}</span>
              </div>
              <div v-if="facts.network.search" class="flex justify-between gap-3">
                <span class="text-[var(--ll-muted)]">{{ t('servers.dns_search') }}</span>
                <span class="text-right font-mono">{{ facts.network.search }}</span>
              </div>
            </div>
          </template>

          <!-- Per interface: what it is, whether it is up, what it carries.
               A Docker or libvirt host is mostly bridges and veth pairs, and a
               list that calls them all "interface" hides which one matters. -->
          <template v-if="facts.network?.interfaces?.length">
            <h3 class="mb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.interfaces') }}</h3>
            <div class="mb-3 space-y-2">
              <div v-for="n in facts.network.interfaces" :key="n.name" class="rounded-lg border border-[var(--ll-border)] p-2">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-mono text-xs font-medium">{{ n.name }}</span>
                  <Badge v-if="n.kind" tone="gray">{{ t(`servers.if_${n.kind}`) }}</Badge>
                  <Badge v-if="n.up === false" tone="warning">{{ t('servers.if_down') }}</Badge>
                  <span class="ml-auto font-mono text-[0.7rem] tabular-nums text-[var(--ll-muted)]">
                    ↓ {{ formatGib(n.rx_bytes / 1024) }} · ↑ {{ formatGib(n.tx_bytes / 1024) }}
                  </span>
                </div>
                <div v-if="n.addresses?.length" class="mt-1 font-mono text-[0.7rem]">{{ n.addresses.join(', ') }}</div>
                <div class="mt-0.5 flex flex-wrap gap-3 text-[0.7rem] text-[var(--ll-muted)]">
                  <span v-if="n.gateway">{{ t('servers.gateway') }}: <span class="font-mono">{{ n.gateway }}</span></span>
                  <span v-if="n.dns?.length">DNS: <span class="font-mono">{{ n.dns.join(', ') }}</span></span>
                  <span v-if="n.mtu">MTU {{ n.mtu }}</span>
                  <span v-if="n.mac" class="font-mono">{{ n.mac }}</span>
                </div>
              </div>
            </div>
          </template>

          <template v-if="facts.ports.length">
            <h3 class="mb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.ports') }}</h3>
            <div class="flex flex-wrap gap-1.5">
              <Badge v-for="p in facts.ports" :key="p" tone="gray">{{ p }}</Badge>
            </div>
          </template>
        </Card>

        <!-- Processes -->
        <Card v-if="facts.processes.length" :title="t('servers.section_processes')" :body-class="'p-4'">
          <div class="space-y-1 text-xs">
            <div v-for="proc in facts.processes" :key="proc.name" class="flex justify-between gap-3">
              <span class="truncate font-mono">{{ proc.name }}</span>
              <span class="shrink-0 tabular-nums text-[var(--ll-muted)]">{{ formatGib(proc.rss_kb) }}</span>
            </div>
          </div>
          <p class="mt-2 text-[0.65rem] text-[var(--ll-muted)]">{{ t('servers.processes_note') }}</p>
        </Card>

    <!-- Services -->
        <Card v-if="facts.failed_units.length" :title="t('servers.failed_units')" :body-class="'p-4'">
          <div class="flex flex-wrap gap-1.5">
            <Badge v-for="u in facts.failed_units" :key="u" tone="error">{{ u }}</Badge>
          </div>
        </Card>

        <!-- Sessions -->
        <Card v-if="facts.sessions.length" :title="t('servers.section_sessions')" :body-class="'p-4'">
          <div class="space-y-1">
            <div v-for="(ses, i) in facts.sessions" :key="i" class="flex items-center gap-2 font-mono text-xs">
              <span class="font-semibold">{{ ses.user }}</span>
              <span class="text-[var(--ll-muted)]">{{ ses.tty }}</span>
              <span class="text-[var(--ll-muted)]">{{ ses.since }}</span>
              <span v-if="ses.from" class="text-[var(--ll-muted)]">({{ ses.from }})</span>
              <Btn
                v-if="killable(ses.tty)"
                variant="ghost"
                size="sm"
                icon="logout"
                class="ml-auto"
                :title="t('servers.session_kill')"
                @click="killSession(ses)"
              />
            </div>
          </div>
        </Card>
      </div>

      <!-- Containers -->
      <Card v-if="facts.containers.length" :title="t('servers.containers')" :body-class="'p-0'">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="c in facts.containers" :key="c.name" class="border-b border-[var(--ll-border)] last:border-0">
                <td class="px-4 py-2 font-mono">{{ c.name }}</td>
                <td class="px-4 py-2 text-right text-[var(--ll-muted)]">
                  <Badge :tone="c.status.startsWith('Up') ? 'success' : 'warning'">{{ c.status }}</Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </template>

    <!-- Reachability: pings and port checks, sampled every few minutes -->
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

    <!-- History -->
    <Card v-if="trend.length > 1" :title="t('servers.history')" :body-class="'p-4'">
      <div class="-ml-1"><Chart :data="chartData" :options="chartOptions" :height="180" /></div>
      <div class="mt-2 flex gap-4 text-[0.7rem] text-[var(--ll-muted)]">
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="{ background: CHART_INK }" />{{ t('servers.memory') }}</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="{ background: CHART_WARN }" />{{ t('servers.disks') }}</span>
      </div>
    </Card>

    <p v-if="server.note" class="whitespace-pre-line rounded-lg border border-[var(--ll-border)] p-3 text-sm">{{ server.note }}</p>

    </template>

    <!-- Logs -->
    <template v-else-if="tab === 'logs'">
      <Card :body-class="'p-4'">
        <div class="flex flex-wrap items-end gap-3">
          <Select v-model="logSource" class="w-44" :label="t('servers.log_source')" :options="sourceOptions" />

          <!-- Every option below came from the host itself. The browser picks
               from that answer rather than naming something of its own. -->
          <Select
            v-if="logSource === 'journal' && sources?.units.length"
            v-model="logUnit"
            class="w-60"
            :label="t('servers.log_unit')"
            :options="[{ title: t('servers.log_all_units'), value: '' }, ...sources.units.map((u) => ({ title: u, value: u }))]"
          />

          <Select
            v-if="logSource === 'docker'"
            v-model="logContainer"
            class="w-60"
            :label="t('servers.containers')"
            :options="(sources?.containers ?? []).map((c) => ({ title: c, value: c }))"
          />

          <Select
            v-if="logSource === 'file'"
            v-model="logPath"
            class="w-72"
            :label="t('servers.log_file')"
            :options="(sources?.files ?? []).map((f) => ({ title: f, value: f }))"
          />

          <label class="w-24">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.log_lines') }}</span>
            <input v-model.number="logLines" type="number" min="1" max="2000" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
          </label>

          <label v-if="logSource === 'journal'" class="flex items-center gap-2 pb-2 text-sm">
            <input v-model="logErrorsOnly" type="checkbox" class="accent-primary-500">{{ t('servers.log_errors_only') }}
          </label>

          <Btn variant="solid" size="sm" icon="download" :disabled="logBusy" class="mb-0.5" @click="fetchLog">
            {{ logBusy ? t('servers.log_loading') : t('servers.log_fetch') }}
          </Btn>
          <Btn v-if="logText" variant="ghost" size="sm" icon="content_copy" class="mb-0.5" @click="copyLog">{{ t('common.copy') }}</Btn>
        </div>

        <p v-if="sourcesError" class="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">{{ t('servers.log_sources_failed') }}</p>
        <p v-else-if="!sources" class="mt-3 text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
        <p v-else-if="!hasAnySource" class="mt-3 text-sm text-[var(--ll-muted)]">{{ t('servers.log_none_available') }}</p>

        <p v-if="logError" class="mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ logError }}</p>

        <!-- Filtering happens here, not on the host: the lines are already
             fetched, and a second round trip to grep them would be slower and
             would lose the surrounding context on the next search. -->
        <div v-if="logText" class="mt-3 flex flex-wrap items-center gap-2">
          <input
            v-model="logQuery"
            :placeholder="t('servers.search_placeholder')"
            class="w-72 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
          >
          <label class="flex items-center gap-2 text-xs"><input v-model="logInvert" type="checkbox" class="accent-primary-500">{{ t('servers.log_invert') }}</label>
          <label class="flex items-center gap-2 text-xs"><input v-model="logWrap" type="checkbox" class="accent-primary-500">{{ t('servers.log_wrap') }}</label>
          <span class="text-xs text-[var(--ll-muted)]">{{ t('servers.log_match_count', { shown: String(logShown), total: String(logTotal) }) }}</span>
        </div>

        <pre
          v-if="logText"
          class="mt-2 max-h-[32rem] overflow-auto rounded-lg bg-black/[0.05] p-3 font-mono text-[0.7rem] leading-relaxed dark:bg-white/5"
          :class="logWrap ? 'whitespace-pre-wrap break-all' : ''"
        >{{ filteredLog }}</pre>
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
    </template>

    <!-- Services -->
    <template v-else-if="tab === 'services'">
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
    </template>

    <!-- Processes -->
    <template v-else-if="tab === 'processes'">
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
  severity, formatUptime, formatGib, memoryNote, swapPct, swapNote, diskNote, fullestDisk,
} from '@spa/lib/server-facts';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';
import { confirmAsk } from '@spa/composables/useConfirm';
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

/** A labelled bar; the same one the list view uses. */
const Meter = (props: { label: string; pct: number | null; note?: string }) => h('div', {}, [
  h('div', { class: 'flex items-baseline justify-between gap-2 text-xs' }, [
    h('span', { class: 'truncate' }, props.label),
    h('span', { class: 'shrink-0 font-mono tabular-nums text-[var(--ll-muted)]' }, props.note ?? (props.pct === null ? '—' : `${props.pct}%`)),
  ]),
  h('div', { class: 'mt-1 h-1.5 overflow-hidden rounded-full bg-black/[0.07] dark:bg-white/10' }, [
    h('div', {
      class: ['h-full rounded-full', props.pct === null ? '' : props.pct >= 90 ? 'bg-red-500' : props.pct >= 75 ? 'bg-amber-500' : 'bg-primary-500'],
      style: { width: `${Math.min(100, Math.max(0, props.pct ?? 0))}%` },
    }),
  ]),
]);
Meter.props = { label: String, pct: { type: Number as unknown as PropType<number | null>, default: null }, note: String };

const Row = (props: { label: string; value?: string | null }) => h('div', { class: 'flex justify-between gap-3' }, [
  h('dt', { class: 'shrink-0 text-[var(--ll-muted)]' }, props.label),
  h('dd', { class: 'truncate text-right' }, props.value || '—'),
]);
Row.props = { label: String, value: String };

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

function cpuText(f: ServerFacts): string {
  const cores = f.cpu.cores === null ? '' : t('servers.cores', { n: String(f.cpu.cores) });
  return [f.cpu.model, cores].filter(Boolean).join(' · ');
}

/** Load only means something against the core count. */
const loadNote = computed(() => {
  const f = facts.value;
  if (!f || f.load.length === 0) return '';
  const per = f.cpu.cores ? ` · ${Math.round((f.load[0] / f.cpu.cores) * 100)}%` : '';
  return `${f.load.map((l) => l.toFixed(2)).join('  ')}${per}`;
});

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

// ---- security ----

const sec = ref<SecurityAudit | null>(null);
const secBusy = ref(false);
const secError = ref('');

async function loadSecurity() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
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

// ---- log filtering ----

const logQuery = ref('');
const logInvert = ref(false);
const logWrap = ref(false);

const logLinesArr = computed(() => (logText.value ? logText.value.split('\n') : []));

const filteredLogLines = computed(() => {
  const q = logQuery.value.trim().toLowerCase();
  if (!q) return logLinesArr.value;

  return logLinesArr.value.filter((l) => (l.toLowerCase().includes(q) ? !logInvert.value : logInvert.value));
});

const filteredLog = computed(() => filteredLogLines.value.join('\n'));
const logShown = computed(() => filteredLogLines.value.length);
const logTotal = computed(() => logLinesArr.value.length);

// ---- sessions ----

/**
 * End somebody's login session.
 *
 * Signals everything attached to that terminal, which is what logging a user
 * out actually means — killing only the shell leaves whatever it started
 * running.
 */
/**
 * Only a real terminal can be signalled.
 *
 * `who` also lists rows whose "tty" is `sshd` or `seat0` — a service name and a
 * login seat, not a terminal. Offering an End button there would produce a
 * refusal on click; the honest interface is to not offer it.
 */
function killable(tty: string): boolean {
  return /^(pts\/\d{1,4}|tty\d{1,3})$/.test(tty);
}

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
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
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
  const srv = server.value;
  if (!srv) return;

  banActing.value = true;
  banNote.value = '';
  try {
    const r = await s.banAction(srv.id, { daemon, action, ip, jail: jail || undefined });
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
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
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
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
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
  const id = Number(route.params.id);
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
  const id = Number(route.params.id);
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

type Tab = 'overview' | 'logs' | 'security' | 'services' | 'processes' | 'terminal' | 'removal';

const tab = ref<Tab>('overview');

const tabs = computed<{ id: Tab; label: string }[]>(() => [
  { id: 'overview', label: t('servers.tab_overview') },
  { id: 'logs', label: t('servers.tab_logs') },
  { id: 'security', label: t('servers.tab_security') },
  { id: 'services', label: t('servers.tab_services') },
  { id: 'processes', label: t('servers.tab_processes') },
  { id: 'terminal', label: t('servers.tab_terminal') },
  { id: 'removal', label: t('servers.tab_removal') },
]);

const terminalRef = ref<{ close: () => Promise<void> } | null>(null);

function setTab(next: Tab) {
  // Leaving the terminal ends the shell here rather than relying on unmount:
  // unmount closes it too, but fire-and-forget, so a slow answer could leave a
  // session running on somebody's server after the tab looked closed.
  if (tab.value === 'terminal' && next !== 'terminal') void terminalRef.value?.close();
  tab.value = next;
  // The page is already deep-linkable; the tab belongs in the URL for the same
  // reason the id does — so a link lands where the sender was looking.
  void router.replace({ query: { ...route.query, tab: next === 'overview' ? undefined : next } });
  if (next === 'logs' && sources.value === null) void loadSources();
  if (next === 'security' && sec.value === null) {
    void loadSecurity();
    void loadBans();
  }
  if (next === 'services' && !services.value.length) void loadServices();
  if (next === 'processes' && !processes.value.length) void loadProcesses();
  actionNote.value = '';
}

// ---- logs ----

const sources = ref<{ journal: boolean; units: string[]; containers: string[]; files: string[] } | null>(null);
const sourcesError = ref(false);
const logSource = ref<'journal' | 'docker' | 'file'>('journal');
const logUnit = ref('');
const logContainer = ref('');
const logPath = ref('');
const logLines = ref(200);
const logErrorsOnly = ref(false);
const logText = ref('');
const logError = ref('');
const logBusy = ref(false);

const sourceOptions = computed(() => {
  const src = sources.value;
  const out: { title: string; value: string }[] = [];
  if (src?.journal) out.push({ title: t('servers.log_journal'), value: 'journal' });
  if (src?.containers.length) out.push({ title: t('servers.log_docker'), value: 'docker' });
  if (src?.files.length) out.push({ title: t('servers.log_file'), value: 'file' });

  return out;
});

const hasAnySource = computed(() => {
  const src = sources.value;
  return !!src && (src.journal || src.containers.length > 0 || src.files.length > 0);
});

/**
 * Ask the host what it has before offering anything. This is also the security
 * boundary: the selects below are populated from this answer, so a read names
 * something the host reported rather than something the browser invented.
 */
async function loadSources() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
  try {
    const r = await s.logSources(id);
    sources.value = r;
    sourcesError.value = r.error !== null;
    // Land on something that exists rather than on an empty journal select.
    if (!r.journal && r.containers.length) logSource.value = 'docker';
    else if (!r.journal && r.files.length) logSource.value = 'file';
    logContainer.value = r.containers[0] ?? '';
    logPath.value = r.files[0] ?? '';
  } catch {
    sourcesError.value = true;
  }
}

async function fetchLog() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
  logBusy.value = true;
  logError.value = '';
  try {
    const r = await s.readLog(id, {
      source: logSource.value,
      unit: logSource.value === 'journal' ? logUnit.value : '',
      container: logSource.value === 'docker' ? logContainer.value : '',
      path: logSource.value === 'file' ? logPath.value : '',
      lines: logLines.value,
      errors_only: logErrorsOnly.value,
    });
    // An empty log is an answer, not a failure — say so rather than leaving the
    // previous content on screen as if it were fresh.
    logText.value = r.text.trim() === '' ? t('servers.log_empty') : r.text;
  } catch (e) {
    logText.value = '';
    logError.value = e instanceof ApiError && typeof e.body === 'object' && e.body !== null && 'error' in e.body
      ? errorText(String((e.body as { error: unknown }).error))
      : t('servers.log_failed');
  } finally {
    logBusy.value = false;
  }
}

async function copyLog() {
  await navigator.clipboard.writeText(logText.value);
  success(t('common.copied'));
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
    if (route.query.tab === 'logs') {
      tab.value = 'logs';
      void loadSources();
    } else if (route.query.tab === 'terminal') {
      tab.value = 'terminal';
    } else if (route.query.tab === 'removal') {
      tab.value = 'removal';
    } else if (route.query.tab === 'security') {
      tab.value = 'security';
      void loadSecurity();
      void loadBans();
    } else if (route.query.tab === 'services') {
      tab.value = 'services';
      void loadServices();
    } else if (route.query.tab === 'processes') {
      tab.value = 'processes';
      void loadProcesses();
    }
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
