<template>
  <div>
    <!-- Password. Asked for every session, never remembered. -->
    <div v-if="phase === 'locked'" class="mx-auto max-w-md py-8">
      <div class="mb-3 flex items-center gap-2">
        <Icon name="terminal" :size="20" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('servers.terminal_title') }}</h2>
      </div>
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('servers.terminal_unlock_intro') }}</p>
      <form @submit.prevent="start">
        <TextField v-model="password" type="password" :label="t('account.current_password')" autocomplete="current-password" autofocus />
        <p v-if="error" class="mt-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        <Btn class="mt-3" variant="solid" icon="terminal" :disabled="busy || !password">
          {{ busy ? t('servers.terminal_opening') : t('servers.terminal_open') }}
        </Btn>
      </form>
      <p class="mt-4 text-[0.7rem] leading-relaxed text-[var(--ll-muted)]">{{ t('servers.terminal_warning') }}</p>
    </div>

    <!-- Session -->
    <div v-else>
      <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-sm">
          <span class="h-2 w-2 rounded-full" :class="phase === 'live' ? 'bg-emerald-500' : phase === 'connecting' ? 'bg-amber-500' : 'bg-[var(--ll-muted)]'" />
          <span class="text-[var(--ll-muted)]">{{ phaseLabel }}</span>
        </div>
        <div class="flex items-center gap-2">
          <label class="flex items-center gap-1.5 text-xs text-[var(--ll-muted)]">
            {{ t('servers.terminal_theme') }}
            <select v-model="themeId" class="rounded-lg border border-[var(--ll-border)] bg-transparent px-2 py-1 text-xs" @change="applyTheme">
              <option v-for="th in THEMES" :key="th.id" :value="th.id">{{ th.label }}</option>
            </select>
          </label>
          <Btn variant="ghost" size="sm" icon="close" @click="stop">{{ t('servers.terminal_close') }}</Btn>
        </div>
      </div>

      <div
        ref="host"
        class="overflow-hidden rounded-xl border border-[var(--ll-border)]"
        :style="{ background: theme.colors.background }"
      />

      <p v-if="closedNote" class="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">{{ closedNote }}</p>
      <p class="mt-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.terminal_footnote') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * An interactive shell, rendered by xterm.js and driven by polling.
 *
 * Polling rather than a socket or a stream: this backend serves requests from a
 * fixed worker pool, and a connection held open for the length of a session
 * pins one of those workers. The cost is a little latency on echo; the
 * alternative has already taken the application down once.
 *
 * The poll interval adapts — brisk while the session is producing output or
 * taking keystrokes, slower when nothing is happening — so an idle terminal
 * left open in a tab is not a request every fiftieth of a second.
 */
import { computed, nextTick, onBeforeUnmount, ref, shallowRef } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import '@xterm/xterm/css/xterm.css';
import { Icon, Btn, TextField } from '@spa/ui';
import { useServersStore } from '@spa/stores/servers';
import { ApiError } from '@spa/api/client';
import { TERMINAL_THEMES as THEMES, preferredTheme, rememberTheme, type TerminalTheme } from '@spa/lib/terminal-themes';

const props = defineProps<{ serverId: number }>();

const s = useServersStore();

const phase = ref<'locked' | 'connecting' | 'live' | 'over'>('locked');
const password = ref('');
const error = ref('');
const busy = ref(false);
const closedNote = ref('');
const host = ref<HTMLElement | null>(null);

const appIsDark = document.documentElement.classList.contains('dark');
const theme = ref<TerminalTheme>(preferredTheme(appIsDark));
const themeId = ref(theme.value.id);

// shallowRef: xterm owns a large mutable object graph that Vue must not try to
// make reactive — deep tracking here is both pointless and slow.
const term = shallowRef<Terminal | null>(null);
const fit = shallowRef<FitAddon | null>(null);

let session = '';
let cursor = 0;
let timer: number | null = null;
let interval = 60;
let stopped = false;

const phaseLabel = computed(() =>
  phase.value === 'live' ? t('servers.terminal_live')
    : phase.value === 'connecting' ? t('servers.terminal_connecting')
      : t('servers.terminal_ended'));

