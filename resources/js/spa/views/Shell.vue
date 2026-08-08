<template>
  <v-navigation-drawer v-model="drawer" :rail="rail" @update:rail="rail = $event" color="surface" width="272" class="ll-nav">
    <div class="d-flex align-center px-4 py-3">
      <div class="ll-brand-logo d-flex align-center justify-center mr-2">
        <span class="msym" style="font-size:20px">bolt</span>
      </div>
      <span v-if="!rail" class="text-h6 font-weight-bold">Ledgerline</span>
      <v-spacer />
      <v-btn v-if="!rail" variant="text" size="small" :icon="mdiChevronLeft" @click="rail = true" />
    </div>

    <v-list density="compact" nav class="px-2">
      <div v-for="grp in menu" :key="grp.key">
        <v-list-subheader v-if="!rail && grp.title" class="ll-subheader">{{ t(grp.title) }}</v-list-subheader>
        <template v-for="item in grp.items" :key="item.to || item.key">
          <v-list-group v-if="item.children" :value="item.key">
            <template #activator="{ props }">
              <v-list-item v-bind="props" :title="t(item.label)">
                <template #prepend><span class="msym ll-nav-ic">{{ item.icon }}</span></template>
              </v-list-item>
            </template>
            <v-list-item
              v-for="c in item.children"
              :key="c.to"
              :to="c.to"
              :title="t(c.label)"
              :active="isActive(c.to)"
              class="ll-sub"
            >
              <template #prepend><span class="ll-dot" /></template>
            </v-list-item>
          </v-list-group>
          <v-list-item v-else :to="item.to" :title="t(item.label)" :active="isActive(item.to)">
            <template #prepend><span class="msym ll-nav-ic">{{ item.icon }}</span></template>
          </v-list-item>
        </template>
      </div>
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat height="64" class="ll-appbar px-2">
    <v-btn variant="text" :icon="mdiMenu" @click="rail ? (rail = false) : (drawer = !drawer)" />
    <div class="d-none d-sm-flex align-center ga-1 text-body-2">
      <span class="text-medium-emphasis">{{ crumbRoot }}</span>
      <template v-if="crumbLeaf">
        <span class="msym text-disabled" style="font-size:16px">chevron_right</span>
        <span class="font-weight-medium">{{ crumbLeaf }}</span>
      </template>
    </div>
    <v-spacer />
    <v-text-field
      v-model="globalSearch"
      :placeholder="t('common.search')"
      :prepend-inner-icon="mdiMagnify"
      variant="solo-filled" flat density="compact" hide-details single-line rounded
      class="d-none d-md-block mr-2" style="max-width:280px"
      @keyup.enter="runSearch"
    />
    <v-btn variant="text" :icon="mdiThemeLightDark" @click="toggleTheme" />
    <v-menu>
      <template #activator="{ props }">
        <v-btn variant="text" v-bind="props" size="small" class="text-body-2">{{ locale.toUpperCase() }}</v-btn>
      </template>
      <v-list density="compact">
        <v-list-item v-for="l in locales" :key="l" :active="l === locale" @click="setLocale(l)"><v-list-item-title>{{ l.toUpperCase() }}</v-list-item-title></v-list-item>
      </v-list>
    </v-menu>
    <v-menu @update:model-value="(o: boolean) => o && loadNotes()">
      <template #activator="{ props }">
        <v-btn icon v-bind="props"><v-badge :model-value="unread > 0" :content="unread" color="error"><v-icon :icon="mdiBellOutline" /></v-badge></v-btn>
      </template>
      <v-list width="340" density="compact">
        <v-list-subheader>{{ t('notifications.title') }}</v-list-subheader>
        <v-list-item v-for="n in notes" :key="n.id" :title="n.title" :subtitle="n.body" :class="{ 'bg-surface-variant': !n.read }" @click="readNote(n)" />
        <v-list-item v-if="!notes.length" :title="t('common.none')" class="text-medium-emphasis" />
        <template v-if="notes.length"><v-divider /><v-list-item class="text-primary" :title="t('notifications.mark_all_read')" @click="markAllNotes" /></template>
      </v-list>
    </v-menu>
    <v-menu>
      <template #activator="{ props }">
        <v-btn variant="text" v-bind="props" class="ll-user pl-1 pr-2">
          <v-avatar size="34" color="primary" class="mr-2">
            <v-img v-if="avatarUrl" :src="avatarUrl" />
            <span v-else class="text-body-2">{{ initials }}</span>
          </v-avatar>
          <div class="d-none d-sm-block text-left" style="line-height:1.1">
            <div class="text-body-2 font-weight-medium">{{ auth.user?.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ auth.isAdmin() ? 'Admin' : 'User' }}</div>
          </div>
        </v-btn>
      </template>
      <v-list width="220" density="compact">
        <v-list-item :prepend-icon="mdiAccountCircleOutline" :title="t('pages.profile.title')" to="/profile" />
        <v-list-item v-if="auth.isAdmin()" :prepend-icon="mdiCogOutline" :title="t('settings.heading')" to="/settings" />
        <v-divider />
        <v-list-item :prepend-icon="mdiLogout" :title="t('messages.menu.logout')" base-color="error" @click="logout" />
      </v-list>
    </v-menu>
  </v-app-bar>

  <v-main class="ll-main">
    <v-container fluid class="pa-4 pa-md-6">
      <router-view />
    </v-container>
  </v-main>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useTheme } from 'vuetify';
