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
        <v-list-item :prepend-icon="mdiThemeLightDark" :title="t('pages.appearance.theme')" @click="toggleTheme" />
        <v-list-item :prepend-icon="mdiLogout" :title="t('account.logout')" @click="logout" />
      </v-list>
    </template>
  </v-navigation-drawer>

  <v-app-bar flat color="surface" border>
    <v-app-bar-nav-icon @click="drawer = !drawer" />
    <v-app-bar-title>Ledgerline</v-app-bar-title>
    <template #append>
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
import { ref } from 'vue';
import { useTheme } from 'vuetify';
import { trans as t, loadLanguageAsync, getActiveLanguage } from 'laravel-vue-i18n';
import { mdiChevronLeft, mdiLogout, mdiThemeLightDark, mdiTranslate, mdiReceiptTextOutline, mdiFolderOutline, mdiAccountBoxOutline, mdiCogOutline, mdiAccountCircleOutline } from '@mdi/js';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@spa/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const theme = useTheme();
const drawer = ref(true);
const rail = ref(false);
const locales = ['en', 'de', 'ru'] as const;
const locale = ref(getActiveLanguage() || 'en');

const navItems = [
  { to: '/finance', icon: mdiReceiptTextOutline, label: 'messages.nav.finance' },
  { to: '/files', icon: mdiFolderOutline, label: 'messages.nav.files' },
  { to: '/contacts', icon: mdiAccountBoxOutline, label: 'messages.nav.contacts' },
  { to: '/profile', icon: mdiAccountCircleOutline, label: 'pages.profile.title' },
  { to: '/settings', icon: mdiCogOutline, label: 'pages.settings.title' },
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
