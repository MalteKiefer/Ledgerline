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
        <p v-if="closedNote" class="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">{{ closedNote }}</p>
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
          <Select
            v-model="themeId"
            class="w-44"
            :label="t('servers.terminal_theme')"
            :options="THEMES.map((th) => ({ title: th.label, value: th.id }))"
            @update:model-value="applyTheme"
          />
          <Btn variant="ghost" size="sm" icon="close" @click="stop">{{ t('servers.terminal_close') }}</Btn>
        </div>
      </div>

      <!-- The padding is on the container: xterm measures the inner box, so
           the fit addon accounts for it and the text stops touching the edge. -->
      <div
        ref="host"
        class="overflow-hidden rounded-xl border border-[var(--ll-border)] p-3"
        :style="{ background: theme.colors.background }"
        @click="term?.focus()"
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
import { Icon, Btn, TextField, Select } from '@spa/ui';
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
  // Without this the helper textarea never receives a keystroke. Pasting still
  // worked, which made it look like the channel was fine and the keyboard was
  // not — the session was simply never focused.
  terminal.focus();

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
  // The remote pty was sized at connect; tell it about the new geometry, or
  // anything full-screen keeps drawing to the old box.
  const t2 = term.value;
  if (t2 && phase.value === 'live') void send(`stty rows ${t2.rows} cols ${t2.cols} 2>/dev/null\n`);
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
      ended(t(`servers.terminal_closed_${r.closed}`) || t('servers.terminal_closed_closed'));

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
      ended(t('servers.terminal_closed_closed'));

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
  teardown();
  term.value?.dispose();
  term.value = null;
  session = '';
  cursor = 0;
  stopped = false;
  // Back to the password rather than a dead black rectangle. Opening another
  // shell has to cost the password again, so this is also the correct state.
  phase.value = 'locked';
  closedNote.value = '';
}

/** The shell is gone: say why, and offer the way back in rather than a frozen screen. */
function ended(note: string) {
  teardown();
  term.value?.dispose();
  term.value = null;
  session = '';
  cursor = 0;
  stopped = false;
  phase.value = 'locked';
  closedNote.value = note;
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