import { useRouter, useRoute } from 'vue-router';
import { trans as t, loadLanguageAsync, getActiveLanguage } from 'laravel-vue-i18n';
import {
  mdiMenu, mdiChevronLeft, mdiMagnify, mdiThemeLightDark, mdiBellOutline, mdiLogout,
  mdiAccountCircleOutline, mdiCogOutline,
} from '@mdi/js';
import { useAuthStore } from '@spa/stores/auth';
import { api } from '@spa/api/client';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const theme = useTheme();
const drawer = ref(true);
const rail = ref(false);
const locales = ['de', 'en', 'ru'] as const;
const locale = ref(getActiveLanguage() || 'de');
const globalSearch = ref('');
const avatarBust = ref(0);

const avatarUrl = computed(() => (auth.user?.has_avatar ? `/api/v1/avatar?v=${avatarBust.value}` : ''));
const initials = computed(() => (auth.user?.name ?? '?').slice(0, 1).toUpperCase());

interface NavChild { to: string; label: string }
interface NavItem { key?: string; to?: string; label: string; icon?: string; children?: NavChild[] }
interface NavGroup { key: string; title?: string; items: NavItem[] }

const menu = computed<NavGroup[]>(() => {
  const groups: NavGroup[] = [
    { key: 'main', items: [{ to: '/', label: 'pages.dashboard.title', icon: 'space_dashboard' }] },
  ];
  const mods: NavItem[] = [];
  if (auth.can('finance')) mods.push({
    key: 'finance', label: 'messages.nav.finance', icon: 'account_balance_wallet',
    children: [
      { to: '/finance/dashboard', label: 'invoices.tab_dashboard' },
      { to: '/finance/invoices', label: 'invoices.tab_invoices' },
      { to: '/finance/payments', label: 'invoices.tab_payments' },
      { to: '/finance/receipts', label: 'invoices.tab_receipts' },
      { to: '/finance/projects', label: 'invoices.tab_projects' },
      { to: '/finance/partners', label: 'invoices.tab_partners' },
      { to: '/finance/stats', label: 'invoices.tab_stats' },
    ],
  });
  if (auth.can('files')) mods.push({ to: '/files', label: 'messages.nav.files', icon: 'folder' });
  if (auth.can('contacts')) mods.push({ to: '/contacts', label: 'messages.nav.contacts', icon: 'contacts' });
  groups.push({ key: 'modules', title: 'settings.personal_heading', items: mods });

  if (auth.isAdmin()) {
    groups.push({
      key: 'admin', title: 'settings.admin_heading',
      items: [{
        key: 'settings', label: 'settings.heading', icon: 'settings',
        children: [
          { to: '/settings/users', label: 'settings.users_section' },
          { to: '/settings/groups', label: 'settings.groups_section' },
          { to: '/settings/company', label: 'settings.company_section' },
          { to: '/settings/notifications-config', label: 'settings.notifications_section' },
          { to: '/settings/security', label: 'settings.security_section' },
          { to: '/settings/files-limits', label: 'settings.files_limits_heading' },
          { to: '/settings/backup', label: 'settings.backup_section' },
          { to: '/settings/paperless', label: 'settings.paperless_section' },
          { to: '/settings/system', label: 'settings.system_section' },
          { to: '/settings/security-log', label: 'settings.seclog_title' },
        ],
      }],
    });
  }
  groups.push({ key: 'account', title: 'account.hub_account_heading', items: [
    { to: '/profile', label: 'pages.profile.title', icon: 'account_circle' },
  ] });
  return groups;
});

