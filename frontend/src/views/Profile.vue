<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('pages.profile.title') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <Card body-class="p-0" class="w-full flex-shrink-0 self-start md:w-60">
        <RouterLink
          v-for="s in sections" :key="s.to" :to="{ name: s.to }"
          class="flex w-full items-center gap-3 border-b border-[var(--ll-border)] px-4 py-3 last:border-0 hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="isActive(s.to) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
        >
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" :class="isActive(s.to) ? 'bg-primary-500/15' : 'bg-black/[0.05] dark:bg-white/10'">
            <Icon :name="s.icon" :size="20" />
          </span>
          <span class="flex-1 text-sm font-medium">{{ t(s.label) }}</span>
          <Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" />
        </RouterLink>
      </Card>
      <div class="min-w-0 flex-1">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, RouterLink, RouterView } from 'vue-router';
import { Icon, Card } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';

const route = useRoute();
const auth = useAuthStore();

const sections = computed(() => [
  { to: 'profile.account', icon: 'account_circle', label: 'account.nav_account' },
  { to: 'profile.appearance', icon: 'palette', label: 'account.nav_appearance' },
  { to: 'profile.security', icon: 'security', label: 'account.nav_security' },
  { to: 'profile.devices', icon: 'smartphone', label: 'account.nav_devices' },
  // Calendar settings only surface when the calendar module is enabled for the user.
  ...(auth.can('calendar') ? [{ to: 'profile.calendar', icon: 'calendar_month', label: 'messages.nav.calendar' }] : []),
  // Encryption keys (own PGP/S-MIME keys, recipients, keyservers) power BOTH
  // mail decryption and Files encryption — surface it whenever either is on.
  ...(auth.can('mail') || auth.can('files') ? [{ to: 'profile.mail-keys', icon: 'key', label: 'mail.keys.title' }] : []),
  { to: 'profile.data', icon: 'database', label: 'account.hub_data_heading' },
]);

function isActive(name: string): boolean { return route.name === name; }
</script>
