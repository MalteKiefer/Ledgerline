<template>
  <Card :title="t('account.nav_account')">
    <div class="flex flex-wrap items-center gap-4">
      <span class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-500 text-lg font-medium text-white">
        <img v-if="avatarUrl" :src="avatarUrl" class="h-full w-full object-cover" >
        <template v-else>{{ initials }}</template>
      </span>
      <div class="min-w-0 flex-1">
        <div class="text-sm font-medium">{{ auth.user?.name }}</div>
        <div class="text-sm text-[var(--ll-muted)]">{{ auth.user?.email }}</div>
      </div>
      <Btn variant="soft" @click="pickAvatar">{{ t('pages.profile.avatar_change') }}</Btn>
      <Btn v-if="avatarUrl" variant="danger" icon="delete" @click="onRemoveAvatar">{{ t('common.delete') }}</Btn>
      <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatar" >
    </div>
  </Card>

  <Card :title="t('settings.sync_section')" class="mt-4">
    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('contacts.apple_profile_hint') }}</p>
    <Btn tag="a" variant="soft" icon="download" :href="carddavUrl" download>{{ t('contacts.apple_profile') }}</Btn>
  </Card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';
import { api } from '@spa/api/client';

const auth = useAuthStore();
const p = useProfileStore();
const { success, error } = useToast();

const avatarBust = ref(0);
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarUrl = computed(() => (auth.user?.has_avatar ? api.streamUrl(`/api/v1/avatar?v=${avatarBust.value}`) : ''));
const initials = computed(() => (auth.user?.name ?? '?').slice(0, 1).toUpperCase());
// Downloadable Apple CardDAV configuration profile (bearer via the ?_token= pattern).
const carddavUrl = computed(() => api.streamUrl('/api/v1/account/carddav-profile'));

function pickAvatar() { avatarInput.value?.click(); }

async function onAvatar(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  try {
    await p.uploadAvatar(file);
    if (auth.user) auth.user.has_avatar = true;
    avatarBust.value++;
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  }
}

async function onRemoveAvatar() {
  await p.removeAvatar();
  if (auth.user) auth.user.has_avatar = false;
}
</script>