const routeTitles: Record<string, string> = {
  home: 'pages.dashboard.title', files: 'messages.nav.files',
  contacts: 'messages.nav.contacts', profile: 'pages.profile.title',
};
const crumbRoot = computed(() => {
  const name = String(route.name ?? '');
  if (name.startsWith('settings')) return t('settings.heading');
  if (name.startsWith('profile')) return t('pages.profile.title');
  if (name === 'finance') return t('messages.nav.finance');
  return t(routeTitles[name] ?? 'pages.dashboard.title');
});
const crumbLeaf = computed(() => {
  const name = String(route.name ?? '');
  const map: Record<string, string> = {
    'settings.users': 'settings.users_section', 'settings.groups': 'settings.groups_section',
    'settings.company': 'settings.company_section', 'settings.backup': 'settings.backup_section',
    'settings.security-log': 'settings.seclog_title', 'settings.notifications': 'settings.notifications_section',
    'settings.notifications-config': 'settings.notifications_section', 'settings.security': 'settings.security_section',
    'settings.files-limits': 'settings.files_limits_heading', 'settings.system': 'settings.system_section',
    'settings.paperless': 'settings.paperless_section',
    'profile.account': 'account.nav_account', 'profile.appearance': 'account.nav_appearance',
    'profile.security': 'account.nav_security', 'profile.devices': 'account.nav_devices',
    'profile.data': 'account.hub_data_heading',
  };
  if (map[name]) return t(map[name]);
  if (name === 'finance' && route.params.section) return t(`invoices.tab_${String(route.params.section)}`);
  return '';
});

function isActive(to?: string): boolean {
  if (!to) return false;
  if (to === '/') return route.path === '/';
  return route.path === to || route.path.startsWith(to + '/');
}

function toggleTheme() { theme.global.name.value = theme.global.current.value.dark ? 'light' : 'dark'; }
async function setLocale(l: string) { await loadLanguageAsync(l); locale.value = l; try { await api.post('/api/v1/locale', { locale: l }); } catch { /* non-fatal */ } }
function runSearch() { if (globalSearch.value.trim()) router.push({ path: '/files', query: { q: globalSearch.value.trim() } }); }
async function logout() { await auth.logout(); router.push({ name: 'login' }); }

interface Note { id: string | number; title: string; body: string; read: boolean }
const notes = ref<Note[]>([]);
const unread = ref(0);
async function loadNotes() {
  try {
    const r = await api.get<{ unread?: number; items?: Note[] }>('/api/v1/notifications');
    notes.value = r.items ?? []; unread.value = r.unread ?? 0;
  } catch { /* ignore */ }
}
async function readNote(n: Note) { if (n.read) return; await api.post(`/api/v1/notifications/${n.id}/read`); n.read = true; unread.value = Math.max(0, unread.value - 1); }
async function markAllNotes() { await api.post('/api/v1/notifications/read-all'); notes.value.forEach((n) => (n.read = true)); unread.value = 0; }
onMounted(loadNotes);
</script>

<style scoped>
.ll-brand-logo { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg, #7066f5, #9e70fa); color: #fff; }
.ll-subheader { font-size: 0.66rem !important; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.55; min-height: 28px !important; padding-top: 12px; }
.ll-nav-ic { font-family: 'Material Symbols Outlined'; font-size: 20px; width: 24px; text-align: center; }
.ll-sub :deep(.v-list-item__prepend) { width: 24px; }
.ll-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; opacity: 0.4; margin-left: 9px; }
.ll-user { text-transform: none; height: 48px; }
.ll-appbar { border-bottom: 1px solid rgba(255,255,255,0.06); }
</style>
