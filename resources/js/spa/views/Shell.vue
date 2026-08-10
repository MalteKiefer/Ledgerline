<template>
  <div class="flex min-h-screen bg-[var(--ll-bg)] text-[var(--ll-fg)]">
    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r border-[var(--ll-border)] bg-[var(--ll-surface)] transition-transform lg:static lg:translate-x-0"
      :class="drawer ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex items-center gap-2.5 px-4 h-16">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="18" /></span>
        <span class="text-base font-bold">Ledgerline</span>
      </div>
      <nav class="flex-1 overflow-y-auto px-3 pb-4 space-y-4">
        <div v-for="grp in menu" :key="grp.key">
          <div v-if="grp.title" class="px-2 pt-3 pb-1 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t(grp.title) }}</div>
          <template v-for="item in grp.items" :key="item.to || item.key">
            <div v-if="item.children">
              <button
                class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
                @click="toggle(item.key!)"
              >
                <Icon :name="item.icon!" :size="20" class="text-[var(--ll-muted)]" />
                <span class="flex-1 text-left">{{ t(item.label) }}</span>
                <Icon name="expand_more" :size="18" class="text-[var(--ll-muted)] transition-transform" :class="open[item.key!] ? 'rotate-180' : ''" />
              </button>
              <div v-show="open[item.key!]" class="mt-0.5 space-y-0.5 pl-3">
                <RouterLink
                  v-for="c in item.children" :key="c.to" :to="c.to"
                  class="flex items-center gap-2.5 rounded-lg py-1.5 pl-4 pr-2.5 text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
                  :class="isActive(c.to) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300 font-medium' : 'text-[var(--ll-muted)]'"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="isActive(c.to) ? 'bg-primary-500' : 'bg-current opacity-40'" />
                  {{ t(c.label) }}
                </RouterLink>
              </div>
            </div>
            <RouterLink
              v-else :to="item.to!"
              class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
              :class="isActive(item.to) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
            >
              <Icon :name="item.icon!" :size="20" :class="isActive(item.to) ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
              {{ t(item.label) }}
            </RouterLink>
          </template>
        </div>
      </nav>
      <!-- Pinned account footer: avatar + name → profile, sign out, app version.
           Sign out lives here because the top-right avatar menu was removed; the
           sidebar footer is the account home (it already links to the profile). -->
      <div class="mt-auto border-t border-[var(--ll-border)] p-2">
        <div class="flex items-center gap-1">
          <RouterLink
            to="/profile"
            class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-2 py-2 hover:bg-black/[0.04] dark:hover:bg-white/5"
            :class="isActive('/profile') ? 'bg-primary-500/10' : ''"
          >
            <span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-500 text-sm font-medium text-white">
              <img v-if="avatarUrl" :src="avatarUrl" class="h-full w-full object-cover" alt=""><template v-else>{{ initials }}</template>
            </span>
            <span class="min-w-0 flex-1 leading-tight">
              <span class="block truncate text-sm font-medium" :class="isActive('/profile') ? 'text-primary-600 dark:text-primary-300' : ''">{{ auth.user?.name }}</span>
              <span class="block truncate text-xs text-[var(--ll-muted)]">{{ auth.user?.email }}</span>
            </span>
          </RouterLink>
          <button
            class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-[var(--ll-muted)] hover:bg-red-500/10 hover:text-red-600"
            :title="t('messages.menu.logout')"
            @click="logout"
          >
            <Icon name="logout" :size="18" />
          </button>
        </div>
        <div class="px-2 pt-1.5 text-[0.66rem] text-[var(--ll-muted)]">v{{ version }}</div>
      </div>
    </aside>
    <div v-if="drawer" class="fixed inset-0 z-20 bg-black/30 lg:hidden" @click="drawer = false" />

    <!-- Main column -->
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-10 flex h-16 items-center gap-2 border-b border-[var(--ll-border)] bg-[var(--ll-surface)]/80 px-3 backdrop-blur">
        <button class="grid h-9 w-9 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10 lg:hidden" @click="drawer = !drawer"><Icon name="menu" :size="20" /></button>
        <nav class="hidden items-center gap-1.5 text-sm sm:flex">
          <span class="text-[var(--ll-muted)]">{{ crumbRoot }}</span>
          <template v-if="crumbLeaf"><Icon name="chevron_right" :size="16" class="text-[var(--ll-muted)]" /><span class="font-medium">{{ crumbLeaf }}</span></template>
        </nav>
        <div class="ml-auto flex items-center gap-1">
          <div class="relative hidden md:block">
            <Icon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ll-muted)]" />
            <input v-model="globalSearch" :placeholder="t('common.search')" class="w-56 rounded-lg border border-[var(--ll-border)] bg-transparent py-1.5 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40" @keyup.enter="runSearch">
          </div>
          <button class="grid h-9 w-9 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" @click="toggleTheme"><Icon :name="dark ? 'light_mode' : 'dark_mode'" :size="20" /></button>

          <DropdownMenuRoot>
            <DropdownMenuTrigger class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-sm hover:bg-black/[0.05] dark:hover:bg-white/10">{{ locale.toUpperCase() }}</DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" class="z-[1600] min-w-32 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <DropdownMenuItem v-for="l in locales" :key="l" class="cursor-pointer rounded-md px-3 py-1.5 text-sm outline-none hover:bg-black/[0.05] dark:hover:bg-white/10" @select="setLocale(l)">{{ l.toUpperCase() }}</DropdownMenuItem>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>

          <DropdownMenuRoot @update:open="(o) => o && loadNotes()">
            <DropdownMenuTrigger class="relative grid h-9 w-9 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10">
              <Icon name="notifications" :size="20" />
              <span v-if="unread" class="absolute right-1.5 top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] text-white">{{ unread }}</span>
            </DropdownMenuTrigger>
            <DropdownMenuPortal><DropdownMenuContent :side-offset="6" align="end" class="z-[1600] w-80 rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1 shadow-lg">
              <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('notifications.title') }}</div>
              <button v-for="n in notes" :key="n.id" class="block w-full rounded-md px-3 py-2 text-left hover:bg-black/[0.05] dark:hover:bg-white/10" :class="n.read ? '' : 'bg-primary-500/5'" @click="readNote(n)">
                <div class="text-sm font-medium">{{ n.title }}</div><div class="text-xs text-[var(--ll-muted)]">{{ n.body }}</div>
              </button>
              <div v-if="!notes.length" class="px-3 py-4 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
              <template v-if="notes.length"><div class="my-1 border-t border-[var(--ll-border)]" /><button class="w-full rounded-md px-3 py-1.5 text-left text-sm text-primary-600 dark:text-primary-300 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="markAllNotes">{{ t('notifications.mark_all_read') }}</button></template>
            </DropdownMenuContent></DropdownMenuPortal>
          </DropdownMenuRoot>
        </div>
      </header>

      <main class="flex-1 p-4 md:p-6"><RouterView /></main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute, RouterLink, RouterView } from 'vue-router';
