<template>
  <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="surface-variant">
    <v-list-item
      :title="auth.user?.name ?? ''"
      :subtitle="auth.user?.email ?? ''"
      nav
    >
      <template #append>
        <v-btn variant="text" :icon="mdiChevronLeft" size="small" @click.stop="rail = !rail" />
      </template>
    </v-list-item>
    <v-divider />
    <v-list density="compact" nav>
      <v-list-item
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        :prepend-icon="item.icon"
        :title="t(item.label)"
      />
    </v-list>
    <template #append>
      <v-list density="compact" nav>
        <v-list-item :prepend-icon="mdiThemeLightDark" :title="t('account.appearance_theme')" @click="toggleTheme" />
        <v-list-item :prepend-icon="mdiLogout" :title="t('messages.menu.logout')" @click="logout" />
      </v-list>
    </template>
  </v-navigation-drawer>

  <v-app-bar flat color="surface" border>
    <v-app-bar-nav-icon @click="drawer = !drawer" />
    <v-app-bar-title>Ledgerline</v-app-bar-title>
    <template #append>
      <v-menu @update:model-value="(o: boolean) => o && loadNotes()">
        <template #activator="{ props }">
          <v-btn icon v-bind="props">
            <v-badge :model-value="unread > 0" :content="unread" color="error">
              <v-icon :icon="mdiBellOutline" />
            </v-badge>
          </v-btn>
        </template>
        <v-list width="320" density="compact">
          <v-list-item v-for="n in notes" :key="n.id" :title="n.title" :subtitle="n.body" :class="{ 'bg-surface-variant': !n.read }" @click="readNote(n)" />
          <v-list-item v-if="!notes.length" :title="t('common.none')" class="text-medium-emphasis" />
          <template v-if="notes.length">
            <v-divider />
            <v-list-item :title="t('notifications.mark_all_read')" @click="markAllNotes" />
          </template>
        </v-list>
      </v-menu>
      <v-menu>
        <template #activator="{ props }">
          <v-btn variant="text" :append-icon="mdiTranslate" v-bind="props">{{ locale.toUpperCase() }}</v-btn>
        </template>
        <v-list>
          <v-list-item v-for="l in locales" :key="l" @click="setLocale(l)">
            <v-list-item-title>{{ l.toUpperCase() }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </template>
  </v-app-bar>

  <v-main>
    <v-container fluid>
      <router-view />
    </v-container>
  </v-main>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useTheme } from 'vuetify';
import { trans as t, loadLanguageAsync, getActiveLanguage } from 'laravel-vue-i18n';
import { mdiChevronLeft, mdiLogout, mdiThemeLightDark, mdiTranslate, mdiBellOutline, mdiReceiptTextOutline, mdiFolderOutline, mdiAccountBoxOutline, mdiCogOutline, mdiAccountCircleOutline } from '@mdi/js';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@spa/stores/auth';
import { api } from '@spa/api/client';

const auth = useAuthStore();
const router = useRouter();
const theme = useTheme();
const drawer = ref(true);
const rail = ref(false);
const locales = ['en', 'de', 'ru'] as const;
const locale = ref(getActiveLanguage() || 'en');

interface Note { id: string; title: string; body: string; read: boolean }
const notes = ref<Note[]>([]);
const unread = ref(0);
async function loadNotes() {
  try {
    const r = await api.get<{ unread?: number; items?: Note[] } | Note[]>('/api/v1/notifications');
    const items = Array.isArray(r) ? r : (r.items ?? []);
    notes.value = items;
    unread.value = Array.isArray(r) ? items.filter((n) => !n.read).length : (r.unread ?? 0);
  } catch { /* ignore */ }
}
async function readNote(n: Note) { if (n.read) return; await api.post(`/api/v1/notifications/${n.id}/read`); n.read = true; unread.value = Math.max(0, unread.value - 1); }
async function markAllNotes() { await api.post('/api/v1/notifications/read-all'); notes.value.forEach((n) => (n.read = true)); unread.value = 0; }
onMounted(loadNotes);

const navItems = [
  { to: '/finance', icon: mdiReceiptTextOutline, label: 'messages.nav.finance' },
  { to: '/files', icon: mdiFolderOutline, label: 'messages.nav.files' },
  { to: '/contacts', icon: mdiAccountBoxOutline, label: 'messages.nav.contacts' },
  { to: '/profile', icon: mdiAccountCircleOutline, label: 'pages.profile.title' },
  { to: '/settings', icon: mdiCogOutline, label: 'settings.heading' },
];

function toggleTheme() {
  theme.global.name.value = theme.global.current.value.dark ? 'light' : 'dark';
}

async function setLocale(l: string) {
  await loadLanguageAsync(l);
  locale.value = l;
}

async function logout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>
