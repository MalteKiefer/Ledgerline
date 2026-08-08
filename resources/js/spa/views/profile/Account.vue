<template>
  <v-card rounded="xl" border flat class="mb-4">
    <v-card-title>{{ t('account.nav_account') }}</v-card-title>
    <v-card-text class="d-flex align-center ga-4">
      <v-avatar size="64" color="primary">
        <v-img v-if="avatarUrl" :src="avatarUrl" />
        <span v-else class="text-h6">{{ initials }}</span>
      </v-avatar>
      <div class="flex-grow-1">
        <div class="text-body-1 font-weight-medium">{{ auth.user?.name }}</div>
        <div class="text-medium-emphasis">{{ auth.user?.email }}</div>
      </div>
      <v-btn variant="tonal" :prepend-icon="mdiUpload" @click="pickAvatar">{{ t('pages.profile.avatar_change') }}</v-btn>
      <v-btn v-if="avatarUrl" variant="text" color="error" @click="onRemoveAvatar">{{ t('common.delete') }}</v-btn>
      <input ref="avatarInput" type="file" accept="image/*" class="d-none" @change="onAvatar" >
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiUpload } from '@mdi/js';
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