import { trans as t, loadLanguageAsync, getActiveLanguage } from 'laravel-vue-i18n';
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuItem } from 'reka-ui';
import { Icon } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { api } from '@spa/api/client';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const drawer = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));
const locales = ['de', 'en', 'ru'] as const;
const locale = ref(getActiveLanguage() || 'de');
const globalSearch = ref('');
const avatarBust = ref(0);
const open = reactive<Record<string, boolean>>({ finance: true, settings: true });

const avatarUrl = computed(() => (auth.user?.has_avatar ? api.streamUrl(`/api/v1/avatar?v=${avatarBust.value}`) : ''));
const initials = computed(() => (auth.user?.name ?? '?').slice(0, 1).toUpperCase());
const version = document.querySelector('meta[name="ll-version"]')?.getAttribute('content') || '';

interface NavChild { to: string; label: string }
interface NavItem { key?: string; to?: string; label: string; icon?: string; children?: NavChild[] }
interface NavGroup { key: string; title?: string; items: NavItem[] }

const menu = computed<NavGroup[]>(() => {
  const groups: NavGroup[] = [{ key: 'main', items: [{ to: '/', label: 'pages.dashboard.title', icon: 'space_dashboard' }] }];
  const mods: NavItem[] = [];
  // Finance + Settings carry their own in-page left submenu (like Profile),
  // so the sidebar shows them as single entries — no expandable children here.
  if (auth.can('finance')) mods.push({ to: '/finance', label: 'messages.nav.finance', icon: 'account_balance_wallet' });
  if (auth.can('files')) mods.push({ to: '/files', label: 'messages.nav.files', icon: 'folder' });
  if (auth.can('files')) mods.push({ to: '/shared-with-me', label: 'files.shared_with_me', icon: 'folder_shared' });
  if (auth.can('contacts')) mods.push({ to: '/contacts', label: 'messages.nav.contacts', icon: 'contacts' });
  if (auth.can('notes')) mods.push({ to: '/notes', label: 'messages.nav.notes', icon: 'sticky_note_2' });
  if (auth.can('calendar')) mods.push({ to: '/calendar', label: 'messages.nav.calendar', icon: 'calendar_month' });
  if (auth.can('calendar')) mods.push({ to: '/tasks', label: 'calendar.todos.title', icon: 'checklist' });
  if (auth.can('mail')) mods.push({ to: '/mail', label: 'messages.nav.mail', icon: 'mail' });
  groups.push({ key: 'modules', title: 'settings.personal_heading', items: mods });
  if (auth.isAdmin()) groups.push({ key: 'admin', title: 'settings.admin_heading', items: [
    { to: '/settings', label: 'settings.heading', icon: 'settings' },
  ] });
  // Profile is pinned to the sidebar footer (with avatar) — not a nav group.
  return groups;
});

