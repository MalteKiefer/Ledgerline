<template>
  <div class="grid min-h-screen place-items-center bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>
      <Card :body-class="'p-6'">
        <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.forgot_title') }}</h1>
        <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('auth_ui.forgot_intro') }}</p>

        <div v-if="sent" class="mb-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-600 dark:text-emerald-400">{{ t('auth_ui.reset_link_sent') }}</div>
        <form v-else class="space-y-4" @submit.prevent="submit">
          <TextField v-model="email" :label="t('auth_ui.email')" type="email" icon="mail" autocomplete="username" @enter="submit" />
          <Btn type="submit" variant="solid" size="lg" class="w-full" :loading="busy">{{ t('auth_ui.send_link') }}</Btn>
        </form>

        <div class="mt-5 text-center text-sm">
          <RouterLink :to="{ name: 'login' }" class="text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.back_to_login') }}</RouterLink>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';

const auth = useAuthStore();
const email = ref('');
const busy = ref(false);
const sent = ref(false);

async function submit() {
  if (!email.value) return;
  busy.value = true;
  try {
    // The API always answers generically (no user enumeration) — show the same
    // confirmation regardless of whether the address exists.
    await auth.forgotPassword(email.value);
  } catch { /* still show the generic confirmation */ } finally {
    busy.value = false;
    sent.value = true;
  }
}
</script>