async function start() {
  busy.value = true;
  error.value = '';
  try {
    const cols = 100;
    const rows = 30;
    const r = await s.terminalOpen(props.serverId, { current_password: password.value, cols, rows });
    session = r.session;
    // Out of memory the moment it has been spent. It is asked for again next time.
    password.value = '';
    phase.value = 'connecting';
    await nextTick();
    mount();
    schedule(0);
  } catch (e) {
    error.value = messageFor(e);
  } finally {
    busy.value = false;
  }
}

function mount() {
  if (!host.value) return;
  const terminal = new Terminal({
    theme: theme.value.colors,
    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
    fontSize: 13,
    cursorBlink: true,
    // A shell prints more than fits; without scrollback the beginning of any
    // long output would simply be gone.
    scrollback: 5000,
    convertEol: false,
  });
  const fitAddon = new FitAddon();
  terminal.loadAddon(fitAddon);
  terminal.open(host.value);
  fitAddon.fit();

  terminal.onData((data) => {
    void send(data);
    // Typing is a sign of life: poll briskly so the echo comes back promptly.
    interval = 60;
    schedule(0);
  });

  term.value = terminal;
  fit.value = fitAddon;
  window.addEventListener('resize', onResize);
}

function onResize() {
  fit.value?.fit();
}

function applyTheme() {
  const found = THEMES.find((th) => th.id === themeId.value);
  if (!found) return;
  theme.value = found;
  rememberTheme(found.id);
  if (term.value) term.value.options.theme = found.colors;
}

/** Keystrokes as base64: a terminal carries control bytes, not text. */
async function send(data: string) {
  if (!session || phase.value === 'over') return;
  try {
    await s.terminalInput(props.serverId, session, btoa(String.fromCharCode(...new TextEncoder().encode(data))));
  } catch {
    // A failed keystroke is not worth interrupting the session for; the next
    // poll will report it if the session is genuinely gone.
  }
}

function schedule(delay: number) {
  if (timer !== null) window.clearTimeout(timer);
  if (stopped) return;
  timer = window.setTimeout(() => void poll(), delay);
}

async function poll() {
  if (!session || stopped) return;
  try {
    const r = await s.terminalPoll(props.serverId, session, cursor);
    cursor = r.cursor;

    if (r.data) {
      const bytes = Uint8Array.from(atob(r.data), (c) => c.charCodeAt(0));
      term.value?.write(bytes);
      interval = 60;
    } else {
      // Nothing happening: back off rather than hammering a quiet session.
      interval = Math.min(interval * 1.5, 900);
    }

    if (r.ready && phase.value === 'connecting') phase.value = 'live';

    if (r.closed) {
      phase.value = 'over';
      closedNote.value = t(`servers.terminal_closed_${r.closed}`) || t('servers.terminal_closed_closed');
      teardown();

      return;
    }

    // Still no worker after a while: say so instead of spinning forever on a
    // session that will never become ready.
    if (!r.ready && phase.value === 'connecting' && Date.now() - openedAt > 20000) {
      closedNote.value = t('servers.terminal_no_worker');
    }

    schedule(interval);
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      phase.value = 'over';
      closedNote.value = t('servers.terminal_closed_closed');
      teardown();

      return;
    }
    schedule(1000);
  }
}

const openedAt = Date.now();

async function stop() {
  if (session) {
    try {
      await s.terminalClose(props.serverId, session);
    } catch {
      // Closing is best effort; the session expires on its own regardless.
    }
  }
  phase.value = 'over';
  teardown();
}

function teardown() {
  stopped = true;
  if (timer !== null) window.clearTimeout(timer);
  timer = null;
  window.removeEventListener('resize', onResize);
}

onBeforeUnmount(() => {
  // Leaving the page ends the session rather than leaving a shell open on
  // somebody's server waiting for the idle timeout.
  if (session && phase.value !== 'over') void s.terminalClose(props.serverId, session).catch(() => {});
  teardown();
  term.value?.dispose();
});

function messageFor(e: unknown): string {
  if (e instanceof ApiError && typeof e.body === 'object' && e.body !== null && 'error' in e.body) {
    const code = String((e.body as { error: unknown }).error);
    const key = `servers.terminal_err_${code}`;
    const text = t(key);

    return text === key ? code : text;
  }

  return t('servers.terminal_err_failed');
}
</script>