const routeTitles: Record<string, string> = { home: 'pages.dashboard.title', files: 'messages.nav.files', 'shared-with-me': 'files.shared_with_me', contacts: 'messages.nav.contacts', notes: 'messages.nav.notes', calendar: 'messages.nav.calendar', tasks: 'calendar.todos.title', mail: 'messages.nav.mail', profile: 'pages.profile.title' };
const crumbRoot = computed(() => {
  const name = String(route.name ?? '');
  if (name.startsWith('settings')) return t('settings.heading');
  if (name.startsWith('profile')) return t('pages.profile.title');
  if (name === 'finance') return t('messages.nav.finance');
  return t(routeTitles[name] ?? 'pages.dashboard.title');
});
const leafMap: Record<string, string> = {
  'settings.users': 'settings.users_section', 'settings.groups': 'settings.groups_section', 'settings.company': 'settings.company_section',
  'settings.backup': 'settings.backup_section', 'settings.security-log': 'settings.seclog_title', 'settings.notifications-config': 'settings.notifications_section',
  'settings.security': 'settings.security_section', 'settings.files-limits': 'settings.files_limits_heading', 'settings.system': 'settings.system_section', 'settings.paperless': 'settings.paperless_section',
  'profile.account': 'account.nav_account', 'profile.appearance': 'account.nav_appearance', 'profile.security': 'account.nav_security', 'profile.devices': 'account.nav_devices', 'profile.data': 'account.hub_data_heading',
};
const crumbLeaf = computed(() => {
  const name = String(route.name ?? '');
  if (leafMap[name]) return t(leafMap[name]);
  if (name === 'finance' && route.params.section) return t(`invoices.tab_${String(route.params.section)}`);
  return '';
});

function toggle(k: string) { open[k] = !open[k]; }
function isActive(to?: string): boolean { if (!to) return false; if (to === '/') return route.path === '/'; return route.path === to || route.path.startsWith(to + '/'); }
function toggleTheme() { dark.value = !dark.value; document.documentElement.classList.toggle('dark', dark.value); localStorage.setItem('ll_theme', dark.value ? 'dark' : 'light'); }
async function setLocale(l: string) { await loadLanguageAsync(l); locale.value = l; try { await api.post('/api/v1/locale', { locale: l }); } catch { /* non-fatal */ } }
function runSearch() { if (globalSearch.value.trim()) router.push({ path: '/files', query: { q: globalSearch.value.trim() } }); }
async function logout() { await auth.logout(); router.push({ name: 'login' }); }

interface Note { id: string | number; title: string; body: string; read: boolean }
const notes = ref<Note[]>([]);
const unread = ref(0);
async function loadNotes() { try { const r = await api.get<{ unread?: number; items?: Note[] }>('/api/v1/notifications'); notes.value = r.items ?? []; unread.value = r.unread ?? 0; } catch { /* ignore */ } }
async function readNote(n: Note) { if (n.read) return; await api.post(`/api/v1/notifications/${n.id}/read`); n.read = true; unread.value = Math.max(0, unread.value - 1); }
async function markAllNotes() { await api.post('/api/v1/notifications/read-all'); notes.value.forEach((n) => (n.read = true)); unread.value = 0; }
onMounted(() => { loadNotes(); });
</script>
